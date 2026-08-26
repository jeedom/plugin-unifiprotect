<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Minimal client for the official local UniFi Protect Integration API.
 *
 * API documentation: https://developer.ui.com/protect
 */
class unifiprotectapi {
    private $api_key = '';
    private $baseurl = '';
    private $is_logged_in = false;
    private $ssl_verify_peer = false;
    private $ssl_verify_host = false;
    private $request_timeout = 30;
    private $connect_timeout = 10;
    private $last_error_message = '';
    private $last_http_code = 0;

    public function __construct($api_key, $baseurl, $ssl_verify = false) {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The PHP curl extension is required');
        }

        $this->api_key = trim((string) $api_key);
        $this->baseurl = rtrim(trim((string) $baseurl), '/');

        if ($this->api_key === '') {
            throw new InvalidArgumentException('The UniFi Protect API key is missing');
        }
        if (!filter_var($this->baseurl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('The UniFi Protect controller URL is invalid');
        }

        if ($ssl_verify === true) {
            $this->ssl_verify_peer = true;
            $this->ssl_verify_host = 2;
        }
    }

    /**
     * Validate the API key and the Protect Integration API availability.
     *
     * @return bool|int true on success, otherwise the HTTP status or false
     */
    public function login() {
        if ($this->is_logged_in) {
            return true;
        }

        $meta = $this->request_json('/meta/info');
        if ($meta === false) {
            return $this->last_http_code > 0 ? $this->last_http_code : false;
        }
        if (!isset($meta['applicationVersion']) || !is_string($meta['applicationVersion'])) {
            $this->set_schema_error('/meta/info', 'applicationVersion');
            return false;
        }

        $this->is_logged_in = true;
        return true;
    }

    public function logout() {
        $this->is_logged_in = false;
        return true;
    }

    /**
     * Aggregate the three official resources used by the plugin.
     *
     * @return array|false
     */
    public function get_server_info() {
        if (!$this->is_logged_in) {
            $login = $this->login();
            if ($login !== true) {
                return false;
            }
        }

        $nvr = $this->get_nvr();
        if ($nvr === false) {
            return false;
        }
        $cameras = $this->get_cameras();
        if ($cameras === false) {
            return false;
        }
        $chimes = $this->get_chimes();
        if ($chimes === false) {
            return false;
        }

        return array(
            'nvr' => $nvr,
            'cameras' => $cameras,
            'chimes' => $chimes,
        );
    }

    /** @return array|false */
    public function get_nvr() {
        $nvr = $this->request_json('/nvrs');
        if ($nvr === false) {
            return false;
        }
        if (!$this->is_valid_nvr($nvr)) {
            $this->set_schema_error('/nvrs', 'string id, string modelKey, string|null name');
            return false;
        }

        $nvr['name'] = $this->device_name($nvr, 'UniFi Protect');
        $nvr['type'] = 'nvr';
        $nvr['state'] = 1;
        return $nvr;
    }

    /** @return array|false */
    public function get_cameras() {
        $cameras = $this->request_json('/cameras');
        if ($cameras === false) {
            return false;
        }
        if (!$this->is_list($cameras)) {
            $this->set_schema_error('/cameras', 'array');
            return false;
        }

        foreach ($cameras as $index => &$camera) {
            if (!$this->is_valid_connected_device($camera)) {
                $this->set_schema_error('/cameras[' . $index . ']', 'string id, modelKey, mac, valid state and string|null name');
                return false;
            }
            $camera['name'] = $this->device_name($camera, 'Camera');
            $camera['type'] = 'camera';
            $camera['isConnected'] = $camera['state'] === 'CONNECTED';
        }
        unset($camera);

        return $cameras;
    }

    /** @return array|false */
    public function get_chimes() {
        $chimes = $this->request_json('/chimes');
        if ($chimes === false) {
            return false;
        }
        if (!$this->is_list($chimes)) {
            $this->set_schema_error('/chimes', 'array');
            return false;
        }

        foreach ($chimes as $index => &$chime) {
            if (!$this->is_valid_connected_device($chime)) {
                $this->set_schema_error('/chimes[' . $index . ']', 'string id, modelKey, mac, valid state and string|null name');
                return false;
            }
            $chime['name'] = $this->device_name($chime, 'Chime');
            $chime['type'] = 'chime';
            $chime['isConnected'] = $chime['state'] === 'CONNECTED';
        }
        unset($chime);

        return $chimes;
    }

