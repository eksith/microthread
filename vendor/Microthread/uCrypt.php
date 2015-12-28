<?php
/**
 * Encryption/Decryption related functions.
 *
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.7
 */
 
namespace Microthread;

final class uCrypt {
	
	const BLOCK_SIZE	= 32;
	const KEY_SIZE		= 32;
	const MERGE_HASH	= 'sha256';
	const OSSL_IV_SIZE	= 'aes-256-cbc';
	const PBK_DELIMETER	= '$';		// Don't use a comma
	const PBK_REGEX		= '/[^a-f0-9\$]+$/i';
	
	private static $rstate;
	
	public function encrypt( $message, $key ) {
		if ( empty( $key ) || empty( $message ) ) {
			return false;
		}
		$key	= $this->keyAdjust( $key );
		
		if ( function_exists( 'openssl_encrypt' ) ) {
			$ivs	= \openssl_cipher_iv_length( self::OSSL_IV_SIZE );
			$iv	= \openssl_random_pseudo_bytes( $ivs );
			$cipher	= 
			\openssl_encrypt(
				$message,
				self::OSSL_IV_SIZE,
				$key,
				\OPENSSL_RAW_DATA,
				$iv;
			);
			
			return base64_encode( $iv . $cipher );
			
		} elseif ( function_exists( 'mcrypt_encrypt' ) ) {
			$message= $this->pkcsPad( $message );
			$ivs	= \mcrypt_get_iv_size( \MCRYPT_RIJNDAEL_128 );
			$iv	= \mcrypt_create_iv( $ivs, \MCRYPT_DEV_URANDOM );
			$cipher	= 
			\mcrypt_encrypt( 
				\MCRYPT_RIJNDAEL_128,
				$key, 
				$message, 
				'ctr', 
				$iv 
			);
			
			return base64_encode( $iv . $cipher );
		}
		
		return false;
	}
	
	public function decrypt( $message, $key ) {
		if ( empty( $key ) || empty( $message ) ) {
			return false;
		}
		$message	= base64_decode( $message, true );
		if ( false === $message ) {
			return false;
		}
		$key		= $this->keyAdjust( $key );
		
		if ( function_exists( 'openssl_decrypt' ) ) {
			$ivs	= \openssl_cipher_iv_length( self::OSSL_IV_SIZE );
			$iv	= mb_substr( $message, 0, $ivs, '8bit' );
			$cipher	= mb_substr( $message, $ivs, null, '8bit' );
			
			return \openssl_decrypt(
				$cipher,
				self::OSSL_IV_SIZE,
				$key,
				\OPENSSL_RAW_DATA,
				$iv
			);
		} elseif ( function_exists( 'mcrypt_decrypt' ) ) {
			$ivs	= \mcrypt_get_iv_size( \MCRYPT_RIJNDAEL_128 );
			$iv	= mb_substr( $message, 0, $ivs, '8bit' );
			$cipher	= mb_substr( $message, $ivs, null, '8bit' );
			
			$message= \mcrypt_decrypt( 
				\MCRYPT_RIJNDAEL_128, 
				$key, $cipher, 
				'ctr', 
				$iv 
			);
			return $this->pkcsUnpad( $message );
		}
		
		return false;
	}
	
	private function pkcsPad( $message ) {
		$block		= \mcrypt_get_block_size( \MCRYPT_RIJNDAEL_128 );
		$pad		= $block - ( mb_strlen( $message, '8bit' ) % $block );
		$message	.= str_repeat( chr( $pad ), $pad );
		return $message;
	}
	
	private function pkcsUnpad( $message ) {
		$block	= \mcrypt_get_block_size( \MCRYPT_RIJNDAEL_128 );
		$len 	= mb_substr( $message, '8bit' );
		$pad	= ord( $message[$len-1] );
		if ($pad <= 0 || $pad > $block) {
			return false;
		}
		
		return mb_substr( $message, 0, $len - $pad, '8bit' );
	}
	
	private function keyAdust( $key ) {
		if ( mb_strlen( $key, '8bit' ) !== self::KEY_SIZE ) {
			return hash( 'sha256', $key, true );
		}
		return $key;
	}
	
	public function genPbk(
		$algo	= 'tiger160,4', 
		$txt,
		$salt	= null,
		$rounds	= 1000, 
		$kl	= 128 
	) {
		$rounds	= ( $rounds <= 0 ) ? 1000 : $rounds;
		$kl	= ( $kl <= 0 ) ? 128 : $kl;
		$salt	= empty( $salt ) ? 
			bin2hex( $this->bytes( 8, 2 ) ) : $salt;
				
		$key	= \hash_pbkdf2( $algo, $txt, $salt, $rounds, $kl );
		$out	= array(
				$algo, $txt, $salt, $rounds, $kl, $key
			);
		return base64_encode( implode( self::PBK_DELIMETER, $out ) );
	}
	
