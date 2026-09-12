<?php
function mb_id() {
    return uniqid('', true);
}

function mb_now() {
    return (int) round(microtime(true) * 1000);
}

function mb_norm_state($s) {
    if (!is_array($s) || !$s) {
        $s = array();
    }
    if (!isset($s['players']) || !is_array($s['players'])) {
        $s['players'] = array();
    }
    if (!isset($s['properties']) || !is_array($s['properties'])) {
        $s['properties'] = array();
    }
    if (!isset($s['transactions']) || !is_array($s['transactions'])) {
        $s['transactions'] = array();
    }
    if (!isset($s['requests']) || !is_array($s['requests'])) {
        $s['requests'] = array();
    }
    if (!isset($s['settings']) || !is_array($s['settings'])) {
        $s['settings'] = array('startingCash' => 1500, 'goAmount' => 200, 'incomeTax' => 200, 'luxuryTax' => 100);
    }
    if (!isset($s['epoch'])) {
        $s['epoch'] = 0;
    }
    if (!isset($s['rev'])) {
        $s['rev'] = 0;
    }
    if (!isset($s['colorIndex'])) {
        $s['colorIndex'] = 0;
    }
    return $s;
}

function mb_bump(&$s) {
    $s['rev'] = intval(isset($s['rev']) ? $s['rev'] : 0) + 1;
}

function mb_find_player(&$s, $id) {
    if ($id === null || $id === '') {
        return null;
    }
    foreach ($s['players'] as $i => $p) {
        if (isset($p['id']) && $p['id'] === $id) {
            return $i;
        }
    }
    return null;
}

function mb_find_prop(&$s, $id) {
    if ($id === null || $id === '') {
        return null;
    }
    foreach ($s['properties'] as $i => $p) {
        if (isset($p['id']) && $p['id'] === $id) {
            return $i;
        }
    }
    return null;
}

function mb_balances($s) {
    $m = array();
    foreach ($s['players'] as $p) {
        if (isset($p['id'])) {
            $m[$p['id']] = isset($p['balance']) ? $p['balance'] : 0;
        }
    }
    return $m;
}

function mb_push_tx(&$s, $partial) {
    $tx = array_merge(array('id' => mb_id(), 'timestamp' => mb_now()), $partial);
    $s['transactions'][] = $tx;
    if (count($s['transactions']) > 200) {
        $s['transactions'] = array_slice($s['transactions'], -200);
    }
    return $tx;
}

function mb_set_fx(&$s, $from, $to, $amount, $old, $tx) {
    $s['lastFx'] = array(
        'id' => mb_id(),
        'from' => $from,
        'to' => $to,
        'amount' => $amount,
        'oldBalances' => $old,
        'at' => mb_now(),
        'type' => isset($tx['type']) ? $tx['type'] : '',
        'reason' => isset($tx['reason']) ? $tx['reason'] : '',
        'propertyId' => isset($tx['propertyId']) ? $tx['propertyId'] : null
    );
}

function mb_request_label($req) {
    $type = isset($req['type']) ? $req['type'] : '';
    $payload = isset($req['payload']) && is_array($req['payload']) ? $req['payload'] : array();
    $kind = isset($payload['kind']) ? $payload['kind'] : '';
    if ($type === 'build' && $kind === 'hotel') {
        return 'Bangun hotel';
    }
    if ($type === 'build' && $kind === 'sell') {
        return 'Jual bangunan';
    }
    $map = array(
        'go' => 'Request GO',
        'pay_bank' => 'Bayar ke bank',
        'recv_bank' => 'Terima dari bank',
        'transfer' => 'Transfer pemain',
        'rent' => 'Tagih sewa',
        'buy' => 'Beli aset',
        'build' => 'Bangun rumah',
        'mortgage' => 'Hipotek',
        'unmortgage' => 'Tebus hipotek',
        'trade' => 'Tukar aset'
    );
    return isset($map[$type]) ? $map[$type] : 'Permintaan';
}

function mb_set_reject_fx(&$s, $req) {
    $old = mb_balances($s);
    $to = isset($req['playerId']) ? $req['playerId'] : '';
    $payload = isset($req['payload']) && is_array($req['payload']) ? $req['payload'] : array();
    $propId = isset($payload['propertyId']) ? $payload['propertyId'] : null;
    $label = mb_request_label($req);
    mb_set_fx($s, 'bank', $to, 0, $old, array(
        'type' => 'reject',
        'reason' => 'Permintaan ' . $label . ' ditolak',
        'propertyId' => $propId
    ));
}

function mb_group_props($s, $group) {
    $out = array();
    foreach ($s['properties'] as $p) {
        if (isset($p['group']) && $p['group'] === $group) {
            $out[] = $p;
        }
    }
    return $out;
}

function mb_house_stock($s) {
    $houses = 0;
    $hotels = 0;
    foreach ($s['properties'] as $p) {
        if (!empty($p['hotel'])) {
            $hotels++;
        } else {
            $houses += isset($p['houses']) ? intval($p['houses']) : 0;
        }
    }
    return array($houses, $hotels);
}

function mb_even_ok($s, $prop, $nextHouses, $nextHotel) {
    $vals = array();
    foreach (mb_group_props($s, $prop['group']) as $p) {
        if ($p['id'] !== $prop['id']) {
            $vals[] = !empty($p['hotel']) ? 5 : intval(isset($p['houses']) ? $p['houses'] : 0);
        } else {
            $vals[] = $nextHotel ? 5 : $nextHouses;
        }
    }
    if (!$vals) {
        return true;
    }
    return (max($vals) - min($vals)) <= 1;
}

function mb_buildings_on_group($s, $group) {
    foreach (mb_group_props($s, $group) as $p) {
        if (!empty($p['hotel']) || (isset($p['houses']) && $p['houses'] > 0)) {
            return true;
        }
    }
    return false;
}

