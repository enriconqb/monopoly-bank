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

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($method === 'GET') {
    if (!is_file($file)) {
        echo 'null';
        exit;
    }
    $raw = file_get_contents($file);
    echo ($raw === false || $raw === '') ? 'null' : $raw;
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    json_decode($raw);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'invalid json'));
        exit;
    }
    if (file_put_contents($file, $raw, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(array('ok' => false, 'error' => 'write failed'));
        exit;
    }
    echo json_encode(array('ok' => true));
    exit;
}

http_response_code(405);
echo json_encode(array('ok' => false, 'error' => 'method not allowed'));
