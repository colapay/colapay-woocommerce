<?php

class Colapay_KeySecretAuthentication extends Colapay_Authentication {

    private $_api_key;
    private $_api_secret;

    public function __construct( $api_key, $api_secret ) {
        $this->_api_key = $api_key;
        $this->_api_secret = $api_secret;
    }

    public function get_data() {
        $data = new stdClass();
        $data->api_key = $this->_api_key;
        $data->api_secret = $this->_api_secret;
        return $data;
    }
}

