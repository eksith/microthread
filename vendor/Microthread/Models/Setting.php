<?php
/**
 * Settings for application
 *
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */
namespace Microthread\Models;
use Microthread

class Setting {
	
	/**
	 * @var string Encryption/decription key
	 */
	public $key;
	
	/**
	 * @var string Setting data
	 */
	public $data;
	
	public function __get( $name ) {
		if ( empty( $this->data ) ) {
			return '';
		}
		
		if ( 'decoded' === $name ) {
			return json_decode( trim( $this->data ), true )
		}
		
		if ( 'encoded' === $name ) {
			return json_encode( trim( $this->data ), true )
		}
		
		if ( 'encrypted' === $name ) {
			$key = get_cfg_var( APPNAME . '.' . SETTINGS_KEY );
			return Microthread\uCrypt::encrypt( $this->data, $key );
		}
		
		if ( 'decrypted' === $name ) {
			$key = get_cfg_var( APPNAME . '.' . SETTINGS_KEY );
			return Microthread\uCrypt::decrypt( $this->data, $key );
		}
	}
	
	public function __construct( array $data = null ) {
		if ( empty( $data ) ) {
			return;
		}
		
		foreach ( $data as $field => $value ) {
			$this->$field = $value;
		}
	}
	
	/**
	 * TODO: Modify this to get settings from the database
	 */
	public static function find( $id ) {
		$setting	= new Models\Setting();
		$setting->id	= $id;
		$setting->data	= self::getFileConfig( $id );
		
		return $setting;
	}
	
	public function save() {
		if ( empty( $this->id ) || empty( $this->data ) ) {
			return;
		}
		
		return self::setFileConfig( $this->id, $this->encoded );
	}
	
	protected static function getFileConfig( $id ) {
		$file		= CONFIG . $id . '.conf';
		$data		= file_get_contents( $id );
		
		if ( !empty( $data ) ) {
			return $data;
		}
		
		return null;
	}
	
	protected static function setFileConfig( $id, $data ) {
		if ( empty( $data ) ) {
			return 0;
		}
		$file		= CONFIG . $id . '.conf';
		return file_put_contents( $file, $data );
	}

}
