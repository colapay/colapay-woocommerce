<?php

class Colapay_Rpc {

    private $_requestor;
    private $_authentication;

    public function __construct( $requestor, $authentication ) {
        $this->_requestor = $requestor;
        $this->_authentication = $authentication;
    }

    // used for unit tests
    public function set_requestor( $requestor ) {
        $this->_requestor = $requestor;
    }

    public function request( $method, $url, $params ) {
        $method = strtolower( $method );

        // create query string
        if ( 'get' === $method || 'delete' === $method ) {
            // use 'PHP_QUERY_RFC3986' to encode spaces to '%20', rather than '+'
            $query_string = http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
        } elseif ( 'post' === $method || 'put' === $method ) {
            $query_string = json_encode( $params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }
        if ( Colapay::DEBUG ) {
            echo $query_string;
            echo '<br><br>';
        }

        $url = Colapay::API_PATH . $url;

        // initialize CURL
        $curl = curl_init();
        $curl_opts = array();

        // HTTP method
        if ( 'get' === $method ) {
            $curl_opts[CURLOPT_HTTPGET] = 1;
            if ( $query_string ) {
                $url .= "?" . $query_string;
            }
        } else if ( 'post' === $method ) {
            $curl_opts[CURLOPT_POST] = 1;
            $curl_opts[CURLOPT_POSTFIELDS] = $query_string;
        } else if ( 'delete' === $method ) {
            $curl_opts[CURLOPT_CUSTOMREQUEST] = "DELETE";
            if ( $query_string ) {
                $url .= "?" . $query_string;
            }
        } else if ( 'put' === $method ) {
            $curl_opts[CURLOPT_CUSTOMREQUEST] = "PUT";
            $curl_opts[CURLOPT_POSTFIELDS] = $query_string;
        } else {
            throw new Colapay_Exception( "Invalid http method - " . $method );
        }

        // headers
        $headers = array( 'User-Agent: ColapayPHP/v1', 'Content-Type: application/json; charset=utf-8' );

        $auth = $this->_authentication->get_data();

        // get the authentication class and parse its payload into the HTTP header.
        $authentication_class = get_class( $this->_authentication );
        switch ( $authentication_class ) {
            case 'Colapay_KeySecretAuthentication':
                // Use HMAC API key
                $microseconds = sprintf( '%0.0f', round( microtime( true ) * 1000000 ) );

                $data_2_hash =  $microseconds . $url;
                if ( array_key_exists( CURLOPT_POSTFIELDS, $curl_opts ) ) {
                    $data_2_hash .= $curl_opts[CURLOPT_POSTFIELDS];
                }
                $signature = hash_hmac( "sha256", $data_2_hash, $auth->api_secret );
                if ( Colapay::DEBUG ) {
                    echo $data_2_hash . '<br><br>';
                    echo mb_detect_encoding( $data_2_hash ) . '<br><br>';
                    echo $signature . '<br><br>';
                }

                $headers[] = "ACCESS_KEY: {$auth->api_key}";
                $headers[] = "ACCESS_SIGNATURE: $signature";
                $headers[] = "ACCESS_NONCE: $microseconds";
                break;

            case 'Colapay_OAuthAuthentication':
                // Use OAuth
                if ( time() > $auth->token["expire_time"] ) {
                    throw new Colapay_Exception( "The OAuth token is expired. Please use refresh_token to refresh it" );
                }

                $headers[] = 'Authorization: Bearer ' . $auth->token["access_token"];
                break;

            default:
                throw new Colapay_Exception( "Invalid authentication mode - " . $authentication_class );
                break;
        }

        if ( 'production' === Colapay::get_api_env() ) {
            $url = Colapay::API_HOST_4_PRODUCTION . $url;
        } else {
            $url = Colapay::API_HOST_4_DEVELOPMENT . $url;
        }

        // CURL options
        $curl_opts[CURLOPT_URL] = $url;
        $curl_opts[CURLOPT_HTTPHEADER] = $headers;
        //$curl_opts[CURLOPT_CAINFO] = dirname( __FILE__ ) . '/colapay.crt';
        $curl_opts[CURLOPT_RETURNTRANSFER] = true;
        $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;

        // do request
        curl_setopt_array( $curl, $curl_opts );
        $response = $this->_requestor->do_curl_request( $curl );

        // decode json response as 'object' rather than 'array'
        try {
            $json = json_decode( $response['body'] );
        } catch ( Exception $e ) {
            throw new Colapay_Exception( "Invalid response body", $response['status_code'], $response['body'] );
        }
        if ( null === $json ) {
            throw new Colapay_Exception( "Invalid response body", $response['status_code'], $response['body'] );
        }
        if ( isset( $json->error ) ) {
            throw new Colapay_Exception( $json->error, $response['status_code'], $response['body'] );
        } else if ( isset( $json->errors ) ) {
            throw new Colapay_Exception( implode( $json->errors, ', ' ), $response['status_code'], $response['body'] );
        }

        return $json;
    }
}

