<?php

require_once dirname(__DIR__) . '/3rdparty/unifiprotectapi.class.php';

function integration_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$base_url = getenv('UNIFI_PROTECT_TEST_URL');
if ($base_url === false || $base_url === '') {
    fwrite(STDERR, "UNIFI_PROTECT_TEST_URL is required\n");
    exit(1);
}

$api = new unifiprotectapi('test-api-key', $base_url);
integration_assert_same(true, $api->login(), 'The X-API-Key header authenticates against the HTTP fixture');

$server_info = $api->get_server_info();
integration_assert_same('nvr-id', $server_info['nvr']['id'], 'The official NVR endpoint is requested and decoded');
integration_assert_same(true, $server_info['cameras'][0]['isConnected'], 'The official camera response is validated and adapted');
integration_assert_same(true, $server_info['chimes'][0]['isConnected'], 'The official chime response is validated and adapted');
integration_assert_same('jpeg-data', $api->get_snapshot('camera-1'), 'The official JPEG snapshot response is validated');

echo "All UniFi Protect HTTP integration tests passed\n";