function mb_owns_full_set($s, $playerId, $group) {
    $list = mb_group_props($s, $group);
    if (!$list) {
        return false;
    }
    foreach ($list as $p) {
        if (!isset($p['ownerId']) || $p['ownerId'] !== $playerId || !empty($p['mortgaged'])) {
            return false;
        }
    }
    return true;
}

function mb_rr_count($s, $ownerId) {
    $n = 0;
    foreach ($s['properties'] as $p) {
        if (isset($p['group']) && $p['group'] === 'railroad' && isset($p['ownerId']) && $p['ownerId'] === $ownerId && empty($p['mortgaged'])) {
            $n++;
        }
    }
    return $n;
}

function mb_util_count($s, $ownerId) {
    $n = 0;
    foreach ($s['properties'] as $p) {
        if (isset($p['group']) && $p['group'] === 'utility' && isset($p['ownerId']) && $p['ownerId'] === $ownerId && empty($p['mortgaged'])) {
            $n++;
        }
    }
    return $n;
}

function mb_calc_rent($s, $prop, $dice) {
    if (empty($prop['ownerId']) || !empty($prop['mortgaged'])) {
        return 0;
    }
    $g = isset($prop['group']) ? $prop['group'] : '';
    $levels = isset($prop['rentLevels']) && is_array($prop['rentLevels']) ? $prop['rentLevels'] : array();
    if ($g === 'railroad') {
        $n = mb_rr_count($s, $prop['ownerId']);
        $i = max(0, $n - 1);
        return isset($levels[$i]) ? $levels[$i] : 0;
    }
    if ($g === 'utility') {
        $n = mb_util_count($s, $prop['ownerId']);
        $d = $dice ? $dice : 7;
        return ($n >= 2 ? 10 : 4) * $d;
    }
    if (!empty($prop['hotel'])) {
        return isset($levels[5]) ? $levels[5] : 0;
    }
    $h = isset($prop['houses']) ? intval($prop['houses']) : 0;
    if ($h > 0) {
        return isset($levels[$h]) ? $levels[$h] : 0;
    }
    $base = isset($levels[0]) ? $levels[0] : 0;
    return mb_owns_full_set($s, $prop['ownerId'], $g) ? $base * 2 : $base;
}

function mb_liquidity($s, $playerId) {
    $pi = mb_find_player($s, $playerId);
    if ($pi === null) {
        return 0;
    }
    $n = floatval($s['players'][$pi]['balance']);
    foreach ($s['properties'] as $pr) {
        if (!isset($pr['ownerId']) || $pr['ownerId'] !== $playerId) {
            continue;
        }
        $refund = floor((isset($pr['houseCost']) ? $pr['houseCost'] : 0) / 2);
        if (!empty($pr['hotel'])) {
            $n += $refund * 5;
        } elseif (!empty($pr['houses'])) {
            $n += $refund * intval($pr['houses']);
        }
        if (empty($pr['mortgaged'])) {
            $n += isset($pr['mortgage']) ? $pr['mortgage'] : 0;
        }
    }
    return $n;
}

function mb_can_raise($s, $playerId) {
    foreach ($s['properties'] as $pr) {
        if (!isset($pr['ownerId']) || $pr['ownerId'] !== $playerId) {
            continue;
        }
        if (!empty($pr['hotel'])) {
            if (mb_even_ok($s, $pr, 4, false)) {
                return true;
            }
        } elseif (!empty($pr['houses'])) {
            if (mb_even_ok($s, $pr, intval($pr['houses']) - 1, false)) {
                return true;
            }
        } elseif (empty($pr['mortgaged']) && !mb_buildings_on_group($s, $pr['group'])) {
            return true;
        }
    }
    return false;
}

function mb_release_assets(&$s, $playerId) {
    foreach ($s['properties'] as $i => $pr) {
        if (!isset($pr['ownerId']) || $pr['ownerId'] !== $playerId) {
            continue;
        }
        $s['properties'][$i]['ownerId'] = null;
        $s['properties'][$i]['houses'] = 0;
        $s['properties'][$i]['hotel'] = false;
        $s['properties'][$i]['mortgaged'] = false;
    }
}

function mb_fail($msg) {
    return array('ok' => false, 'error' => $msg, 'conflict' => true);
}

function mb_ok() {
    return array('ok' => true);
}

function mb_apply_go(&$s, $playerId) {
    $i = mb_find_player($s, $playerId);
    if ($i === null) {
        return mb_fail('Pemain tidak ada');
    }
    $amt = floatval($s['settings']['goAmount']);
    $old = mb_balances($s);
    $s['players'][$i]['balance'] = floatval($s['players'][$i]['balance']) + $amt;
    $tx = mb_push_tx($s, array('type' => 'go', 'from' => 'bank', 'to' => $playerId, 'amount' => $amt, 'reason' => 'GO'));
    mb_set_fx($s, 'bank', $playerId, $amt, $old, $tx);
    return mb_ok();
}

function mb_apply_recv(&$s, $playerId, $amt, $reason) {
    $i = mb_find_player($s, $playerId);
    $amt = floatval($amt);
    if ($i === null || $amt <= 0) {
        return mb_fail('Data terima tidak valid');
    }
    $old = mb_balances($s);
    $s['players'][$i]['balance'] = floatval($s['players'][$i]['balance']) + $amt;
    $tx = mb_push_tx($s, array('type' => 'recv-bank', 'from' => 'bank', 'to' => $playerId, 'amount' => $amt, 'reason' => $reason ? $reason : 'Terima bank'));
    mb_set_fx($s, 'bank', $playerId, $amt, $old, $tx);
    return mb_ok();
}

