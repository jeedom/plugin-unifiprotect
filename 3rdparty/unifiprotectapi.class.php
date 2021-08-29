<?php

/**
 * the UniFi API client class
 *
 * This UniFi API client class is based on the work done by the following developers:
 *    domwo: http://community.ubnt.com/t5/UniFi-Wireless/little-php-class-for-unifi-api/m-p/603051
 *    fbagnol: https://github.com/fbagnol/class.unifi.php
 * and the API as published by Ubiquiti:
 *    https://www.ubnt.com/downloads/unifi/<UniFi controller version number>/unifi_sh_api
 *
 * @package UniFi_Controller_API_Client_Class
 * @author  Art of WiFi <info@artofwifi.net>
 * @version Release: 1.1.70
 * @license This class is subject to the MIT license that is bundled with this package in the file LICENSE.md
 * @example This directory in the package repository contains a collection of examples:
 *          https://github.com/Art-of-WiFi/UniFi-API-client/tree/master/examples
 */
class unifiprotectapi {
    /**
     * private and protected properties
     */
    private $class_version        = '1.1.70';
    protected $baseurl            = 'https://127.0.0.1:443';
    protected $user               = '';
    protected $password           = '';
    protected $debug              = false;
    protected $ssl_verify_peer    = false;
    protected $ssl_verify_host    = false;
    protected $is_loggedin        = false;
    protected $is_unifi_os        = false;
    protected $exec_retries       = 0;
    protected $cookies            = '';
    protected $headers            = [];
    protected $method             = 'GET';
    protected $methods_allowed    = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
    protected $connect_timeout    = 10;
    protected $last_results_raw   = null;
    protected $last_error_message = null;

    /**
     * Construct an instance of the UniFi API client class
     *
     * @param string  $user       user name to use when connecting to the UniFi controller
     * @param string  $password   password to use when connecting to the UniFi controller
     * @param string  $baseurl    optional, base URL of the UniFi controller which *must* include an 'https://' prefix,
     *                            a port suffix (e.g. :8443) is required for non-UniFi OS controllers,
     *                            do not add trailing slashes, default value is 'https://127.0.0.1:8443'
     * @param string  $site       optional, short site name to access, defaults to 'default'
     * @param string  $version    optional, the version number of the controller
     * @param bool    $ssl_verify optional, whether to validate the controller's SSL certificate or not, a value of true is
     *                            recommended for production environments to prevent potential MitM attacks, default value (false)
     *                            disables validation of the controller certificate
     */
    public function __construct($user, $password, $baseurl = '', $ssl_verify = false) {
        if (!extension_loaded('curl')) {
            trigger_error('The PHP curl extension is not loaded. Please correct this before proceeding!');
        }

        $this->user     = trim($user);
        $this->password = trim($password);

        if (!empty($baseurl)) {
            $this->check_base_url($baseurl);
            $this->baseurl = trim($baseurl);
        }

        if ((bool) $ssl_verify === true) {
            $this->ssl_verify_peer = true;
            $this->ssl_verify_host = 2;
        }
    }

    /**
     * This method is called as soon as there are no other references to the class instance
     * https://www.php.net/manual/en/language.oop5.decon.php
     *
     * NOTE: to force the class instance to log out when you're done, simply call logout()
     */
    public function __destruct() {
        /**
         * if $_SESSION['unificookie'] is set, do not logout here
         */
        if (isset($_SESSION['unificookie'])) {
            return;
        }

        /**
         * logout, if needed
         */
        if ($this->is_loggedin) {
            $this->logout();
        }
    }

