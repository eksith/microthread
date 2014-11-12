<?php

namespace Microthread\Models;
use Microthread;

class Session extends Model {
	use Entry;
	
	const HASH_ALGO		= 'tiger160,4';
	
	/**
	 * @var string Session decryption key
	 */
	public $skey;
	
	/**
	 * @var string Session data
	 */
	public $data;
	
	public function __get( $name ) {
		if ( empty( $this->data ) ) {
			return '';
		}
		
		if ( 'decrypted' === $name ) {
			$key = hash( self::HASH_ALGO, getRawSig() . $this->skey );
			return self::decrypt( $this->data, $key );
		}
		if ( 'encrypted' === $name ) {
			$this->skey	= hash( self::HASH_ALGO, uCrypt::IV( 5 ) );
			$key		= hash( 
						self::HASH_ALGO, 
						getRawSig() . $this->skey
					);
			return self::encrypt( $this->data, $key );
		}
		
		return '';
	}
	
	public static function find( $filter = array() ) {
		$data	= isset( $filter['data'] ) ? true : false;
		$sql	= 'SELECT id, skey, created_at, updated_at';
		
		if ( $data ) {
			$sql .= ', data';
		}
		
		$sql .= ' FROM sessions WHERE id = :id LIMIT 1';
		return parent::find( 
			$sql, array( 'id' => $filter['id'] ), 'object'
		);
	}
	
	public function save() {
		$params	= array(
			'id'		=> $this->id,
			'data'		=> $this->encrypted,
			'skey'		=> $this->skey
		);
		
		//parent::beginTransaction();
		/**
		 * Editing a session
		 */
		if ( isset( $this->created_at ) ) {
			parent::edit( 'sessions', $params );
			
		/**
		 * We're creating a new session
		 */
		} else {
			parent::put( 'sessions', $params );
		}
		
		//parent::commit();
	}
	
	public static function delete( $params = array() ) {
		parent::delete( 'sessions', $params );
	}
	
	
	/**
	 * Helpers
	 */
	
	public static function encrypt( $data, $skey ) {
		return Microthread\uCrypt::encrypt( json_encode( $data ), $skey );
	}
	
	public static function decrypt( $data, $skey ) {
		try {
			return json_decode( Microthread\uCrypt::decrypt( $data, $skey ), true );
		} catch( Exception $e ) {
			return array();
		}
	}
	
	public static function gc( $exp ) {
		$sql	= "DELETE FROM sessions WHERE ( updated_at < :exp );";
		parent::beginTransaction();
		
		$stmt = parent::prepare( $sql );
		$stmt->execute( array( 'exp' => $exp ) );
		
		parent::commit();
	}	
}