function mb_apply_transfer(&$s, $fromId, $toId, $amt, $reason) {
    $a = mb_find_player($s, $fromId);
    $b = mb_find_player($s, $toId);
    $amt = floatval($amt);
    if ($a === null || $b === null || $amt <= 0) {
        return mb_fail('Transfer tidak valid');
    }
    if (floatval($s['players'][$a]['balance']) < $amt) {
        return mb_fail('Saldo tidak cukup');
    }
    $old = mb_balances($s);
    $s['players'][$a]['balance'] = floatval($s['players'][$a]['balance']) - $amt;
    $s['players'][$b]['balance'] = floatval($s['players'][$b]['balance']) + $amt;
    $tx = mb_push_tx($s, array('type' => 'p2p', 'from' => $fromId, 'to' => $toId, 'amount' => $amt, 'reason' => $reason ? $reason : 'Transfer'));
    mb_set_fx($s, $fromId, $toId, $amt, $old, $tx);
    return mb_ok();
}

function mb_apply_buy(&$s, $playerId, $propId, $price) {
    $pi = mb_find_player($s, $playerId);
    $xi = mb_find_prop($s, $propId);
    $price = floatval($price);
    if ($xi === null) {
        return mb_fail('Aset tidak ada');
    }
    if (!empty($s['properties'][$xi]['ownerId'])) {
        return mb_fail('Sudah dimiliki');
    }
    if ($pi === null || $price < 0) {
        return mb_fail('Pembeli tidak valid');
    }
    if (floatval($s['players'][$pi]['balance']) < $price) {
        return mb_fail('Saldo tidak cukup');
    }
    $old = mb_balances($s);
    $s['players'][$pi]['balance'] = floatval($s['players'][$pi]['balance']) - $price;
    $s['properties'][$xi]['ownerId'] = $playerId;
    $name = $s['properties'][$xi]['name'];
    $tx = mb_push_tx($s, array('type' => 'buy', 'from' => $playerId, 'to' => 'bank', 'propertyId' => $propId, 'amount' => $price, 'reason' => 'Beli ' . $name));
    mb_set_fx($s, $playerId, 'bank', $price, $old, $tx);
    return mb_ok();
}

function mb_settle_rent(&$s, $payerId, $ownerId, $amount, $propertyId) {
    $a = mb_find_player($s, $payerId);
    $b = mb_find_player($s, $ownerId);
    if ($a === null || $b === null) {
        return mb_fail('Pemain tidak ada');
    }
    $old = mb_balances($s);
    $s['players'][$a]['balance'] = floatval($s['players'][$a]['balance']) - $amount;
    $s['players'][$b]['balance'] = floatval($s['players'][$b]['balance']) + $amount;
    $s['debtSettlement'] = null;
    $name = '';
    $xi = mb_find_prop($s, $propertyId);
    if ($xi !== null) {
        $name = $s['properties'][$xi]['name'];
    }
    $tx = mb_push_tx($s, array('type' => 'rent', 'from' => $payerId, 'to' => $ownerId, 'propertyId' => $propertyId, 'amount' => $amount, 'reason' => 'Sewa ' . $name));
    mb_set_fx($s, $payerId, $ownerId, $amount, $old, $tx);
    return mb_ok();
}

function mb_settle_pay(&$s, $payerId, $amount, $reason) {
    $a = mb_find_player($s, $payerId);
    if ($a === null) {
        return mb_fail('Pemain tidak ada');
    }
    $old = mb_balances($s);
    $s['players'][$a]['balance'] = floatval($s['players'][$a]['balance']) - $amount;
    $s['debtSettlement'] = null;
    $tx = mb_push_tx($s, array('type' => 'pay-bank', 'from' => $payerId, 'to' => 'bank', 'amount' => $amount, 'reason' => $reason ? $reason : 'Bayar bank'));
    mb_set_fx($s, $payerId, 'bank', $amount, $old, $tx);
    return mb_ok();
}

function mb_bankrupt_rent(&$s, $payerId, $ownerId, $amount, $propertyId) {
    $a = mb_find_player($s, $payerId);
    $b = mb_find_player($s, $ownerId);
    if ($a === null || $b === null) {
        return mb_fail('Pemain tidak ada');
    }
    $old = mb_balances($s);
    $paid = min(floatval($s['players'][$a]['balance']), $amount);
    $s['players'][$a]['balance'] = floatval($s['players'][$a]['balance']) - $paid;
    $s['players'][$b]['balance'] = floatval($s['players'][$b]['balance']) + $paid;
    $s['players'][$a]['bankrupt'] = true;
    $s['players'][$a]['balance'] = 0;
    mb_release_assets($s, $payerId);
    $s['debtSettlement'] = null;
    $name = '';
    $xi = mb_find_prop($s, $propertyId);
    if ($xi !== null) {
        $name = $s['properties'][$xi]['name'];
    }
    if ($paid > 0) {
        mb_push_tx($s, array('type' => 'rent', 'from' => $payerId, 'to' => $ownerId, 'propertyId' => $propertyId, 'amount' => $paid, 'reason' => 'Sewa sisa (bangkrut) ' . $name));
    }
    $tx = mb_push_tx($s, array('type' => 'bangkrut', 'from' => $payerId, 'to' => 'bank', 'amount' => 0, 'propertyId' => $propertyId, 'reason' => 'Bangkrut sewa'));
    mb_set_fx($s, $payerId, $ownerId, $paid, $old, $tx);
    return mb_ok();
}

function mb_bankrupt_pay(&$s, $payerId, $amount, $reason) {
    $a = mb_find_player($s, $payerId);
    if ($a === null) {
        return mb_fail('Pemain tidak ada');
    }
    $old = mb_balances($s);
    $paid = min(floatval($s['players'][$a]['balance']), $amount);
    $s['players'][$a]['balance'] = floatval($s['players'][$a]['balance']) - $paid;
    $s['players'][$a]['bankrupt'] = true;
    $s['players'][$a]['balance'] = 0;
    mb_release_assets($s, $payerId);
    $s['debtSettlement'] = null;
    if ($paid > 0) {
        mb_push_tx($s, array('type' => 'pay-bank', 'from' => $payerId, 'to' => 'bank', 'amount' => $paid, 'reason' => ($reason ? $reason : 'Bayar bank') . ' (bangkrut)'));
    }
    $tx = mb_push_tx($s, array('type' => 'bangkrut', 'from' => $payerId, 'to' => 'bank', 'amount' => 0, 'reason' => 'Bangkrut bayar bank'));
    mb_set_fx($s, $payerId, 'bank', $paid, $old, $tx);
    return mb_ok();
}

