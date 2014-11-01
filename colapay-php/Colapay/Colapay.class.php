<?php

class Colapay {

    const API_HOST_4_PRODUCTION = 'https://www.colapay.com';
    const API_HOST_4_DEVELOPMENT = '';
    const API_PATH = '/api/v1/';

    const DEBUG = false;

    private static $_api_env = 'production';
    private $_rpc;
    private $_authentication;


    public function __construct( $authentication_mode ) {
        if ( is_a( $authentication_mode, 'Colapay_Authentication' ) ) {
            $this->_authentication = $authentication_mode;
        } else {
            throw new Colapay_Exception( 'Could not determine API authentication mode - ' . $authentication_mode );
        }

        $this->_rpc = new Colapay_Rpc( new Colapay_Requestor(), $this->_authentication );
    }

    public static function set_api_env( $env ) {
        Colapay::$_api_env = $env;
    }

    public static function get_api_env() {
        return Colapay::$_api_env;
    }

    public static function key_secret_mode( $key, $secret ) {
        return new Colapay( new Colapay_KeySecretAuthentication( $key, $secret ) );
    }

    public static function oauth_mode( $token ) {
        return new Colapay( new Colapay_OAuthAuthentication( $token ) );
    }

    // used for unit tests
    public function set_requestor( $requestor ) {
        $this->_rpc->set_requestor( $requestor );
    }

    private function get( $path, $params = array() ) {
        return $this->_rpc->request( "GET", $path, $params );
    }

    private function post( $path, $params = array() ) {
        return $this->_rpc->request( "POST", $path, $params );
    }

    private function delete( $path, $params = array() ) {
        return $this->_rpc->request( "DELETE", $path, $params );
    }

    private function put( $path, $params = array() ) {
        return $this->_rpc->request( "PUT", $path, $params );
    }

    public function create_invoice( $name, $price, $currency, $merchant, $options = array() ) {
        // required parameters
        $params = array(
                "name" => $name,
                "price" => $price,
                "currency" => $currency,
                "merchant" => $merchant
                );
        // optional parameters
        foreach ( $options as $option => $val ) {
            $params[$option] = $val;
        }

        $res = new stdClass();
        $response = $this->post( "invoice/create", $params );
        if ( Colapay::DEBUG ) {
            var_dump( $response );
            echo '<br><br>';
        }
        if ( ! $response->success ) {
            $res->success = false;
            $res->error = $response->error;
        } else {
            $res->success = true;
            $res->invoice = $response->invoice;
//            $res->embed_html = "<div class=\"colapay-button\" invoice_id=\"" . $response->invoice->id . "\"></div><script src=\"https://colapay.com/assets/button.js\" type=\"text/javascript\"></script>";
        }

        return $res;
    }

    public function get_invoice_status( $invoice_id ) {
        return $this->get( "invoice/" . $invoice_id, array() );
    }

    public function create_item( $name, $price, $currency, $options = array() ) {
        // required parameters
        $params = array(
                "name" => $name,
                "price" => $price,
                "currency" => $currency
                );
        // optional parameters
        foreach ( $options as $option => $val ) {
            $params[$option] = $val;
        }

        $res = new stdClass();
        $response = $this->post( "item/create", $params );
        if ( Colapay::DEBUG ) {
            var_dump( $response );
            echo '<br><br>';
        }
        if ( ! $response->success ) {
            $res->success = false;
            $res->error = $response->error;
        } else {
            $res->success = true;
            $res->item = $response->item;
        }

        return $res;
    }

    public function get_user_info() {
        $res = new stdClass();
        $response = $this->get( "info", array() );
        $res->success = true;
        $res->user = $response;

        return $res;
    }
}

