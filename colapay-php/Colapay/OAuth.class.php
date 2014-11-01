<?php

/**
 * Colapay OAuth interface
 *
 * implement the 'authorization_code' grant type, which is one of four grant types
 * defined in OAuth 2.0
 * ref. http://tools.ietf.org/html/rfc6749
 */
class Colapay_OAuth {

    private $_client_id;
    private $_client_secret;
    private $_redirect_uri;

    public function __construct( $client_id, $client_secret, $redirect_uri ) {
        $this->_client_id = $client_id;
        $this->_client_secret = $client_secret;
        $this->_redirect_uri = $redirect_uri;
    }

    public function create_authorize_url( $scope ) {
        $url = "https://colapay.com/oauth/authorize?response_type=code" .
            "&client_id=" . urlencode( $this->_client_id ) .
            "&redirect_uri=" . urlencode( $this->_redirect_uri ) .
            "&scope=" . urlencode( $scope );

        foreach ( func_get_args() as $key => $scope ) {
            if ( 0 == $key ) {
                // First scope was already appended
            } else {
                $url .= "+" . urlencode( $scope );
            }
        }

        return $url;
    }

    public function refresh_token( $old_token ) {
        return $this->get_token( $old_token["refresh_token"], "refresh_token" );
    }

    public function get_token( $code, $grant_type = 'authorization_code' ) {
        $post_fields["grant_type"] = $grant_type;
        $post_fields["redirect_uri"] = $this->_redirect_uri;
        $post_fields["client_id"] = $this->_client_id;
        $post_fields["client_secret"] = $this->_client_secret;

        if ( "refresh_token" === $grant_type ) {
            $post_fields["refresh_token"] = $code;
        } else {
            $post_fields["code"] = $code;
        }

        $curl = curl_init();
        curl_setopt( $curl, CURLOPT_POST, 1 );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $post_fields ) );
        curl_setopt( $curl, CURLOPT_URL, 'https://colapay.com/oauth/token' );
        curl_setopt( $curl, CURLOPT_CAINFO, dirname( __FILE__ ) . '/ca-colapay.crt' );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'User-Agent: ColapayPHP/v1' ) );

        $response = curl_exec( $curl );
        $status_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

        if ( false === $response ) {
            $error = curl_errno( $curl );
            $message = curl_error( $curl );
            curl_close( $curl );
            throw new Colapay_Exception( "Could not get token - network error " . $error . " (" . $message . ")" );
        }
        if ( 200 !== $status_code ) {
            throw new Colapay_Exception( "Could not get token - code " . $status_code, $status_code, $response );
        }
        curl_close( $curl );

        try {
            $json = json_decode( $response );
        } catch ( Exception $e ) {
            throw new Colapay_Exception( "Could not get token - JSON error", $status_code, $response );
        }

        return array(
            "access_token" => $json->access_token,
            "refresh_token" => $json->refresh_token,
            "expire_time" => time() + 7200 );
    }
}