function mb_apply_rent_due(&$s, $payerId, $ownerId, $amount, $propertyId) {
    $a = mb_find_player($s, $payerId);
    $b = mb_find_player($s, $ownerId);
    if ($a === null || $b === null || $amount <= 0) {
        return mb_fail('Tidak bisa sewa');
    }
    if (!empty($s['players'][$a]['bankrupt'])) {
        return mb_fail('Pemain bangkrut');
    }
    if (!empty($s['debtSettlement'])) {
        return mb_fail('Selesaikan hipotek wajib dulu');
    }
    if (floatval($s['players'][$a]['balance']) >= $amount) {
        return mb_settle_rent($s, $payerId, $ownerId, $amount, $propertyId);
    }
    if (mb_liquidity($s, $payerId) >= $amount) {
        $s['debtSettlement'] = array(
            'status' => 'choosing_mortgage',
            'startedAt' => mb_now(),
            'kind' => 'rent',
            'payerId' => $payerId,
            'ownerId' => $ownerId,
            'amount' => $amount,
            'propertyId' => $propertyId
        );
        return mb_ok();
    }
    return mb_bankrupt_rent($s, $payerId, $ownerId, $amount, $propertyId);
}

function mb_apply_pay_due(&$s, $payerId, $amount, $reason) {
    $a = mb_find_player($s, $payerId);
    $amount = floatval($amount);
    if ($a === null || $amount <= 0) {
        return mb_fail('Data bayar tidak valid');
    }
    if (!empty($s['players'][$a]['bankrupt'])) {
        return mb_fail('Pemain bangkrut');
    }
    if (!empty($s['debtSettlement'])) {
        return mb_fail('Selesaikan hipotek wajib dulu');
    }
    $note = $reason ? $reason : 'Bayar bank';
    if (floatval($s['players'][$a]['balance']) >= $amount) {
        return mb_settle_pay($s, $payerId, $amount, $note);
    }
    if (mb_liquidity($s, $payerId) >= $amount) {
        $s['debtSettlement'] = array(
            'status' => 'choosing_mortgage',
            'startedAt' => mb_now(),
            'kind' => 'pay_bank',
            'payerId' => $payerId,
            'ownerId' => 'bank',
            'amount' => $amount,
            'propertyId' => null,
            'reason' => $note
        );
        return mb_ok();
    }
    return mb_bankrupt_pay($s, $payerId, $amount, $note);
}

function mb_try_debt(&$s) {
    if (empty($s['debtSettlement']) || !is_array($s['debtSettlement'])) {
        return mb_ok();
    }
    $d = $s['debtSettlement'];
    $payerId = isset($d['payerId']) ? $d['payerId'] : '';
    $a = mb_find_player($s, $payerId);
    if ($a === null) {
        $s['debtSettlement'] = null;
        return mb_ok();
    }
    $kind = isset($d['kind']) ? $d['kind'] : 'rent';
    $amt = floatval(isset($d['amount']) ? $d['amount'] : 0);
    if (floatval($s['players'][$a]['balance']) >= $amt) {
        if ($kind === 'pay_bank') {
            return mb_settle_pay($s, $payerId, $amt, isset($d['reason']) ? $d['reason'] : '');
        }
        return mb_settle_rent($s, $payerId, $d['ownerId'], $amt, isset($d['propertyId']) ? $d['propertyId'] : null);
    }
    if (!mb_can_raise($s, $payerId)) {
        if ($kind === 'pay_bank') {
            return mb_bankrupt_pay($s, $payerId, $amt, isset($d['reason']) ? $d['reason'] : '');
        }
        return mb_bankrupt_rent($s, $payerId, $d['ownerId'], $amt, isset($d['propertyId']) ? $d['propertyId'] : null);
    }
    return mb_ok();
}

