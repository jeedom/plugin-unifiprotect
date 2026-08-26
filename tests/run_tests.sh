#!/bin/sh

set -eu

php tests/unifiprotectapi_test.php

php -S 127.0.0.1:18081 tests/http_router.php >/tmp/unifiprotect-http-test.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT INT TERM

sleep 1
UNIFI_PROTECT_TEST_URL=http://127.0.0.1:18081 php tests/http_integration_test.php