    /**
     * Login to the UniFi controller
     *
     * @return bool returns true upon success
     */
    public function login() {
        /**
         * skip the login process if already logged in
         */
        if ($this->is_loggedin === true) {
            return true;
        }

        if ($this->update_unificookie()) {
            $this->is_loggedin = true;

            return true;
        }

        /**
         * check whether this is a "regular" controller or one based on UniFi OS,
         * prepare cURL and options
         */
        if (!($ch = $this->get_curl_resource())) {
            return false;
        }

        $curl_options = [
            CURLOPT_HEADER => true,
            CURLOPT_POST   => true,
            CURLOPT_NOBODY => true,
            CURLOPT_URL    => $this->baseurl . '/',
        ];

        curl_setopt_array($ch, $curl_options);

        /**
         * execute the cURL request and get the HTTP response code
         */
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        /**
         * prepare the actual login
         */
        $curl_options = [
            CURLOPT_NOBODY     => false,
            CURLOPT_POSTFIELDS => json_encode(['username' => $this->user, 'password' => $this->password]),
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'Expect:'
            ],
            CURLOPT_REFERER    => $this->baseurl . '/login',
            CURLOPT_URL        => $this->baseurl . '/api/login',
        ];

        /**
         * specific to UniFi OS-based controllers
         */
        if ($http_code === 200) {
            $this->is_unifi_os         = true;
            $curl_options[CURLOPT_URL] = $this->baseurl . '/api/auth/login';
        }

        curl_setopt_array($ch, $curl_options);

        /**
         * execute the cURL request and get the HTTP response code
         */
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        if ($this->debug) {
            print PHP_EOL . '<pre>';
            print PHP_EOL . '-----------LOGIN-------------' . PHP_EOL;
            print_r(curl_getinfo($ch));
            print PHP_EOL . '----------RESPONSE-----------' . PHP_EOL;
            print $response;
            print PHP_EOL . '-----------------------------' . PHP_EOL;
            print '</pre>' . PHP_EOL;
        }

        /**
         * based on the HTTP response code trigger an error
         */
        if ($http_code === 400 || $http_code === 401) {
            trigger_error("HTTP response status received: $http_code. Probably a controller login failure");

            return $http_code;
        }

        curl_close($ch);

        /**
         * extract the cookies
         */
        if ($http_code >= 200 && $http_code < 400) {
            return $this->is_loggedin;
        }

