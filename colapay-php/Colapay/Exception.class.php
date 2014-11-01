<?php

class Colapay_Exception extends Exception {

    public function __construct( $message, $http_code = null, $response = null ) {
        parent::__construct( $message );
        $this->http_code = $http_code;
        $this->response = $response;
    }

    public function get_http_code() {
        return $this->http_code;
    }

    public function get_response() {
        return $this->response;
    }
}

