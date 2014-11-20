<?php
// Testing page. Thar be dragons!

ini_set( 'display_errors', '1' ); // Remove this in production
set_time_limit( 3600 );

$initialMemory = memory_get_usage();

/**
 * Execution time
 */
define( 'START',	-microtime( true ) ); // Use with + microtime(true);

/**
 * Application base path.
 */
define( 'PATH',		realpath( dirname( __FILE__ ) ) . '/' );

function formatBytes( $bytes, $precision = 2 ) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB');
	
	$bytes = max( $bytes, 0 );
	$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
	$pow = min( $pow, count( $units ) - 1 );
	
	return round( $bytes, $precision ) . ' ' . $units[$pow];
}

$initialMemory = 'Initial ' . formatBytes( $initialMemory ) . " bytes";

/**
 * Microthread core configuration
 */
require( PATH . 'vendor/Microthread/bootstrap.php' );


$handler = new Microthread\BoardSession();
session_set_save_handler( $handler, true );
session_start();