function mb_apply_build(&$s, $propId, $kind) {
    $xi = mb_find_prop($s, $propId);
    if ($xi === null) {
        return mb_fail('Aset tidak ada');
    }
    $p = $s['properties'][$xi];
    $oi = mb_find_player($s, isset($p['ownerId']) ? $p['ownerId'] : null);
    if ($oi === null) {
        return mb_fail('Tidak ada pemilik');
    }
    $cost = floatval(isset($p['houseCost']) ? $p['houseCost'] : 0);
    $refund = floor($cost / 2);
    $old = mb_balances($s);
    if ($kind === 'hotel') {
        if (!empty($p['hotel']) || intval($p['houses']) !== 4) {
            return mb_fail('Perlu 4 rumah dulu');
        }
        if (!mb_even_ok($s, $p, 0, true)) {
            return mb_fail('Even-build hotel');
        }
        list($hs, $ht) = mb_house_stock($s);
        if ($ht >= 12) {
            return mb_fail('Stok hotel habis');
        }
        if (floatval($s['players'][$oi]['balance']) < $cost) {
            return mb_fail('Saldo tidak cukup');
        }
        $s['players'][$oi]['balance'] -= $cost;
        $s['properties'][$xi]['houses'] = 0;
        $s['properties'][$xi]['hotel'] = true;
        $tx = mb_push_tx($s, array('type' => 'hotel', 'from' => $p['ownerId'], 'to' => 'bank', 'propertyId' => $propId, 'amount' => $cost, 'reason' => 'Hotel ' . $p['name']));
        mb_set_fx($s, $p['ownerId'], 'bank', $cost, $old, $tx);
        return mb_ok();
    }
    if ($kind === 'sell') {
        if (!empty($p['hotel'])) {
            if (!mb_even_ok($s, $p, 4, false)) {
                return mb_fail('Even-sell dulu set lain');
            }
            $s['properties'][$xi]['hotel'] = false;
            $s['properties'][$xi]['houses'] = 4;
            $s['players'][$oi]['balance'] += $refund;
            $tx = mb_push_tx($s, array('type' => 'sell-build', 'from' => 'bank', 'to' => $p['ownerId'], 'propertyId' => $propId, 'amount' => $refund, 'reason' => 'Turun hotel ' . $p['name']));
            mb_set_fx($s, 'bank', $p['ownerId'], $refund, $old, $tx);
            return mb_try_debt($s);
        }
        if (intval($p['houses']) > 0) {
            if (!mb_even_ok($s, $p, intval($p['houses']) - 1, false)) {
                return mb_fail('Even-sell');
            }
            $s['properties'][$xi]['houses'] = intval($p['houses']) - 1;
            $s['players'][$oi]['balance'] += $refund;
            $tx = mb_push_tx($s, array('type' => 'sell-build', 'from' => 'bank', 'to' => $p['ownerId'], 'propertyId' => $propId, 'amount' => $refund, 'reason' => 'Jual rumah ' . $p['name']));
            mb_set_fx($s, 'bank', $p['ownerId'], $refund, $old, $tx);
            return mb_try_debt($s);
        }
        return mb_fail('Tidak ada bangunan');
    }
    if ($cost <= 0 || !empty($p['mortgaged']) || !empty($p['hotel'])) {
        return mb_fail('Tidak bisa bangun');
    }
    foreach (mb_group_props($s, $p['group']) as $x) {
        if (!isset($x['ownerId']) || $x['ownerId'] !== $p['ownerId']) {
            return mb_fail('Butuh set warna lengkap');
        }
        if (!empty($x['mortgaged'])) {
            return mb_fail('Ada tanah hipotek di set');
        }
    }
    if (intval($p['houses']) >= 4) {
        return mb_fail('Sudah 4 rumah, pakai Hotel');
    }
    if (!mb_even_ok($s, $p, intval($p['houses']) + 1, false)) {
        return mb_fail('Even-build: rata dulu');
    }
    list($hs, $ht) = mb_house_stock($s);
    if ($hs >= 32) {
        return mb_fail('Stok rumah habis');
    }
    if (floatval($s['players'][$oi]['balance']) < $cost) {
        return mb_fail('Saldo tidak cukup');
    }
    $s['players'][$oi]['balance'] -= $cost;
    $s['properties'][$xi]['houses'] = intval($p['houses']) + 1;
    $tx = mb_push_tx($s, array('type' => 'house', 'from' => $p['ownerId'], 'to' => 'bank', 'propertyId' => $propId, 'amount' => $cost, 'reason' => 'Rumah ' . $p['name']));
    mb_set_fx($s, $p['ownerId'], 'bank', $cost, $old, $tx);
    return mb_ok();
}

function mb_sell_build_once(&$s, $xi) {
    $p = $s['properties'][$xi];
    $oi = mb_find_player($s, isset($p['ownerId']) ? $p['ownerId'] : null);
    if ($oi === null) {
        return false;
    }
    $refund = floor(floatval(isset($p['houseCost']) ? $p['houseCost'] : 0) / 2);
    if (!empty($p['hotel'])) {
        if (!mb_even_ok($s, $p, 4, false)) {
            return false;
        }
        $s['properties'][$xi]['hotel'] = false;
        $s['properties'][$xi]['houses'] = 4;
        $s['players'][$oi]['balance'] += $refund;
        mb_push_tx($s, array('type' => 'sell-build', 'from' => 'bank', 'to' => $p['ownerId'], 'propertyId' => $p['id'], 'amount' => $refund, 'reason' => 'Turun hotel ' . $p['name']));
        return true;
    }
    if (intval($p['houses']) > 0) {
        if (!mb_even_ok($s, $p, intval($p['houses']) - 1, false)) {
            return false;
        }
        $s['properties'][$xi]['houses'] = intval($p['houses']) - 1;
        $s['players'][$oi]['balance'] += $refund;
        mb_push_tx($s, array('type' => 'sell-build', 'from' => 'bank', 'to' => $p['ownerId'], 'propertyId' => $p['id'], 'amount' => $refund, 'reason' => 'Jual rumah ' . $p['name']));
        return true;
    }
    return false;
}

function mb_liquidate_group(&$s, $group) {
    $guard = 0;
    while (mb_buildings_on_group($s, $group) && $guard < 80) {
        $guard++;
        $bestI = null;
        $bestLvl = -1;
        foreach ($s['properties'] as $i => $pr) {
            if (!isset($pr['group']) || $pr['group'] !== $group) {
                continue;
            }
            $lvl = !empty($pr['hotel']) ? 5 : intval(isset($pr['houses']) ? $pr['houses'] : 0);
            if ($lvl > $bestLvl) {
                $bestLvl = $lvl;
                $bestI = $i;
            }
        }
        if ($bestI === null || $bestLvl <= 0 || !mb_sell_build_once($s, $bestI)) {
            return false;
        }
    }
    return !mb_buildings_on_group($s, $group);
}

function mb_apply_mortgage(&$s, $propId) {
    $xi = mb_find_prop($s, $propId);
    if ($xi === null) {
        return mb_fail('Aset tidak ada');
    }
    $p = $s['properties'][$xi];
    $oi = mb_find_player($s, isset($p['ownerId']) ? $p['ownerId'] : null);
    if ($oi === null || !empty($p['mortgaged'])) {
        return mb_fail('Tidak bisa hipotek');
    }
    if (!mb_liquidate_group($s, $p['group'])) {
        return mb_fail('Jual semua rumah di set dulu');
    }
    $p = $s['properties'][$xi];
    $old = mb_balances($s);
    $amt = floatval(isset($p['mortgage']) ? $p['mortgage'] : 0);
    $s['properties'][$xi]['mortgaged'] = true;
    $s['players'][$oi]['balance'] += $amt;
    $tx = mb_push_tx($s, array('type' => 'mortgage', 'from' => 'bank', 'to' => $p['ownerId'], 'propertyId' => $propId, 'amount' => $amt, 'reason' => 'Hipotek ' . $p['name']));
    mb_set_fx($s, 'bank', $p['ownerId'], $amt, $old, $tx);
    return mb_try_debt($s);
}

