<?php

/**
 * Packages location. Assuming you have a folder called /vendor in your web root.
 */
define( 'PKGS',		PATH . 'vendor/' );


/**
 * Autoloader
 */
set_include_path( get_include_path() . PATH_SEPARATOR . PKGS );
spl_autoload_extensions( '.php' );
spl_autoload_register( function( $class ) {
	spl_autoload( str_replace( "\\", "/", $class ) );
});
