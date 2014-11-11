<?php
/**
 * PDO Connector class
 * Modifies the DSN to parse username and password individually.
 * Optionally, gets the DSN directly from php.ini.
 *
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.4
 */

namespace Microthread\Models;
 
class Cxn {
	
	public function __construct() {}
	
	/**
	 * @param string Connection string
	 * @param string Database type inferred form connection string
	 */
	public function connect( $dbh, &$dbType, &$pdo ) {
		$settings = array(
			\PDO::ATTR_TIMEOUT		=> "5",
			\PDO::ATTR_ERRMODE		=> \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE	=> \PDO::FETCH_ASSOC,
			\PDO::ATTR_PERSISTENT		=> true
		);
		
		$this->_dsn( $dbh, $username, $password );
		$dbType = $this->findDbType( $dbh );
		
		if ( 'mysql' === $dbType ) {
			/**
			 * Slightly slower, but more secure to disable 
			 * emulation
			 */
			$settings[\PDO::ATTR_EMULATE_PERPARES] = false;
		}
		
		return new \PDO( $dbh, $username, $password, $settings );
	}
	
	/**
	 * Extract the username and password from the DSN and rebuild the 
	 * connection string
	 * 
	 * @param string $dsn Full connection string or DSN identifyer in php.ini
	 * @param string $username Optional username. If empty, it will extract from DSN
	 * @param string $password Also optional and will extract from DSN as above
	 */
	private function _dsn( &$dsn, &$username = '', &$password = '' ) {
		/**
		 * No host name with ':' would mean this is a DSN name in php.ini
		 */
		if ( false === strrpos( $dsn, ':' ) ) {
			/**
			 * We need get_cfg_var() here because ini_get doesn't work
			 * https://bugs.php.net/bug.php?id=54276
			 */
			if ( false === strrpos( $dsn, APPNAME . '.database' ) ) {
				$dsn = get_cfg_var( "php.dsn.$dsn" );
			} else {
				$dsn = get_cfg_var( APPNAME . '.database' );
			}
		}
		
		/**
		 * Some people use spaces to separate parameters in DSN strings 
		 * and this is NOT standard
		 */
		$d = explode( ';', $dsn );
		$m = count( $d );
		$s = '';
		
		for( $i = 0; $i < $m; $i++ ) {
			$n = explode( '=', $d[$i] );
			
			/**
			 * Empty parameter? Continue
			 */
			if ( count( $n ) <= 1 ) {
				$s .= implode( '', $n ) . ';';
				continue;
			}
			
			/**
			 * Username or password?
			 */
			switch( trim( $n[0] ) ) {
				case 'uid':
				case 'user':
				case 'username':
					$username = trim( $n[1] );
					break;
				
				case 'pwd':
				case 'pass':
				case 'password':
					$password = trim( $n[1] );
					break;
				
				/**
				 * Some other parameter? Leave as-is
				 */
				default:
					$s .= implode( '=', $n ) . ';';
			}
		}
		
		$dsn = rtrim( $s, ';' );
	}
	
	/**
	 * Useful for database specific SQL.
	 * Expand as necessary.
	 */
	private function findDbType( $dsn ) {
		if ( 0 === strpos( $dsn, 'mysql' ) ) {
			return 'mysql';
		} elseif ( 0 === strpos( $dsn, 'postgres' ) ) {
			return 'postgres';
		} elseif ( 0 === strpos( $dsn, 'sqlite' ) ) {
			return 'sqlite';
		}
		
		return 'other';
	}
}