function mb_apply_unmortgage(&$s, $propId) {
    $xi = mb_find_prop($s, $propId);
    if ($xi === null) {
        return mb_fail('Aset tidak ada');
    }
    $p = $s['properties'][$xi];
    $oi = mb_find_player($s, isset($p['ownerId']) ? $p['ownerId'] : null);
    if ($oi === null || empty($p['mortgaged'])) {
        return mb_fail('Tidak sedang hipotek');
    }
    $cost = round(floatval($p['mortgage']) * 1.1);
    if (floatval($s['players'][$oi]['balance']) < $cost) {
        return mb_fail('Saldo tidak cukup');
    }
    $old = mb_balances($s);
    $s['players'][$oi]['balance'] -= $cost;
    $s['properties'][$xi]['mortgaged'] = false;
    $tx = mb_push_tx($s, array('type' => 'unmortgage', 'from' => $p['ownerId'], 'to' => 'bank', 'propertyId' => $propId, 'amount' => $cost, 'reason' => 'Tebus ' . $p['name']));
    mb_set_fx($s, $p['ownerId'], 'bank', $cost, $old, $tx);
    return mb_ok();
}

function mb_apply_trade(&$s, $pl) {
    $aId = isset($pl['aId']) ? $pl['aId'] : '';
    $bId = isset($pl['bId']) ? $pl['bId'] : '';
    $ai = mb_find_player($s, $aId);
    $bi = mb_find_player($s, $bId);
    if ($ai === null || $bi === null || $aId === $bId) {
        return mb_fail('Pemain tidak valid');
    }
    if (!empty($s['players'][$ai]['bankrupt']) || !empty($s['players'][$bi]['bankrupt'])) {
        return mb_fail('Pemain bangkrut tidak bisa tukar');
    }
    $oa = isset($pl['offerA']) && is_array($pl['offerA']) ? $pl['offerA'] : array();
    $ob = isset($pl['offerB']) && is_array($pl['offerB']) ? $pl['offerB'] : array();
    $oaIds = isset($oa['propertyIds']) && is_array($oa['propertyIds']) ? $oa['propertyIds'] : array();
    $obIds = isset($ob['propertyIds']) && is_array($ob['propertyIds']) ? $ob['propertyIds'] : array();
    $oaCash = max(0, round(floatval(isset($oa['cash']) ? $oa['cash'] : 0)));
    $obCash = max(0, round(floatval(isset($ob['cash']) ? $ob['cash'] : 0)));
    if (!$oaIds && !$obIds && $oaCash <= 0 && $obCash <= 0) {
        return mb_fail('Tawaran kosong');
    }
    if ($oaCash > 0 && floatval($s['players'][$ai]['balance']) < $oaCash) {
        return mb_fail('Saldo kurang');
    }
    if ($obCash > 0 && floatval($s['players'][$bi]['balance']) < $obCash) {
        return mb_fail('Saldo kurang');
    }
    foreach ($oaIds as $id) {
        $xi = mb_find_prop($s, $id);
        if ($xi === null || $s['properties'][$xi]['ownerId'] !== $aId) {
            return mb_fail('Aset tidak milik pemain');
        }
        if (!empty($s['properties'][$xi]['houses']) || !empty($s['properties'][$xi]['hotel'])) {
            return mb_fail('Jual bangunan dulu');
        }
    }
    foreach ($obIds as $id) {
        $xi = mb_find_prop($s, $id);
        if ($xi === null || $s['properties'][$xi]['ownerId'] !== $bId) {
            return mb_fail('Aset tidak milik pemain');
        }
        if (!empty($s['properties'][$xi]['houses']) || !empty($s['properties'][$xi]['hotel'])) {
            return mb_fail('Jual bangunan dulu');
        }
    }
    $old = mb_balances($s);
    foreach ($oaIds as $id) {
        $xi = mb_find_prop($s, $id);
        $s['properties'][$xi]['ownerId'] = $bId;
        mb_push_tx($s, array('type' => 'deed', 'from' => $aId, 'to' => $bId, 'propertyId' => $id, 'amount' => 0, 'reason' => 'Tukar ' . $s['properties'][$xi]['name']));
    }
    foreach ($obIds as $id) {
        $xi = mb_find_prop($s, $id);
        $s['properties'][$xi]['ownerId'] = $aId;
        mb_push_tx($s, array('type' => 'deed', 'from' => $bId, 'to' => $aId, 'propertyId' => $id, 'amount' => 0, 'reason' => 'Tukar ' . $s['properties'][$xi]['name']));
    }
    if ($oaCash > 0) {
        $s['players'][$ai]['balance'] -= $oaCash;
        $s['players'][$bi]['balance'] += $oaCash;
        mb_push_tx($s, array('type' => 'p2p', 'from' => $aId, 'to' => $bId, 'amount' => $oaCash, 'reason' => 'Uang tukar'));
    }
    if ($obCash > 0) {
        $s['players'][$bi]['balance'] -= $obCash;
        $s['players'][$ai]['balance'] += $obCash;
        mb_push_tx($s, array('type' => 'p2p', 'from' => $bId, 'to' => $aId, 'amount' => $obCash, 'reason' => 'Uang tukar'));
    }
    $net = $oaCash - $obCash;
    $from = $net >= 0 ? $aId : $bId;
    $to = $net >= 0 ? $bId : $aId;
    mb_set_fx($s, $from, $to, abs($net), $old, array('type' => 'deed', 'reason' => 'Tukar aset'));
    return mb_ok();
}