	public function verifyPbk( $txt, $hash ) {
		if ( empty( $hash) || mb_strlen( $hash, '8bit' ) > 100 ) {
			return false;
		}
		$key	= base64_decode( $hash, true );
		if ( false === $key ) {
			return false;
		}
		$k	= explode( self::PBK_DELIMETER, $key );
		
		if ( empty( $k ) || empty( $txt ) ) {
			return false;
		}
		if ( count( $k ) != 6 ) {
			return false;
		}
		if ( !in_array( $k[0], hash_algos() , true ) ) {
			return false;
		}
		
		$pbk = \hash_pbkdf2( $k[0], $txt, 
				( int ) $k[2], $k[3], $k[4] );
		
		return \hash_equals( $this->cleanPbk( $k[5] ), $pbk );
	}
	
	private function cleanPbk( $hash ) {
		return preg_replace( self::PBK_REGEX, '', $hash );
	}
	
	private function rbytes( $size ) {
		if ( isset( self::$rstate ) ) {
			self::$rstate	= 
			$this->merge ( 
				self::$rstate, 
				\random_bytes( $size )
			);
		} else {
			self::$rstate	= \random_bytes( $size );
		}
	}
	
	private function ossl( $size ) {
		$strong		= true;
		self::$rstate	= 
		$this->merge( 
			self::$rstate, 
			\openssl_random_pseudo_bytes( $size, $strong ) 
		);
	}
	
	private function mrand( $size, $src ) {
		if ( isset( self::$rstate ) ) {
			self::$rstate	= 
			$this->merge ( 
				self::$rstate, 
				\mcrypt_create_iv( $size, $src )
			);
		} else {
			self::$rstate	= 
			\mcrypt_create_iv( $size, $src );
		}
	
	}
	
	private function frand( $size, $src ) {
		if ( 
			file_exists( $src ) && 
			is_readable( $src ) 
		) {
			self::$rstate	= 
			$this->merge( 
				self::$rstate, 
				file_get_contents( $src, false, null, -1, $size ) 
			);
		}
	}
	
	
	/**
	 * mt_rand Wrapper that fixes some rapid use anomalies (PHP < 5.4)
	 *
	 * @return int Pseudo-random number (unsafe for crypto!)
	 */
	public function rnum( $min, $max ) {
		$num = 0;
		while ( $num < $min || $num > $max || null == $num ) {
			$num = mt_rand( $min, $max );
		}
		return $num;
	}
	
	// https://paragonie.com/blog/2015/07/how-safely-generate-random-strings-and-integers-in-php
	public function number( $min, $max, $level = 0 ) {
		$num = 0;
		if ( $level <= 0 ) {
			return $this->rnum( $min, $max );
		}
		if ( function_exists( 'random_int' ) ) {
			return \random_int( $min, $max );
		}
		
		$mask	= 0;
		$bits	= 0;
		$bytes	= 0;
		$shift	= 0;
		$tries	= 0;
		$range	= $max - $min;
		
		while( $range > 0 ) {
			if ( $bits % 8 === 0 ) {
				++$bytes;
			}
			++$bits;
			$range >>= 1;
			$mask	= $mask << 1 | 1;
			
		}
		
		$shift	= $min;
		
		do {
			if ( $tries > 128 ) {
				die( 'Crypto error: Random integer' );
			}
			$rnd	= $this->bytes( $bytes, $level );
			if ( $rnd === false ) {
				die( 'Crypto error: Random bytes' );
			}
			$num	= 0;
			for( $i = 0; $i < $bytes; ++$i ) {
				$num |= ord( $rnd[$i] ) << ( $i * 8 );
			}
			
			$num	&= $mask;
			$num	+= $shift;
			++$tries;
		} while ( $num < $min || $num > $max || !is_int( $num ) );
		
		return $num;
	}
	
	public function random( $level ) {
		if ( function_exists( 'random_bytes' ) ) {
			$this->rbytes( self::BLOCK_SIZE );
		} elseif ( function_exists( 'mcrypt_create_iv' ) ) {
			$this->mrand( self::BLOCK_SIZE, \MCRYPT_DEV_URANDOM );
		} else {
			$this->frand( self::BLOCK_SIZE, '/dev/urandom' );
		}
		
		if ( $level <= 0 ) {
			return self::$rstate;
		}
		
		if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$this->ossl( self::BLOCK_SIZE );
		}
		$this->frand( self::BLOCK_SIZE, '/dev/arandom' );
		
		if ( $level >= 2 ) {
			if ( function_exists( 'mcrypt_create_iv' ) ) {
				$this->mrand( self::BLOCK_SIZE, \MCRYPT_DEV_RANDOM );
			} else {
				$this->frand( self::BLOCK_SIZE, '/dev/random' );
			}
		}
		return self::$state;
		
	}
	
	public function bytes( $size, $level = 0 ) {
		$blocks = max( ceil( $size / self::BLOCK_SIZE ), 1 );
		$result = '';
		for ( $i = 0; $i < self::BLOCK_SIZE; $i++ ) {
			$result .= $this->random( $level );
		}
		self::$state = $this->merge( self::$state, substr( $result, $size ) );
		return substr( $result, 0, $size );
	}
	
	private function merge( $src1, $src2 ) {
		if ( isset( self::$state ) ) {
			$i = ord( self::$state ) % 2;
		} else {
			$i = $this->number( 0, 255 ) % 2;
		}
		
		if ( $i === 0 ) {
			return \hash_hmac( self::MERGE_HASH, $src1, $src2, true );
		}
		
		return \hash_hmac( self::MERGE_HASH, $src2, $src1, true );
	}
}