    /** @return string|false JPEG payload */
    public function get_snapshot($camera_id) {
        $camera_id = trim((string) $camera_id);
        if ($camera_id === '') {
            $this->last_error_message = 'The camera id is missing';
            return false;
        }

        return $this->request_binary('/cameras/' . rawurlencode($camera_id) . '/snapshot?highQuality=true', 'image/jpeg');
    }

    public function get_last_error_message() {
        return $this->last_error_message;
    }

    public function get_last_http_code() {
        return $this->last_http_code;
    }

    /** @return array|false */
    protected function request_json($path) {
        $response = $this->request($path, 'application/json');
        if ($response === false) {
            return false;
        }

        $content_type = strtolower((string) $response['content_type']);
        if (strpos($content_type, 'application/json') !== 0) {
            $this->last_error_message = 'Unexpected content type returned by the official UniFi Protect API for ' . $path . ': ' . $content_type;
            return false;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error_message = 'Invalid JSON returned by the official UniFi Protect API for ' . $path;
            return false;
        }
        return $decoded;
    }

    /** @return string|false */
    protected function request_binary($path, $expected_content_type) {
        $response = $this->request($path, $expected_content_type);
        if ($response === false) {
            return false;
        }

        $content_type = strtolower((string) $response['content_type']);
        if (strpos($content_type, strtolower($expected_content_type)) !== 0) {
            $this->last_error_message = 'Unexpected content type returned by the official UniFi Protect API for ' . $path . ': ' . $content_type;
            return false;
        }
        if ($response['body'] === '') {
            $this->last_error_message = 'Empty response returned by the official UniFi Protect API for ' . $path;
            return false;
        }
        return $response['body'];
    }

    /** @return array|false Array containing body and content_type */
    protected function request($path, $accept) {
        $this->last_error_message = '';
        $this->last_http_code = 0;

        $curl = curl_init();
        if ($curl === false) {
            $this->last_error_message = 'Unable to initialize cURL';
            return false;
        }

        $url = $this->baseurl . '/proxy/protect/integration/v1' . $path;
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
            CURLOPT_TIMEOUT => $this->request_timeout,
            CURLOPT_SSL_VERIFYPEER => $this->ssl_verify_peer,
            CURLOPT_SSL_VERIFYHOST => $this->ssl_verify_host,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'Accept: ' . $accept,
                'X-API-Key: ' . $this->api_key,
            ),
        ));

        $body = curl_exec($curl);
        $this->last_http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $content_type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

        if ($body === false) {
            $this->last_error_message = 'cURL error: ' . curl_error($curl);
            return false;
        }

        if ($this->last_http_code < 200 || $this->last_http_code >= 300) {
            $this->last_error_message = $this->http_error_message($this->last_http_code, $body);
            return false;
        }

        return array('body' => $body, 'content_type' => $content_type);
    }

    private function http_error_message($http_code, $body) {
        $message = 'HTTP ' . $http_code . ' returned by the official UniFi Protect API';
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            foreach (array('message', 'error', 'detail') as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key]) && $decoded[$key] !== '') {
                    return $message . ': ' . $decoded[$key];
                }
            }
        }
        return $message;
    }

    private function set_schema_error($path, $expected) {
        $this->last_error_message = 'Unexpected response schema for ' . $path . ' (expected ' . $expected . ')';
    }

    private function has_fields($data, $fields) {
        if (!is_array($data)) {
            return false;
        }
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                return false;
            }
        }
        return true;
    }

    private function is_valid_nvr($nvr) {
        return $this->has_fields($nvr, array('id', 'modelKey', 'name'))
            && is_string($nvr['id'])
            && $nvr['id'] !== ''
            && is_string($nvr['modelKey'])
            && $nvr['modelKey'] !== ''
            && ($nvr['name'] === null || is_string($nvr['name']));
    }

    private function is_valid_connected_device($device) {
        return $this->has_fields($device, array('id', 'modelKey', 'state', 'name', 'mac'))
            && is_string($device['id'])
            && $device['id'] !== ''
            && is_string($device['modelKey'])
            && $device['modelKey'] !== ''
            && is_string($device['mac'])
            && $device['mac'] !== ''
            && is_string($device['state'])
            && in_array($device['state'], array('CONNECTED', 'CONNECTING', 'DISCONNECTED'), true)
            && ($device['name'] === null || is_string($device['name']));
    }

    private function is_list($value) {
        if (!is_array($value)) {
            return false;
        }
        return count($value) === 0 || array_keys($value) === range(0, count($value) - 1);
    }

    private function device_name($device, $fallback) {
        if (isset($device['name']) && is_string($device['name']) && trim($device['name']) !== '') {
            return $device['name'];
        }
        return $fallback . ' ' . $device['id'];
    }
}
