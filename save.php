<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'save';
$file = $dir . DIRECTORY_SEPARATOR . 'game.json';

if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'cannot create save folder'));
    exit;
}

function mb_req_progress($r) {
    if (!is_array($r) || !isset($r['status'])) {
        return 0;
    }
    $s = $r['status'];
    if ($s === 'approved' || $s === 'rejected') {
        return 3;
    }
    if ($s === 'pending') {
        return 2;
    }
    if ($s === 'negotiating') {
        return 1;
    }
    return 0;
}

function mb_merge_requests($existing, $incoming) {
    $byId = array();
    if (is_array($existing)) {
        foreach ($existing as $r) {
            if (is_array($r) && isset($r['id'])) {
                $byId[$r['id']] = $r;
            }
        }
    }
    if (is_array($incoming)) {
        foreach ($incoming as $r) {
            if (!is_array($r) || !isset($r['id'])) {
                continue;
            }
            $id = $r['id'];
            if (!isset($byId[$id])) {
                $byId[$id] = $r;
                continue;
            }
            $old = $byId[$id];
            $lp = mb_req_progress($old);
            $rp = mb_req_progress($r);
            if ($lp >= 3 && $rp < 3) {
                continue;
            }
            if (isset($old['status'], $r['status']) && $old['status'] === 'negotiating' && $r['status'] === 'negotiating') {
                $lr = 0;
                $rr = 0;
                if (isset($old['payload']) && is_array($old['payload']) && isset($old['payload']['round'])) {
                    $lr = intval($old['payload']['round']);
                }
                if (isset($r['payload']) && is_array($r['payload']) && isset($r['payload']['round'])) {
                    $rr = intval($r['payload']['round']);
                }
                $byId[$id] = ($rr >= $lr) ? $r : $old;
                continue;
            }
            $byId[$id] = ($rp >= $lp) ? $r : $old;
        }
    }
    return array_values($byId);
}

