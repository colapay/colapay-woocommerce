<?php

class Colapay_Requestor {

    public function do_curl_request( $curl ) {
        $response = curl_exec( $curl );

        // Check for errors
        if ( false === $response ) {
            $error = curl_errno( $curl );
            $message = curl_error( $curl );
            curl_close( $curl );
            throw new Colapay_Exception( "Network error " . $error . " (" . $message . ")" );
        }

        // Check status code
        $status_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
        curl_close( $curl );
        if ( 200 != $status_code ) {
            throw new Colapay_Exception( "Status code " . $status_code, $status_code, $response );
        }

        return array( "status_code" => $status_code, "body" => $response );
    }
}

