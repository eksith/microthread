<?php

/**
 * Packages location. Assuming you have a folder called /vendor in your web root.
 */
define( 'PKGS',		PATH . 'vendor/' );


define( 'ARCHIVE',	PATH . 'data/archive/' );
define( 'CONFIG',	PATH . 'data/config/' );
define( 'CACHE_TIME', 	3600 );

/**
 * Data parameters
 */
define( 'CONN',			'sqlite:data\\store.sqlite' );


/**
 * Autoloader
 */
set_include_path( get_include_path() . PATH_SEPARATOR . PKGS );
spl_autoload_extensions( '.php' );
spl_autoload_register( function( $class ) {
	spl_autoload( str_replace( "\\", "/", $class ) );
});
