<?php

require_once dirname(__DIR__) . '/3rdparty/unifiprotectapi.class.php';

class MockUnifiProtectApi extends unifiprotectapi {
    public $json_responses = array();
    public $binary_response = 'jpeg-data';
    public $last_binary_path = '';

    public function __construct() {
        parent::__construct('test-api-key', 'https://protect.example.test');
    }

    protected function request_json($path) {
        return array_key_exists($path, $this->json_responses)
            ? $this->json_responses[$path]
            : false;
    }

    protected function request_binary($path, $expected_content_type) {
        $this->last_binary_path = $path;
        return $this->binary_response;
    }
}

class MockTransportUnifiProtectApi extends unifiprotectapi {
    public $response = array('body' => '{}', 'content_type' => 'application/json');

    public function __construct() {
        parent::__construct('test-api-key', 'https://protect.example.test');
    }

    protected function request($path, $accept) {
        return $this->response;
    }

    public function decode_json($path) {
        return parent::request_json($path);
    }

    public function decode_binary($path, $content_type) {
        return parent::request_binary($path, $content_type);
    }
}

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_true($actual, $message) {
    assert_same(true, $actual, $message);
}

$api = new MockUnifiProtectApi();
$api->json_responses = array(
    '/meta/info' => array('applicationVersion' => '7.1.87'),
    '/nvrs' => array(
        'id' => 'nvr-id',
        'modelKey' => 'nvr',
        'name' => null,
        'doorbellSettings' => array(),
        'armMode' => array(),
    ),
    '/cameras' => array(
        array('id' => 'camera-1', 'modelKey' => 'camera', 'state' => 'CONNECTED', 'name' => 'Front', 'mac' => 'AA:BB:CC:DD:EE:01'),
        array('id' => 'camera-2', 'modelKey' => 'camera', 'state' => 'DISCONNECTED', 'name' => null, 'mac' => 'AA:BB:CC:DD:EE:02'),
    ),
    '/chimes' => array(
        array('id' => 'chime-1', 'modelKey' => 'chime', 'state' => 'CONNECTING', 'name' => 'Hall', 'mac' => 'AA:BB:CC:DD:EE:03'),
    ),
);

assert_true($api->login(), 'The official meta response authenticates the client');
$server_info = $api->get_server_info();
assert_true(is_array($server_info), 'Official resources are aggregated');
assert_same('UniFi Protect nvr-id', $server_info['nvr']['name'], 'A nullable official NVR name gets a stable fallback');
assert_same('nvr', $server_info['nvr']['type'], 'The NVR is mapped to the generic Jeedom profile');
assert_same(true, $server_info['cameras'][0]['isConnected'], 'CONNECTED is mapped to true');
assert_same(false, $server_info['cameras'][1]['isConnected'], 'DISCONNECTED is mapped to false');
assert_same('Camera camera-2', $server_info['cameras'][1]['name'], 'A nullable official camera name gets a stable fallback');
assert_same(false, $server_info['chimes'][0]['isConnected'], 'CONNECTING is not reported as connected');

assert_same('jpeg-data', $api->get_snapshot('camera/id'), 'The snapshot payload is returned unchanged');
assert_same('/cameras/camera%2Fid/snapshot?highQuality=true', $api->last_binary_path, 'The camera id is encoded in the official snapshot path');

$api_with_invalid_camera = new MockUnifiProtectApi();
$api_with_invalid_camera->json_responses['/cameras'] = array(
    array('id' => 'camera-1', 'modelKey' => 'camera', 'state' => 'CONNECTED', 'name' => 'Front'),
);
assert_same(false, $api_with_invalid_camera->get_cameras(), 'A camera response missing its MAC address is rejected');
assert_true(strpos($api_with_invalid_camera->get_last_error_message(), 'valid state') !== false, 'The schema error identifies the expected device contract');

$api_with_invalid_nvr = new MockUnifiProtectApi();
$api_with_invalid_nvr->json_responses['/nvrs'] = array('id' => 'nvr-id');
assert_same(false, $api_with_invalid_nvr->get_nvr(), 'An incomplete NVR response is rejected');

$api_with_changed_state = new MockUnifiProtectApi();
$api_with_changed_state->json_responses['/chimes'] = array(
    array('id' => 'chime-1', 'modelKey' => 'chime', 'state' => 'UNKNOWN_NEW_STATE', 'name' => 'Hall', 'mac' => 'AA:BB:CC:DD:EE:03'),
);
assert_same(false, $api_with_changed_state->get_chimes(), 'An undocumented connection state is rejected instead of silently changing Jeedom values');

$transport = new MockTransportUnifiProtectApi();
$transport->response = array('body' => '{"applicationVersion":"7.1.87"}', 'content_type' => 'application/json; charset=utf-8');
assert_same(array('applicationVersion' => '7.1.87'), $transport->decode_json('/meta/info'), 'A valid JSON content type and payload are accepted');
$transport->response = array('body' => '{"applicationVersion":"7.1.87"}', 'content_type' => 'text/html');
assert_same(false, $transport->decode_json('/meta/info'), 'A JSON-looking body with the wrong content type is rejected');
$transport->response = array('body' => 'jpeg-data', 'content_type' => 'image/jpeg');
assert_same('jpeg-data', $transport->decode_binary('/snapshot', 'image/jpeg'), 'A non-empty JPEG response is accepted');
$transport->response = array('body' => '', 'content_type' => 'image/jpeg');
assert_same(false, $transport->decode_binary('/snapshot', 'image/jpeg'), 'An empty JPEG response is rejected');

echo "All UniFi Protect API tests passed\n";
