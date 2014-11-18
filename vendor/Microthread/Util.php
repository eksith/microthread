<?php
/**
 * Common utilities and helpers
 *
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */
namespace Microthread;

class Util {
	/**
	 * Headers and signatures
	 */
	private static $_sig		= null;
	private static $_rawSig		= null;
	

	public static function getSig() {
		if ( null === self::$_sig ) ) {
			self::setSig();
		}
		return self::$_sig;
	}

	public static function getRawSig() {
		if ( null === self::$_rawSig ) ) {
			setSig();
		}
		return self::$_rawSig;
	}
	
	/**
	 * Helpers
	 */
	public static function encField( $field ) {
		return hash( 'tiger160,4', $field . session_id() . self::getSig() );
	}
	
	public static function fromField( $arr, $field, $default, $enc = false ) {
		if ( $enc ) {
			$field = self::encField( $field );
		}
		if ( 'post' === $arr ) {
			return self::fromArray( $_POST, $field, $default );
		} 
		return self::fromArray( $_GET, $field, $default );
	}

	public static function fromArray( $arr, $field, $default ) {
		return isset( $arr[$field] )? $arr[$field] : $default;
	}
	
	/**
	 * Formatting
	 */
	public static function detectEncoding( $str ) {
		$enc = 'UTF-8,ISO-8859-1,SJIS,EUC-JP';
		return mb_detect_encoding( $str, $enc );
	}
	
	public static function fileSig( $path, $verify = null ) {
		if ( null === $verify ) {
			return sha1_file( $path );
		}
		
		return $verify === sha1_file( $path );
	}
	
	public static archivePage() {
		$path	= $_SERVER['REQUEST_URI'];
		$out	= self::pullOutput();
		Cache::put( $path, $out, false );
	}
	
	public static function headers() {
		if ( function_exists( 'getallheaders' ) ) {
			return getallheaders();
		}
		
		$headers = array();
		
		foreach( $_SERVER as $k => $v ) {
			if ( 0 === strpos( $k, 'HTTP_' ) ) {
				/**
				 * Remove HTTP_ and turn turn '_' to spaces
				 */
				$hd	= str_replace( '_', ' ', substr( $k, 5 ) );
				
				/**
				 * E.G. ACCEPT LANGUAGE to Accept-Language
				 */
				$uw	= ucwords( strtolower( $hd ) );
				$uw	= str_replace( ' ', '-', $uw );
				
				$headers[ $uw ] = $v;
			}
		}
		
		return $headers;
	}
	
	private static function setSig() {
		$str		= '';
		$headers	= self::headers();
		
		foreach ( $headers as $h => $v ) {
			switch( $h ) {
				case 'Accept-Charset':
				case 'Accept-Language':
				case 'Accept-Encoding':
				case 'Proxy-Authorization':
				case 'Authorization':
				case 'Max-Forwards':
				case 'Connection':
				case 'From':
				case 'Host':
				case 'DNT':
				case 'TE':
				case 'X-Requested-With':
				case 'X-Forwarded-For':
				case 'X-ATT-DeviceId':
				case 'User-Agent':
					$str .= $v;
					break;
			}
		}
		
		$str		.= $_SERVER['SERVER_PROTOCOL'] . $_SERVER['REMOTE_ADDR'];
		$s		= hash( 'tiger160,4', $str );
		
		self::$_sig	= $s;
		self::$_rawSig	= $str;
	}
	
	/**
	 * Cut off excess content without cutting words in the middle
	 */
	public static function smartTrim( $val, $max = 100 ) {
		$val	= trim( $val );
		$len	= mb_strlen( $val );
		
		if ( $len <= $max ) {
			return $val;
		}
		
		$out	= '';
		$words	= preg_split( '/([\.\s]+)/', $val, -1, 
				PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE );
			
		for ( $i = 0; $i < count( $words ); $i++ ) {
			$w = $words[$i];
			// Add if this word's length is less than total string length
			if ( $w[1] <= $max ) {
				$out .= $w[0];
			}
		}
		
		return $out;
	}
	
	/**
	 * Formatting
	 */
	public static function detectEncoding( $str ) {
		$enc = 'UTF-8,ISO-8859-1,SJIS,EUC-JP';
		return mb_detect_encoding( $str, $enc );
	}
	
	public static pullOutput() {
		ob_start();
		$out = ob_get_contents();
		ob_end_clean();
		
		return $out;
	}
}
