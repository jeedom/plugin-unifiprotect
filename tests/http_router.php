<?php

if (!isset($_SERVER['HTTP_X_API_KEY']) || $_SERVER['HTTP_X_API_KEY'] !== 'test-api-key') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'invalid API key'));
    return;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/proxy/protect/integration/v1/cameras/camera-1/snapshot') {
    header('Content-Type: image/jpeg');
    echo 'jpeg-data';
    return;
}

$responses = array(
    '/proxy/protect/integration/v1/meta/info' => array('applicationVersion' => '7.1.87'),
    '/proxy/protect/integration/v1/nvrs' => array('id' => 'nvr-id', 'modelKey' => 'nvr', 'name' => 'Protect'),
    '/proxy/protect/integration/v1/cameras' => array(
        array('id' => 'camera-1', 'modelKey' => 'camera', 'state' => 'CONNECTED', 'name' => 'Front', 'mac' => 'AA:BB:CC:DD:EE:01'),
    ),
    '/proxy/protect/integration/v1/chimes' => array(
        array('id' => 'chime-1', 'modelKey' => 'chime', 'state' => 'CONNECTED', 'name' => 'Hall', 'mac' => 'AA:BB:CC:DD:EE:02'),
    ),
);

if (!array_key_exists($path, $responses)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'unknown test endpoint'));
    return;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($responses[$path]);
