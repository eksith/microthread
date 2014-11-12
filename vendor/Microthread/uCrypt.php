<?php
/**
 * Encryption/Decryption related functions.
 *
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.5
 */
 
namespace Microthread;

final class uCrypt {
	
	public static function IV( $size, $ssl = false ) {
		if ( $ssl && function_exists( 'openssl_random_pseudo_bytes' ) ) {
			return openssl_random_pseudo_bytes( $size, $ssl );
		}
		
		return mcrypt_create_iv( $size, MCRYPT_DEV_RANDOM );
	}
	
	public static function encrypt( $data, $key ) {
		if ( null === $data ) { return null ; }
		return self::encryption( $data, $key, 'encrypt' );
	}
	
	public static function decrypt( $data, $key ) {
		if ( null === $data ) { return null ; }
		return self::encryption( $data, $key, 'decrypt' );
	}
	
	private static function encryption( $str, $key, $mode = 'encrypt' ) {
		$td	= mcrypt_module_open( MCRYPT_RIJNDAEL_256, '', MCRYPT_MODE_CBC, '');
		$ivs	= mcrypt_enc_get_iv_size( $td );
		$bsize	= mcrypt_enc_get_block_size( $td );
		$ksize	= mcrypt_enc_get_key_size( $td );
		$key	= substr( hash( 'sha256', $key ), 0, $ksize );
		
		try {
			if ( 'encrypt' === $mode ) {
				$iv	= self::IV( $ivs );
			} else {
				$str	= base64_decode( $str );
				$iv	= mb_substr( $str, 0, $ivs );
				$str	= mb_substr( $str, mb_strlen( $iv ) );
			}
			
			mcrypt_generic_init( $td, $key, $iv );
			
			if ( 'encrypt' === $mode ) {
				self::_pad( $str, $bsize );
				
				$str = mcrypt_generic( $td, $str );
				$out = base64_encode( $iv . $str );
			} else {
				$str = mdecrypt_generic( $td, $str );
				self::_unpad( $str, $bsize );
				
				$out = $str;
			}
		} catch( \Exception $e ) {
			$out = null;
		}
		
		mcrypt_generic_deinit( $td );
		mcrypt_module_close( $td );
		
		return $out;
	}
	
	private static function _pad( &$str, $bsize ) {
		$pad = $bsize - ( mb_strlen( $str ) % $bsize );
		$str .= str_repeat( chr( $pad ), $pad );
	}
	
	private static function _unpad( &$str, $bsize ) {
		$len = mb_strlen( $str );
		$pad = ord( $str[$len - 1] );
		
		if ($pad && $pad < $bsize) {
			$pm = preg_match( '/' . chr( $pad ) . '{' . $pad . '}$/', $str );
			if ( $pm ) {
				$str = mb_substr( $str, 0, $len - $pad );
			}
		}
	}
}