        return false;
    }

    /**
     * Logout from the UniFi controller
     *
     * @return bool returns true upon success
     */
    public function logout() {
        /**
         * prepare cURL and options
         */
        if (!($ch = $this->get_curl_resource())) {
            return false;
        }

        $curl_options = [
            CURLOPT_HEADER => true,
            CURLOPT_POST   => true
        ];

        /**
         * constuct HTTP request headers as required
         */
        $this->headers = [
            'content-length: 0',
            'Expect:'
        ];

        $logout_path   = '/logout';
        if ($this->is_unifi_os) {
            $logout_path = '/api/auth/logout';
            $curl_options[CURLOPT_CUSTOMREQUEST] = 'POST';

            $this->create_x_csrf_token_header();
        }

        $curl_options[CURLOPT_HTTPHEADER] = $this->headers;
        $curl_options[CURLOPT_URL]        = $this->baseurl . $logout_path;

        curl_setopt_array($ch, $curl_options);

        /**
         * execute the cURL request to logout
         */
        curl_exec($ch);

        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        $this->is_loggedin = false;
        $this->cookies     = '';

        return true;
    }

    /****************************************************************
     * Functions to access UniFi controller API routes from here:
     ****************************************************************/

    public function get_server_info() {
        return $this->fetch_results('/bootstrap');
    }

    public function get_raw_events($_start, $_end) {
        return $this->fetch_results('/events?start=' . $_start . '&end=' . $_end);
    }

    public function get_snapshot($_camera_id) {
        return $this->fetch_results('/cameras/' . $_camera_id . '/snapshot?force=true');
    }


    /****************************************************************
     * setter/getter functions from here:
     ****************************************************************/

    /**
     * Set debug mode
     *
     * @param  bool $enable true enables debug mode, false disables debug mode
     * @return bool         false when a non-boolean parameter was passed
     */
    public function set_debug($enable) {
        if ($enable === true || $enable === false) {
            $this->debug = $enable;

            return true;
        }

        trigger_error('Error: the parameter for set_debug() must be boolean');

        return false;
    }

    /**
     * Get the private property $debug
     *
     * @return bool the current boolean value for $debug
     */
    public function get_debug() {
        return $this->debug;
    }

    /**
     * Get last raw results
     *
     * @param  boolean       $return_json true returns the results in "pretty printed" json format,
     *                                    false returns PHP stdClass Object format (default)
     * @return object|string              the raw results as returned by the controller API
     */
    public function get_last_results_raw($return_json = false) {
        if (!is_null($this->last_results_raw)) {
            if ($return_json) {
                return json_encode($this->last_results_raw, JSON_PRETTY_PRINT);
            }

            return $this->last_results_raw;
        }

        return false;
    }

    /**
     * Get last error message
     *
     * @return object|bool the error message of the last method called in PHP stdClass Object format, returns false if unavailable
     */
    public function get_last_error_message() {
        if (!is_null($this->last_error_message)) {
            return $this->last_error_message;
        }

        return false;
    }

    /**
     * Get Cookie from UniFi controller (singular and plural)
     *
     * NOTES:
     * - when the results from this method are stored in $_SESSION['unificookie'], the Class initially does not
     *   log in to the controller when a subsequent request is made using a new instance. This speeds up the
     *   overall request considerably. Only when a subsequent request fails (e.g. cookies have expired) is a new login
     *   executed and the value of $_SESSION['unificookie'] updated.
     * - to force the Class instance to log out automatically upon destruct, simply call logout() or unset
     *   $_SESSION['unificookie'] at the end of your code
     *
     * @return string the UniFi controller cookie
     */
    public function get_cookie() {
        return $this->cookies;
    }

    public function get_cookies() {
        return $this->cookies;
    }

    /**
     * Get version of the Class
     *
     * @return string semver compatible version of this class
     *                https://semver.org/
     */
    public function get_class_version() {
        return $this->class_version;
    }

    /**
     * Set value for the private property $cookies
     *
     * @param string $cookies_value new value for $cookies
     */
    public function set_cookies($cookies_value) {
        $this->cookies = $cookies_value;
    }

    /**
     * Get current request method
     *
     * @return string request type
     */
    public function get_method() {
        return $this->method;
    }

    /**
     * Set request method
     *
     * @param  string $method a valid HTTP request method
     * @return bool           whether request was successful or not
     */
    public function set_method($method) {

        if (!in_array($method, $this->methods_allowed)) {
            return false;
        }

        $this->method = $method;

        return true;
    }

    /**
     * Get value for cURL option CURLOPT_SSL_VERIFYPEER
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html
     *
     * @return bool value of private property $ssl_verify_peer (cURL option CURLOPT_SSL_VERIFYPEER)
     */
    public function get_ssl_verify_peer() {
        return $this->ssl_verify_peer;
    }

    /**
     * Set value for cURL option CURLOPT_SSL_VERIFYPEER
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html
     *
     * @param int|bool $ssl_verify_peer should be 0/false or 1/true
     */
    public function set_ssl_verify_peer($ssl_verify_peer) {
        if (!in_array($ssl_verify_peer, [0, false, 1, true])) {
            return false;
        }

        $this->ssl_verify_peer = $ssl_verify_peer;

        return true;
    }

    /**
     * Get value for cURL option CURLOPT_SSL_VERIFYHOST
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html
     *
     * @return bool value of private property $ssl_verify_peer (cURL option CURLOPT_SSL_VERIFYHOST)
     */
    public function get_ssl_verify_host() {
        return $this->ssl_verify_host;
    }

    /**
     * Set value for cURL option CURLOPT_SSL_VERIFYHOST
     *
     * https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html
     *
     * @param int|bool $ssl_verify_host should be 0/false or 2
     */
    public function set_ssl_verify_host($ssl_verify_host) {
        if (!in_array($ssl_verify_host, [0, false, 2])) {
            return false;
        }

        $this->ssl_verify_host = $ssl_verify_host;

        return true;
    }

    /**
     * Is current controller UniFi OS-based
     *
     * @return bool whether current controller is UniFi OS-based
     */
    public function get_is_unifi_os() {
        return $this->is_unifi_os;
    }

    /**
     * Set value for private property $is_unifi_os
     *
     * @param  bool|int $is_unifi_os new value, must be 0, 1, true or false
     * @return bool                  whether request was successful or not
     */
    public function set_is_unifi_os($is_unifi_os) {
        if (!in_array($is_unifi_os, [0, false, 1, true])) {
            return false;
        }

        $this->is_unifi_os = $is_unifi_os;

        return true;
    }

    /**
     * Set value for the private property $connect_timeout
     *
     * @param int $timeout new value for $connect_timeout in seconds
     */
    public function set_connection_timeout($timeout) {
        $this->connect_timeout = $timeout;
    }

    /**
     * Get current value of the private property $connect_timeout
     *
     * @return int current value if $connect_timeout
     */
    public function get_connection_timeout() {
        return $this->connect_timeout;
    }

    /****************************************************************
     * private and protected functions from here:
     ****************************************************************/

    /**
     * Fetch results
     *
     * execute the cURL request and return results
     *
     * @param  string       $path           request path
     * @param  object|array $payload        optional, PHP associative array or stdClass Object, payload to pass with the request
     * @param  boolean      $boolean        optional, whether the method should return a boolean result, else return
     *                                      the "data" array
     * @param  boolean      $login_required optional, whether the method requires to be logged in or not
     * @return bool|array                   [description]
     */
    protected function fetch_results($path, $payload = null, $boolean = false, $login_required = true) {
        /**
         * guard clause to check if logged in when needed
         */
        if ($login_required && !$this->is_loggedin) {
            return false;
        }

        $this->last_results_raw = $this->exec_curl($path, $payload);
        //var_dump($this->last_results_raw);
        if (is_string($this->last_results_raw)) {
            $response = json_decode($this->last_results_raw, true);
            if (!is_array($response)) {
                return $this->last_results_raw;
            }
            return $response;
        }
        return false;
    }

    /**
     * Fetch results where output should be boolean (true/false)
     *
     * execute the cURL request and return a boolean value
     *
     * @param  string       $path           request path
     * @param  object|array $payload        optional, PHP associative array or stdClass Object, payload to pass with the request
     * @param  bool         $login_required optional, whether the method requires to be logged in or not
     * @return bool                         [description]
     */
    protected function fetch_results_boolean($path, $payload = null, $login_required = true) {
        return $this->fetch_results($path, $payload, true, $login_required);
    }

    /**
     * Capture the latest JSON error when $this->debug is true
     *
     * @return bool returns true upon success, false upon failure
     */
    protected function catch_json_last_error() {
        if ($this->debug) {
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    // JSON is valid, no error has occurred and return true early
                    return true;
                case JSON_ERROR_DEPTH:
                    $error = 'The maximum stack depth has been exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $error = 'Invalid or malformed JSON';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error = 'Control character error, possibly incorrectly encoded';
                    break;
                case JSON_ERROR_SYNTAX:
                    $error = 'Syntax error, malformed JSON';
                    break;
                case JSON_ERROR_UTF8:
                    // PHP >= 5.3.3
                    $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                case JSON_ERROR_RECURSION:
                    // PHP >= 5.5.0
                    $error = 'One or more recursive references in the value to be encoded';
                    break;
                case JSON_ERROR_INF_OR_NAN:
                    // PHP >= 5.5.0
                    $error = 'One or more NAN or INF values in the value to be encoded';
                    break;
                case JSON_ERROR_UNSUPPORTED_TYPE:
                    $error = 'A value of a type that cannot be encoded was given';
                    break;
                case JSON_ERROR_INVALID_PROPERTY_NAME:
                    // PHP >= 7.0.0
                    $error = 'A property name that cannot be encoded was given';
                    break;
                case JSON_ERROR_UTF16:
                    // PHP >= 7.0.0
                    $error = 'Malformed UTF-16 characters, possibly incorrectly encoded';
                    break;
                default:
                    // an unknown error occurred
                    $error = 'Unknown JSON error occurred';
                    break;
            }

            trigger_error('JSON decode error: ' . $error);

            return false;
        }

        return true;
    }

    /**
     * Validate the submitted base URL
     *
     * @param  string $baseurl the base URL to validate
     * @return bool            true if base URL is a valid URL, else returns false
     */
    protected function check_base_url($baseurl) {
        if (!filter_var($baseurl, FILTER_VALIDATE_URL) || substr($baseurl, -1) === '/') {
            trigger_error('The URL provided is incomplete, invalid or ends with a / character!');

            return false;
        }

        return true;
    }

    /**
     * Update the unificookie if sessions are enabled
     *
     * @return bool true when unificookie was updated, else returns false
     */
    protected function update_unificookie() {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['unificookie']) && !empty($_SESSION['unificookie'])) {
            $this->cookies = $_SESSION['unificookie'];

            /**
             * if the cookie contains a JWT this is a UniFi OS controller
             */
            if (strpos($this->cookies, 'TOKEN') !== false) {
                $this->is_unifi_os = true;
            }

            return true;
        }

        return false;
    }

    /**
     * Add a cURL header containing the CSRF token from the TOKEN in our Cookie string
     *
     * @return bool true upon success or false when unable to extract the CSRF token
     */
    protected function create_x_csrf_token_header() {
        if (!empty($this->cookies) && strpos($this->cookies, 'TOKEN') !== false) {
            $cookie_bits = explode('=', $this->cookies);
            if (empty($cookie_bits) || !array_key_exists(1, $cookie_bits)) {
                return;
            }

            $jwt_components = explode('.', $cookie_bits[1]);
            if (empty($jwt_components) || !array_key_exists(1, $jwt_components)) {
                return;
            }

            $this->headers[] = 'x-csrf-token: ' . json_decode(base64_decode($jwt_components[1]))->csrfToken;
        }
    }

    /**
     * Callback function for cURL to extract and store cookies as needed
     *
     * @param  object|resource $ch          the cURL instance
     * @param  int             $header_line the response header line number
     * @return int                          length of the header line
     */
    protected function response_header_callback($ch, $header_line) {
        if (strpos($header_line, 'unifises') !== false || strpos($header_line, 'TOKEN') !== false) {
            $cookie = trim(str_replace(['set-cookie: ', 'Set-Cookie: '], '', $header_line));

            if (!empty($cookie)) {
                $cookie_crumbs = explode(';', $cookie);
                foreach ($cookie_crumbs as $cookie_crumb) {
                    if (strpos($cookie_crumb, 'unifises') !== false) {
                        $this->cookies     = $cookie_crumb;
                        $this->is_loggedin = true;
                        $this->is_unifi_os = false;

                        break;
                    }

                    if (strpos($cookie_crumb, 'TOKEN') !== false) {
                        $this->cookies     = $cookie_crumb;
                        $this->is_loggedin = true;
                        $this->is_unifi_os = true;

                        break;
                    }
                }
            }
        }

        return strlen($header_line);
    }

    /**
     * Execute the cURL request
     *
     * @param  string            $path    path for the request
     * @param  object|array      $payload optional, payload to pass with the request
     * @return bool|array|string          response returned by the controller API, false upon error
     */
    protected function exec_curl($path, $payload = null) {
        if (!in_array($this->method, $this->methods_allowed)) {
            trigger_error('an invalid HTTP request type was used: ' . $this->method);

            return false;
        }

        if (!($ch = $this->get_curl_resource())) {
            trigger_error('get_curl_resource() did not return a resource');

            return false;
        }

        $this->headers = [];
        $url = $this->baseurl . $path;

        if ($this->is_unifi_os) {
            $url = $this->baseurl . '/proxy/protect/api' . $path;
        }

        $curl_options = [
            CURLOPT_URL => $url
        ];
        /**
         * when a payload is passed
         */
        $json_payload  = '';
        if (!empty($payload)) {
            $json_payload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $curl_options[CURLOPT_POSTFIELDS] = $json_payload;

            /**
             * add empty Expect header to prevent cURL from injecting an "Expect: 100-continue" header
             */
            $this->headers = [
                'content-type: application/json',
                'Expect:'
            ];

            /**
             * should not use GET (the default request type) or DELETE when passing a payload,
             * switch to POST instead
             */
            if ($this->method === 'GET' || $this->method === 'DELETE') {
                $this->method = 'POST';
            }
        }

        switch ($this->method) {
            case 'POST':
                $curl_options[CURLOPT_POST] = true;
                break;
            case 'DELETE':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'PUT':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                break;
            case 'PATCH':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                break;
        }

        if ($this->is_unifi_os && $this->method !== 'GET') {
            $this->create_x_csrf_token_header();
        }

        if (count($this->headers) > 0) {
            $curl_options[CURLOPT_HTTPHEADER] = $this->headers;
        }

        curl_setopt_array($ch, $curl_options);

        /**
         * execute the cURL request
         */
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            trigger_error('cURL error: ' . curl_error($ch));
        }

        /**
         * fetch the HTTP response code
         */
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        /**
         * an HTTP response code 401 (Unauthorized) indicates the Cookie/Token has expired in which case
         * re-login is required
         */
        if ($http_code == 401) {
            if ($this->debug) {
                error_log(__FUNCTION__ . ': needed to reconnect to UniFi controller');
            }

            if ($this->exec_retries == 0) {
                /**
                 * explicitly clear the expired Cookie/Token, update other properties and log out before logging in again
                 */
                if (isset($_SESSION['unificookie'])) {
                    $_SESSION['unificookie'] = '';
                }

                $this->is_loggedin = false;
                $this->cookies     = '';
                $this->exec_retries++;
                curl_close($ch);

                /**
                 * then login again
                 */
                $this->login();

                /**
                 * when re-login was successful, simply execute the same cURL request again
                 */
                if ($this->is_loggedin) {
                    if ($this->debug) {
                        error_log(__FUNCTION__ . ': re-logged in, calling exec_curl again');
                    }

                    return $this->exec_curl($path, $payload);
                }

                if ($this->debug) {
                    error_log(__FUNCTION__ . ': re-login failed');
                }
            }

            return false;
        }

        if ($this->debug) {
            print PHP_EOL . '<pre>';
            print PHP_EOL . '---------cURL INFO-----------' . PHP_EOL;
            print_r(curl_getinfo($ch));
            print PHP_EOL . '-------URL & PAYLOAD---------' . PHP_EOL;
            print $url . PHP_EOL;
            if (empty($json_payload)) {
                print 'empty payload';
            }

            print $json_payload;
            print PHP_EOL . '----------RESPONSE-----------' . PHP_EOL;
            print $response;
            print PHP_EOL . '-----------------------------' . PHP_EOL;
            print '</pre>' . PHP_EOL;
        }

        curl_close($ch);

        /**
         * set method back to default value, just in case
         */
        $this->method = 'GET';

        return $response;
    }

    /**
     * Create a new cURL resource and return a cURL handle
     *
     * @return object|bool|resource cURL handle upon success, false upon failure
     */
    protected function get_curl_resource() {
        $ch = curl_init();
        if (is_object($ch) || is_resource($ch)) {
            $curl_options = [
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_SSL_VERIFYPEER => $this->ssl_verify_peer,
                CURLOPT_SSL_VERIFYHOST => $this->ssl_verify_host,
                CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_HEADERFUNCTION => [$this, 'response_header_callback'],
            ];

            if ($this->debug) {
                $curl_options[CURLOPT_VERBOSE] = true;
            }

            if (!empty($this->cookies)) {
                $curl_options[CURLOPT_COOKIESESSION] = true;
                $curl_options[CURLOPT_COOKIE]        = $this->cookies;
            }

            curl_setopt_array($ch, $curl_options);

            return $ch;
        }

        return false;
    }
}
