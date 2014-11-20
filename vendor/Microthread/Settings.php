<?php
/**
 * Saving, creating, updating application settings
 *
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */

namespace Microthread;
use Microthread\Models;

class Settings {
	
	private static $loaded = array();
	
	public static function getConfig( $id, $decrypt = false ) {
		if ( in_array( $id , self::$loaded )  ) {
			return self::$loaded[$id];
		}
		
		$setting		= Models\Setting::find( $id );
		if ( $decrypt ) {
			self::$loaded[$id]		= $setting->decrypted;
		} else {
			self::$loaded[$id]		= $setting->decoded;
		}
		return self::$loaded[$id];
	}
	
	public static function setConfig( $id, $data, $encrypt = false ) {
		$setting		= new Models\Setting();
		$setting->id		= $id;
		
		if ( in_array( $id , self::$loaded )  ) {
			self::$loaded[$id]	= $data;
			if ( $encrypt ) {
				$setting->encrypted	= $data;
			} else {
				$setting->encoded	= $data;
			}
		}
		
		return $setting->save();
	}
	
	
	public static function updateFromFile( $id ) {
		$setting = Models\Setting::find( 
				array( 'id' => $id, 'data'=> true )
			);
		
		if ( !isset( $setting->id ) ) {
			$setting	= new Models\Setting();
			$setting->id	= $id;
		}
		
		$setting->data		= Models\Setting::getFileConfig( $id );
		$setting->save();
	}
}
