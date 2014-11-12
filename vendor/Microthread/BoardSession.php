<?php
/**
 * Board session handler using database (Model) to store session data
 *
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */
 
namespace Microthread;

class BoardSession implements \SessionHandlerInterface {
	
	const HASH_ALGO			= 'tiger160,4';
	const HASH_CHAR_BITS		= 5;
	const SESSION_COOKIE_LIFETIME	= 3200;
	const SESSION_LIFETIME		= 1440;
	const SESSION_COOKIES_ONLY	= true;
	const SESSION_HTTP_ONLY		= true;
	const SESSION_HTTPS_COOKIE	= false;
	
	public function __construct() {
		ini_set( 'session.gc_maxlifetime', self::SESSION_LIFETIME );
		ini_set( 'session.gc_probability', 1 );
		ini_set( 'session.gc_divisor', 10 );
		
		session_set_save_handler( $this, true );
		register_shutdown_function( 'session_write_close' );
		self::setCookieParams();
	}
	
	public function regen( $name ) {
		session_regenerate_id( true );
	}
	
	public function open( $savePath, $sessionName ) {
		return true;
	}
	
	public function read( $id ) {
		$session = Models\Session::find( 
				array( 
					'id' => $id, 
					'data' => true 
				)
			);
		
		/**
		 * No existing session? Create it.
		 */
		if ( !isset( $session->id ) ) {
			$session = new Models\Session();
		}
		
		/**
		 * Return decrypted session data (or null)
		 */
		return $session->decrypted;
	}
	
	public function write( $id, $data ) {
		Queue::register(
			array( &$this, "save" ), 
			$id, $data
		);
	}
	
	public function destroy( $id ) {
		Queue::register( 
			array( &$this, "Microthread\Models\Session::delete" ), 
			array( 'id' => $id )
		);
	}
	
	public function gc( $exp ) {
		Queue::register(
			array( &$this, "Microthread\Models\Session::gc" ), 
			$exp
		);
	}
	
	public function close() {
		return true;
	}
	
	public function save( $id, $data ) {
		$session = new Models\Session();
		
		$session->id	= $id;
		$session->data	= $data;
		$session->save();
	}
	
	protected static function setCookieParams() {
		$cparams = session_get_cookie_params();
		session_set_cookie_params(
			self::SESSION_COOKIE_LIFETIME, 
			$cparams['path'],
			$cparams['domain'],
			self::SESSION_HTTPS_COOKIE,
			self::SESSION_HTTP_ONLY
		);
	}
}
