<?php
class Auth {
    private $username = "morbisapi";
    private $password = "morbis1234";

    public function validateCredentials($username, $password) {
        return ($username === $this->username && $password === $this->password);
    }

    public function getAllHeaders() {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    public function getFilteredServer() {
        $filtered = array();
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0 || strpos($key, 'AUTH') !== false) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    public function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . 
                (isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : ''));
        }
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        }
        elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
        }
        else {
            $requestHeaders = $this->getAllHeaders();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        error_log("Authorization Header: " . ($headers ? $headers : 'Not found'));
        
        return $headers;
    }

    public function validateAuth() {
        $auth_header = $this->getAuthorizationHeader();
        
        if (!$auth_header) {
            return false;
        }

        if (preg_match('/Basic\s+(.*)$/i', $auth_header, $matches)) {
            $decoded = base64_decode($matches[1]);
            if (strpos($decoded, ':') === false) {
                return false;
            }
            
            list($username, $password) = explode(':', $decoded);
            error_log("Auth attempt - Username: " . $username);
            
            return $this->validateCredentials($username, $password);
        }
        
        return false;
    }
}
?> 