function mb_apply_request(&$s, $req) {
    $type = isset($req['type']) ? $req['type'] : '';
    $pl = isset($req['payload']) && is_array($req['payload']) ? $req['payload'] : array();
    $pid = isset($req['playerId']) ? $req['playerId'] : '';
    if ($type === 'go') {
        return mb_apply_go($s, $pid);
    }
    if ($type === 'pay_bank') {
        return mb_apply_pay_due($s, $pid, isset($pl['amount']) ? $pl['amount'] : 0, isset($pl['reason']) ? $pl['reason'] : 'Bayar bank');
    }
    if ($type === 'recv_bank') {
        return mb_apply_recv($s, $pid, isset($pl['amount']) ? $pl['amount'] : 0, isset($pl['reason']) ? $pl['reason'] : 'Terima bank');
    }
    if ($type === 'transfer') {
        return mb_apply_transfer($s, $pid, isset($pl['toPlayerId']) ? $pl['toPlayerId'] : '', isset($pl['amount']) ? $pl['amount'] : 0, isset($pl['reason']) ? $pl['reason'] : 'Transfer');
    }
    if ($type === 'buy') {
        return mb_apply_buy($s, $pid, isset($pl['propertyId']) ? $pl['propertyId'] : '', isset($pl['amount']) ? $pl['amount'] : 0);
    }
    if ($type === 'rent') {
        $xi = mb_find_prop($s, isset($pl['propertyId']) ? $pl['propertyId'] : '');
        if ($xi === null) {
            return mb_fail('Tidak bisa sewa');
        }
        $p = $s['properties'][$xi];
        $payerId = isset($pl['payerId']) ? $pl['payerId'] : '';
        if (!$payerId && $pid && $pid !== $p['ownerId']) {
            $payerId = $pid;
        }
        if (empty($p['ownerId']) || !empty($p['mortgaged']) || !$payerId || $payerId === $p['ownerId']) {
            return mb_fail('Tidak bisa sewa');
        }
        $dice = isset($pl['dice']) ? $pl['dice'] : null;
        if (isset($p['group']) && $p['group'] === 'utility') {
            $dice = floatval($dice);
            if ($dice < 2 || $dice > 12) {
                return mb_fail('Dadu 2–12');
            }
        }
        $amount = mb_calc_rent($s, $p, $dice);
        if ($amount <= 0) {
            return mb_fail('Sewa 0');
        }
        return mb_apply_rent_due($s, $payerId, $p['ownerId'], $amount, $p['id']);
    }
    if ($type === 'build') {
        $kind = isset($pl['kind']) ? $pl['kind'] : 'house';
        return mb_apply_build($s, isset($pl['propertyId']) ? $pl['propertyId'] : '', $kind);
    }
    if ($type === 'mortgage') {
        return mb_apply_mortgage($s, isset($pl['propertyId']) ? $pl['propertyId'] : '');
    }
    if ($type === 'unmortgage') {
        return mb_apply_unmortgage($s, isset($pl['propertyId']) ? $pl['propertyId'] : '');
    }
    if ($type === 'trade') {
        return mb_apply_trade($s, $pl);
    }
    return mb_fail('Jenis permintaan tidak dikenal');
}

function mb_rank_asset_value($pr) {
    if (!empty($pr['mortgaged'])) {
        return floatval(isset($pr['mortgage']) ? $pr['mortgage'] : 0);
    }
    return floatval(isset($pr['price']) ? $pr['price'] : 0);
}

function mb_end_game(&$s) {
    $rows = array();
    foreach ($s['players'] as $pl) {
        $assets = array();
        $assetValue = 0;
        foreach ($s['properties'] as $pr) {
            if (!isset($pr['ownerId']) || $pr['ownerId'] !== $pl['id']) {
                continue;
            }
            $val = mb_rank_asset_value($pr);
            $assetValue += $val;
            $assets[] = array(
                'id' => $pr['id'],
                'name' => $pr['name'],
                'value' => $val,
                'mortgaged' => !empty($pr['mortgaged']),
                'houses' => isset($pr['houses']) ? $pr['houses'] : 0,
                'hotel' => !empty($pr['hotel'])
            );
        }
        $bal = floatval(isset($pl['balance']) ? $pl['balance'] : 0);
        $rows[] = array(
            'playerId' => $pl['id'],
            'name' => $pl['name'],
            'color' => $pl['color'],
            'bankrupt' => !empty($pl['bankrupt']),
            'balance' => $bal,
            'assetValue' => $assetValue,
            'netWorth' => $bal + $assetValue,
            'assets' => $assets
        );
    }
    usort($rows, function ($a, $b) {
        if ($b['netWorth'] != $a['netWorth']) {
            return ($b['netWorth'] > $a['netWorth']) ? 1 : -1;
        }
        return strcmp($a['name'], $b['name']);
    });
    $s['gameOver'] = array('endedAt' => mb_now(), 'rankings' => $rows);
    return mb_ok();
}

