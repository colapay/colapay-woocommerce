<?php

class Colapay_OAuthAuthentication extends Colapay_Authentication {

    private $_token;

    public function __construct( $token ) {
        $this->_token = $token;
    }

    public function get_data() {
        $data = new stdClass();
        $data->token = $this->_token;
        return $data;
    }
}