function mb_read_state($fp) {
    rewind($fp);
    $raw = stream_get_contents($fp);
    if ($raw === false || $raw === '') {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

function mb_epoch($data) {
    if (!is_array($data) || !isset($data['epoch'])) {
        return 0;
    }
    return intval($data['epoch']);
}

function mb_rev($data) {
    if (!is_array($data) || !isset($data['rev'])) {
        return 0;
    }
    return intval($data['rev']);
}

function mb_payload_key($payload) {
    if (!is_array($payload)) {
        return '';
    }
    return json_encode($payload);
}

function mb_is_duplicate_request($list, $req) {
    $id = isset($req['id']) ? $req['id'] : '';
    $pid = isset($req['playerId']) ? strval($req['playerId']) : '';
    $type = isset($req['type']) ? $req['type'] : '';
    $key = mb_payload_key(isset($req['payload']) && is_array($req['payload']) ? $req['payload'] : array());
    $now = isset($req['createdAt']) ? intval($req['createdAt']) : 0;
    if ($now <= 0) {
        $now = (int) round(microtime(true) * 1000);
    }
    if (!is_array($list)) {
        return false;
    }
    foreach ($list as $r) {
        if (!is_array($r) || !isset($r['id'])) {
            continue;
        }
        if ($r['id'] === $id) {
            continue;
        }
        if (!isset($r['playerId']) || strval($r['playerId']) !== $pid) {
            continue;
        }
        if (!isset($r['type']) || $r['type'] !== $type) {
            continue;
        }
        $st = isset($r['status']) ? $r['status'] : '';
        if ($st !== 'pending' && $st !== 'negotiating') {
            continue;
        }
        $created = isset($r['createdAt']) ? intval($r['createdAt']) : 0;
        if ($now - $created > 5000) {
            continue;
        }
        $okey = mb_payload_key(isset($r['payload']) && is_array($r['payload']) ? $r['payload'] : array());
        if ($okey === $key) {
            return true;
        }
    }
    return false;
}

function mb_write_state($fp, $data) {
    $json = json_encode($data);
    if ($json === false) {
        return false;
    }
    rewind($fp);
    if (!ftruncate($fp, 0)) {
        return false;
    }
    return fwrite($fp, $json) !== false;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'game_apply.php';

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($method === 'GET') {
    if (!is_file($file)) {
        echo 'null';
        exit;
    }
    $fp = fopen($file, 'r');
    if ($fp === false) {
        echo 'null';
        exit;
    }
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    echo ($raw === false || $raw === '') ? 'null' : $raw;
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $incoming = json_decode($raw, true);
    if (!is_array($incoming)) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'invalid json'));
        exit;
    }

    $fp = fopen($file, 'c+');
    if ($fp === false) {
        http_response_code(500);
        echo json_encode(array('ok' => false, 'error' => 'write failed'));
        exit;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        http_response_code(500);
        echo json_encode(array('ok' => false, 'error' => 'lock failed'));
        exit;
    }

    $current = mb_norm_state(mb_read_state($fp));
    $oldReqs = (isset($current['requests']) && is_array($current['requests'])) ? $current['requests'] : array();
    $op = isset($incoming['_op']) ? $incoming['_op'] : '';

    if ($op === 'upsertRequest' && isset($incoming['request']) && is_array($incoming['request'])) {
        $req = $incoming['request'];
        if (!isset($req['id'])) {
            flock($fp, LOCK_UN);
            fclose($fp);
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => 'request id required'));
            exit;
        }
        if (mb_is_duplicate_request($oldReqs, $req)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            echo json_encode(array('ok' => false, 'duplicate' => true, 'requests' => $oldReqs, 'state' => $current));
            exit;
        }
        $current['requests'] = mb_merge_requests($oldReqs, array($req));
        $ok = mb_write_state($fp, $current);
        flock($fp, LOCK_UN);
        fclose($fp);
        if (!$ok) {
            http_response_code(500);
            echo json_encode(array('ok' => false, 'error' => 'write failed'));
            exit;
        }
        echo json_encode(array('ok' => true, 'requests' => $current['requests'], 'state' => $current));
        exit;
    }

    if ($op === 'commitRequest' || $op === 'rejectRequest') {
        $id = isset($incoming['id']) ? $incoming['id'] : '';
        $found = null;
        $idx = -1;
        foreach ($current['requests'] as $i => $r) {
            if (isset($r['id']) && $r['id'] === $id) {
                $found = $r;
                $idx = $i;
                break;
            }
        }
        if ($idx < 0 || !$found || (isset($found['status']) && $found['status'] !== 'pending')) {
            flock($fp, LOCK_UN);
            fclose($fp);
            echo json_encode(array('ok' => false, 'conflict' => true, 'error' => 'Sudah diproses', 'state' => $current));
            exit;
        }
        if ($op === 'rejectRequest') {
            $current['requests'][$idx]['status'] = 'rejected';
            $current['requests'][$idx]['resolvedAt'] = mb_now();
            mb_set_reject_fx($current, $found);
            mb_bump($current);
            $ok = mb_write_state($fp, $current);
            flock($fp, LOCK_UN);
            fclose($fp);
            echo json_encode(array('ok' => $ok, 'state' => $current));
            exit;
        }
        $applied = mb_apply_request($current, $found);
        if (empty($applied['ok'])) {
            flock($fp, LOCK_UN);
            fclose($fp);
            echo json_encode(array('ok' => false, 'conflict' => true, 'error' => isset($applied['error']) ? $applied['error'] : 'Gagal', 'state' => $current));
            exit;
        }
        $current['requests'][$idx]['status'] = 'approved';
        $current['requests'][$idx]['resolvedAt'] = mb_now();
        mb_bump($current);
        $ok = mb_write_state($fp, $current);
        flock($fp, LOCK_UN);
        fclose($fp);
        echo json_encode(array('ok' => $ok, 'state' => $current));
        exit;
    }

    if ($op === 'command') {
        $kind = isset($incoming['kind']) ? $incoming['kind'] : '';
        $applied = mb_command($current, $kind, $incoming);
        if (empty($applied['ok'])) {
            flock($fp, LOCK_UN);
            fclose($fp);
            echo json_encode(array('ok' => false, 'conflict' => true, 'error' => isset($applied['error']) ? $applied['error'] : 'Gagal', 'state' => $current));
            exit;
        }
        mb_bump($current);
        $ok = mb_write_state($fp, $current);
        flock($fp, LOCK_UN);
        fclose($fp);
        echo json_encode(array('ok' => $ok, 'state' => $current));
        exit;
    }

    flock($fp, LOCK_UN);
    fclose($fp);
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'unknown op'));
    exit;
}

http_response_code(405);
echo json_encode(array('ok' => false, 'error' => 'method not allowed'));