function mb_command(&$s, $kind, $in) {
    $colors = array('#D32F2F','#1565C0','#2E7D32','#F9A825','#6A1B9A','#E65100','#AD1457','#00695C','#4E342E','#37474F');
    if ($kind === 'addPlayer') {
        $name = trim(isset($in['name']) ? $in['name'] : '');
        $bal = floatval(isset($in['balance']) ? $in['balance'] : 0);
        if ($name === '' || $bal < 0) {
            return mb_fail('Nama/saldo tidak valid');
        }
        $ci = intval($s['colorIndex']);
        $color = $colors[$ci % count($colors)];
        $s['colorIndex'] = $ci + 1;
        $s['players'][] = array('id' => mb_id(), 'name' => $name, 'color' => $color, 'balance' => $bal, 'bankrupt' => false);
        return mb_ok();
    }
    if ($kind === 'settings') {
        if (isset($in['startingCash'])) {
            $s['settings']['startingCash'] = floatval($in['startingCash']);
        }
        if (isset($in['goAmount'])) {
            $s['settings']['goAmount'] = floatval($in['goAmount']);
        }
        return mb_ok();
    }
    if ($kind === 'bankrupt') {
        $i = mb_find_player($s, isset($in['playerId']) ? $in['playerId'] : '');
        if ($i === null) {
            return mb_fail('Pemain tidak ada');
        }
        $on = empty($s['players'][$i]['bankrupt']);
        $s['players'][$i]['bankrupt'] = $on;
        if ($on) {
            mb_release_assets($s, $s['players'][$i]['id']);
            if (!empty($s['debtSettlement']['payerId']) && $s['debtSettlement']['payerId'] === $s['players'][$i]['id']) {
                $s['debtSettlement'] = null;
            }
        }
        return mb_ok();
    }
    if ($kind === 'deletePlayer') {
        $id = isset($in['playerId']) ? $in['playerId'] : '';
        mb_release_assets($s, $id);
        $out = array();
        foreach ($s['players'] as $p) {
            if ($p['id'] !== $id) {
                $out[] = $p;
            }
        }
        $s['players'] = $out;
        return mb_ok();
    }
    if ($kind === 'reset') {
        $s['epoch'] = intval($s['epoch']) + 1;
        $s['players'] = array();
        $s['transactions'] = array();
        $s['requests'] = array();
        $s['lastFx'] = null;
        $s['debtSettlement'] = null;
        $s['gameOver'] = null;
        $s['colorIndex'] = 0;
        foreach ($s['properties'] as $i => $pr) {
            $s['properties'][$i]['ownerId'] = null;
            $s['properties'][$i]['houses'] = 0;
            $s['properties'][$i]['hotel'] = false;
            $s['properties'][$i]['mortgaged'] = false;
        }
        return mb_ok();
    }
    if ($kind === 'endGame') {
        return mb_end_game($s);
    }
    if ($kind === 'playerColor') {
        $i = mb_find_player($s, isset($in['playerId']) ? $in['playerId'] : '');
        $hex = isset($in['color']) ? $in['color'] : '';
        if ($i === null || !$hex) {
            return mb_fail('Warna tidak valid');
        }
        $s['players'][$i]['color'] = $hex;
        return mb_ok();
    }
    if ($kind === 'go') {
        return mb_apply_go($s, isset($in['playerId']) ? $in['playerId'] : '');
    }
    if ($kind === 'payBank') {
        return mb_apply_pay_due($s, isset($in['playerId']) ? $in['playerId'] : '', isset($in['amount']) ? $in['amount'] : 0, isset($in['reason']) ? $in['reason'] : '');
    }
    if ($kind === 'recvBank') {
        return mb_apply_recv($s, isset($in['playerId']) ? $in['playerId'] : '', isset($in['amount']) ? $in['amount'] : 0, isset($in['reason']) ? $in['reason'] : '');
    }
    if ($kind === 'transfer') {
        return mb_apply_transfer($s, isset($in['fromId']) ? $in['fromId'] : '', isset($in['toId']) ? $in['toId'] : '', isset($in['amount']) ? $in['amount'] : 0, isset($in['reason']) ? $in['reason'] : '');
    }
    if ($kind === 'buy') {
        return mb_apply_buy($s, isset($in['playerId']) ? $in['playerId'] : '', isset($in['propertyId']) ? $in['propertyId'] : '', isset($in['amount']) ? $in['amount'] : 0);
    }
    if ($kind === 'rent') {
        $xi = mb_find_prop($s, isset($in['propertyId']) ? $in['propertyId'] : '');
        if ($xi === null) {
            return mb_fail('Tidak bisa sewa');
        }
        $p = $s['properties'][$xi];
        $amount = mb_calc_rent($s, $p, isset($in['dice']) ? $in['dice'] : 7);
        return mb_apply_rent_due($s, isset($in['payerId']) ? $in['payerId'] : '', $p['ownerId'], $amount, $p['id']);
    }
    if ($kind === 'build') {
        $buildKind = isset($in['buildKind']) ? $in['buildKind'] : 'house';
        return mb_apply_build($s, isset($in['propertyId']) ? $in['propertyId'] : '', $buildKind);
    }
    if ($kind === 'mortgage') {
        return mb_apply_mortgage($s, isset($in['propertyId']) ? $in['propertyId'] : '');
    }
    if ($kind === 'unmortgage') {
        return mb_apply_unmortgage($s, isset($in['propertyId']) ? $in['propertyId'] : '');
    }
    if ($kind === 'transferProp') {
        $xi = mb_find_prop($s, isset($in['propertyId']) ? $in['propertyId'] : '');
        $ti = mb_find_player($s, isset($in['toPlayerId']) ? $in['toPlayerId'] : '');
        if ($xi === null || $ti === null) {
            return mb_fail('Pilih penerima');
        }
        if (!empty($s['properties'][$xi]['houses']) || !empty($s['properties'][$xi]['hotel'])) {
            return mb_fail('Jual bangunan dulu');
        }
        $s['properties'][$xi]['ownerId'] = $s['players'][$ti]['id'];
        mb_push_tx($s, array('type' => 'deed', 'from' => 'trade', 'to' => $s['players'][$ti]['id'], 'propertyId' => $s['properties'][$xi]['id'], 'amount' => 0, 'reason' => 'Alih ' . $s['properties'][$xi]['name']));
        return mb_ok();
    }
    if ($kind === 'returnBank') {
        $xi = mb_find_prop($s, isset($in['propertyId']) ? $in['propertyId'] : '');
        if ($xi === null) {
            return mb_fail('Aset tidak ada');
        }
        $s['properties'][$xi]['ownerId'] = null;
        $s['properties'][$xi]['houses'] = 0;
        $s['properties'][$xi]['hotel'] = false;
        $s['properties'][$xi]['mortgaged'] = false;
        mb_push_tx($s, array('type' => 'return', 'from' => 'player', 'to' => 'bank', 'propertyId' => $s['properties'][$xi]['id'], 'amount' => 0, 'reason' => 'Kembali ' . $s['properties'][$xi]['name']));
        return mb_ok();
    }
    if ($kind === 'completeDebt') {
        return mb_try_debt($s);
    }
    if ($kind === 'trade') {
        return mb_apply_trade($s, $in);
    }
    return mb_fail('Perintah tidak dikenal');
}
