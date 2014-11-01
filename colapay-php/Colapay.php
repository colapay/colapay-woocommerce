<?php

/**
 * SPL autoloader.
 *
 * @param string $class_name the name of class to load
 */
function Colapay_autoload( $class_name ) {
    $dirs = array(
            __DIR__ . '/Colapay/'
            );
    // support case insensitivity
    $class_names = array( $class_name, strtolower( $class_name ), strtoupper( $class_name ) );
    foreach( $class_names as $class_name ) {
        foreach( $dirs as $dir ) {
            $file = $dir . $class_name . '.class.php';
            if ( file_exists( $file ) ) {
                include_once( $file );
                return;
            }
            $file = str_replace( 'Colapay_', '', $file );
            if ( file_exists( $file ) ) {
                include_once( $file );
                return;
            }
        }
    }
    //die("[ERROR]: class [$class_name] cannot be auto loaded!");
}

if ( version_compare( PHP_VERSION, '5.3.0', '>=' ) ) {
    spl_autoload_register( 'Colapay_autoload', TRUE, TRUE );
} else {
    spl_autoload_register( 'Colapay_autoload' );
}

if ( ! function_exists( 'curl_init' ) ) {
    throw new Exception( 'The Colapay client library requires the CURL PHP extension.' );
}

mb_internal_encoding( 'UTF-8' );

