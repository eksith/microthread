<?php
/**
 * Basic user class
 * 
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */
 
namespace Microthread\Models;
use Microthread;

class User extends Model {
	use Entry;
	
	const STATUS_COMPLETE		= 0;
	const STATUS_NORMAL		= 1;
	const STATUS_BANNED		= -1;
	
	const STATUS_ERROR_STORAGE	= 1;
	const STATUS_ERROR_CONFLICT	= 2;
	const STATUS_ERROR_LOGIN	= 3;
	const STATUS_ERROR_NOTFOUND	= 4;
	const STATUS_ERROR_BANNED	= 5;
	
	/**
	 * Minimum username size
	 */
	const MIN_USER_SIZE		= 2;
	
	/**
	 * Max username size
	 */
	const MAX_USER_SIZE		= 20;
	
	/**
	 * @var string User login name
	 */
	public $username;
	
	/**
	 * @var string User login password
	 */
	public $password;
	
	/**
	 * @var string User profile
	 */
	public $bio;
	
	/**
	 * Create or edit post in the database.
	 */
	public function save() {
		$row	= 0;
		$params	= $this->baseParams();
		
		if ( isset( $this->id ) ) { // Editing existing user
			$params['id']	= $this->id;
			$row		= parent::edit( 'users', $params );
			if ( $row ) {
				return self::STATUS_COMPLETE;
			}
		} else { // Creating new user
			if ( userExists( $this->username ) ) {
				return self::STATUS_ERROR_CONFLICT;
			}
			$params['username'] = $this->username;
			$this->id = parent::put( 'users', $params );
			if ( $this->id ) {
				return self::STATUS_COMPLETE;
			}
		}
		
		return self::STATUS_ERROR_STORAGE;
	}
	
	public function find( $filter = array() ) {
		$filter		= parent::filterConfig( $filter );
		$params		= array();
		$sql		= 'SELECT id, username, created_at, updated_at, status';
		
		if ( isset( $filter['bio'] ) ) {
			$sql .= ', bio';
		}
		if ( isset( $filter['meta'] ) ) {
			$sql .= ' '. parent::metaJoin( 'users_meta', 'user', $filter['meta'] );
		}
		$sql .= ' WHERE';
		if ( !empty(  $filter['search'] ) ) {
			$params['username'] = $filter['search'];
			$sql .= ' username LIKE %:username%'
		}
		
		$sql .= ';';
		return parent::find( $sql, $params );
	}
	
	/**
	 * Authenticate.
	 */
	public function login() {
		$user	= parent::find( 
				array( 
					'fields'	=> 'id, password, status', 
					'table'		=>'users'
				), 
				array( 'username'	=> $this->username ), 
				'object' 
			);
		
		if ( null === $user ) {
			return self::STATUS_ERROR_NOTFOUND;
		}
		
		if ( self::STATUS_ERROR_BANNED === $user->status ) {
			return self::STATUS_ERROR_BANNED;
		}
		
		if ( password_verify( $this->password, $user->password ) ) {
			$this->status	= $user->status;
			$params		= $this->baseParams();
			$params['id']	= $this->id;
			
			parent::edit( 'users', $params );
			return self::STATUS_ERROR_LOGIN;
		}
		
		return self::STATUS_COMPLETE;
	}
	
	/**
	 * (un)Block a user by changing the status
	 * 
	 * @param bool $status Block status (true if blocked)
	 * @return int Affected rows
	 */
	public function setBan( $status = true ) {
		$params = array( 'id' => $this-> id );
		if ( $status ) {
			$params['status'] = self::STATUS_BANNED;
		} else {
			$params['status'] = self::STATUS_NORMAL;
		}
		return parent::edit( 'users', $params );
	}
	
	/**
	 * Pre-save procedures including hashing the password ( done at each save if specified )
	 */
	private function baseParams() {
		$params = array();
		
		if ( isset( $this->status ) ) {
			$params['status'] = $this->status;
		}
		
		if ( isset( $this->password ) ) {
			/**
			 * Leaving default settings on the password_hash function
			 * http://php.net/manual/en/function.password-hash.php
			 */
			$params['password'] = password_hash( $this->password );
		}
		
		if ( isset( $this->bio ) ) {
			$html = new Microthread\Html();
			$params['bio']	= $html->filter( $this->bio );
		}
		
		return $params;
	}
	
	private function userExists( $username ) {
		$user	= parent::find( 
				array( 'fields' => 'id', 'table' => 'users' ), 
				array( 'username' => $this->username ), 
				'row'
			);
		if ( empty( $user ) ) {
			return false;
		}
		return true;
	}
}
