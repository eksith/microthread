<?php
/**
 * Base object modal for finding, editing, deleting objects by table
 * This class handles connecting to a database if necessary and so it 
 * depends on the 'Cxn' class and requires DBH to be defined.
 * This class contains methods for dynamic query building
 * 
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.6
 * @uses Cxn
 */

namespace Dragon\Models;

abstract class Model {
	
	/**
	 * @var int Class object identifier key (every object should have one)
	 */
	public $id;
	
	/**
	 * @var int Class object creation date. Should not modified.
	 */
	public $created_at;
	
	/**
	 * @var int Class object edited/saved date. Must be modified on each save.
	 */
	public $updated_at;
	
	/**
	 * @var int Special status. Relevance will differ per object.
	 * @example An entry with status = -1 may be 'hidden' from view
	 */
	public $status			= 0;
	
	/**
	 * @var array Any errors accumulated during database interaction
	 */
	public static $errors	= array();
	
	/**
	 * @var array List of open connections in :
	 * 	array( connection, database type, transactions ) format
	 */
	private static $connections = array();
	
	/**
	 * @var array List of connection strings
	 */
	private static $connstrings = array();
	
	
	const FIELD_REGEX	= '/[^a-z\_\.]/i';
	
	const SEARCH_REGEX	= '/[^\pN\pL\s\-\_\.]/i';
	
	
	/**
	 * Checks PDO connection or assigns to self::$db 
	 * if it hasn't been set and a new one has been passed.
	 * 
	 * @param object $db PDO connection
	 * @param string $name Connection name
	 * @return bool True of the variable was set. False on failure.
	 */
	private static function _db( $db = null, $name = 'default' ) {
		if ( 
			isset( self::$connections[$name] ) && 
			is_object( self::$connections[$name][0] )
		) {
			return true;
		} elseif( is_object( $db ) ) {
			self::$connections[$name][0] = $db;
			return true;
		}
		
		return false;
	}
	
	/**
	 * Clean up
	 */
	public function __destruct() {
		foreach( self::$connections as $k => $v ) {
			$v[0] = null;
		}
		self::$connections = null;
	}
	
	/**
	 * Setup local Cxn instance with PDO 
	 * Note: This depends on the Cxn class and also DBH (your connection string) being set
	 */
	public static function init( $name = 'default' ) {
		if ( self::_db( null, $name ) ) { 
			return ;
		}
		$db		= new Cxn();
		$cs		= self::getConnString( $name );
		if ( null === $cs ) {
			throw new Exception( 'Connection string not found' );
		}
		$pdo		= $db->connect( $cs, $dbType, $pdo );
		self::$connections[$name]	= array( $pdo, $dbType, 0 );
	}
	
	private static function getConnString( $name ) {
		if ( array_key_exists( self::$connstrings ) ) {
			return self::$connstrings[$name];
		}
		return null;
	}
	
	public static function setConnString( $dbh, $name = 'default' ) {
		self::$connestrings[$name] = $dbh;
	}
	
	protected static function getDbType( $name = 'default' ) {
		return isset( self::$connections[$name] )? self::$connections[$name][1] : '';
	}
	
	/**
	 * Find objects using the given sql and parameters
	 * 
	 * @param string $sql Database query
	 * @param array $params Selector parameters in 'column' => 'value' format
	 * @return mixed Object of the same type as the class calling this or null on failure
	 * 		Single object or array of the same type as the class calling 
	 * 		this or null on failure
	 */
	protected static function find( 
		$query, 
		$params, 
		$fetch		= 'class', 
		$name		= 'default'
	) {//Incorporate joins
		if (
			is_array( $query ) && 
			isset( $query['fields'] ) && 
			$query['table']
		) {
			$sql = self::_selectStatement( 
					$query['fields'], 
					$query['table'], 
					$params
				);
		} elseif ( is_array( $query ) ) {
			return null; // Unrecognized query
		}
		
		$stmt	= self::prepare( $query, $name );
		
		if ( $stmt->execute( $params ) ) {
			if ( 'row' === $fetch ) {
				return $stmt->fetch( \PDO::FETCH_ASSOC );
			} elseif ( 'object' === $fetch ) {
				return $stmt->fetchObject( get_called_class() );
			} elseif ( 'lazy' === $fetch ) {
				return $stmt->fetch( \PDO::FETCH_LAZY );
			} else {
				return $stmt->fetchAll(
					\PDO::FETCH_CLASS, get_called_class()
				);
			}
		}
		
		return null;
	}
	
	/**
	 * Insert a single row into a table
	 * 
	 * @param string $table Database table name
	 * @param array $params Insert values in 'column' => 'value' format
	 * @param bool $noKey Prevent returning the last inserted ID if true
	 * @return int The ID of the newly inserted row or 0 on failure
	 */
	protected static function put(
		$table, 
		$params, 
		$noKey		= false, 
		$name		= 'default'
	) {
		$sql	= self::_insertStatement( $table, $params );
		$stmt	= self::prepare( $sql, $name );
		
		if ( $stmt->execute( $params ) ) {
			/**
			 * If the id was already specified, we just need to know 
			 * whether the record was successfully inserted. 
			 * Or else return the last inserted id
			 */
			return ( array_key_exists( 'id', $params ) || $noKey ) ? 
				true : self::lastId();
		}
		
		return 0;
	}
	
	/**
	 * Bulk insert rows into a table
	 * 
	 * @param string $table Database table name
	 * @param array $records Parameter array of rows
	 * @return array IDs of each newly inserted row or empty array on failure
	 */
	protected static function putAll(
		$table, 
		$records	= array(), 
		$name		= 'default'
	) {
		if ( !count( $records ) ) { 
			return array(); 
		}
		$rows = array();
		
		try {
			self::beginTransaction( $name );
			$sql	= self::_insertStatement( $table, $records[0] );
			$stmt	= self::prepare( $sql, $name );
			
			foreach ( $records as $params ) {
				if ( $stmt->execute( $params ) ) {
					$row[] = array_key_exists( 'id', $params ) ?
							true : self::lastId();
				}
			}
			
			self::commit();
			
		} catch ( \PDOException $e ) {
			self::rollBack();
			self::$errors[] = array( 
				"putAll in table $table", $e->getMessage()
			);
		}
		
		return $rows;
	}
	
	/**
	 * Update records in a single table
	 * 
	 * @param string $table Database table name
	 * @param array $params Column parameters (id required)
	 * @return int Number of rows affected
	 */
	protected static function edit(
		$table,
		$params, 
		$name		= 'default'
	) {
		if ( !isset( $params['id'] ) ) {
			return 0;
		}
		
		$id		= $params['id'];
		unset( $params['id'] );
		
		$sql		= self::_updateStatement( 
					$table, 
					$params, 
					"$table.id = :id"
				);
		$params['id']	= $id;
		return self::execute( $sql, $params, $name );
	}
	
	/**
	 * Delete from a single table based on parameters
	 * 
	 * @param string $table Table name (only one) to delete from
	 * @param array $params Delete selectors
	 * 
	 * @return int Number of rows affected/deleted
	 */
	protected static function delete(
		$table,
		$params,
		$name		= 'default'
	) {
		$sql		= self::_deleteStatement( $table, $params );
		return self::execute( $sql, $params, $name );
	}
	
	/**
	 * 
	 * @example Deleting a post with ID = 223
	 * 		Model::delete( 'posts', 223 );
	 */
	protected static function deleteById(
		$table, 
		$id,
		$name		= 'default'
	) {
		if ( is_array( $id ) ) {
			self::_addParams( 'str', $id, $params, $in );
		} else {
			$in	= ':id';
			$params	= array( 'id' => $id );
		}
		
		$sql		= "DELETE FROM $table WHERE id IN ( $in );";
		return self::execute( $sql, $params, $name );
	}
	
	/**
	 * Set the PDO query statement
	 */
	protected static function prepare( $sql, $name = 'default' ) {
		self::init( $name );
		return self::$connections[$name][0]->prepare( $sql );
	}
	
	/**
	 * Execute a PDO statement for one set of parameters
	 * @return int Database rows affected
	 */
	protected static function execute( 
		$sql, 
		$params		= array(), 
		$name		= 'default' 
	) {
		$result = 0;
		
		try {
			$stmt	= self::prepare( $sql, $name );
			if ( empty( $params ) ) {
				if ( $stmt->execute() ) {
					$result = $stmt->rowCount();
				}
			} elseif ( $stmt->execute( $params ) ) {
				$result	= $stmt->rowCount();
			}
		} catch( \PDOException $e ) {
			self::$errors[]	= array( 
				"execute of SQL : $sql", 
				$e->getMessage()
			);
		}
		
		return $result;
	}
	
	/**
	 * Execute a single PDO statement multiple times for a collection of parameters
	 * @return array Of integers Database rows affected
	 */
	protected static function executeAll( 
		$sql, 
		$paramCollection = array(), 
		$name		 = 'default'
	) {
		$result = array();
		
		try {
			self::beginTransaction( $name );
			$stmt = self::prepare( $sql );
			
			foreach ( $paramCollection as $params ) {
				if( $stmt->execute( $params ) ) {
					$result[] = $stmt->rowCount();
				}
			}
			
			self::commit( $name );
		} catch( \PDOException $e ) {
			self::$errors[] = array( 
						"executeAll of SQL : $sql", 
						$e->getMessage()
					);
			self::rollback( $name );
		}
		
		return $result;
	}
	
	/**
	 * Execute multiple PDO statements with matching parameters
	 * @return array Of integers Database rows affected
	 */
	protected static function executeMultiple( 
		array $statements, 
		$name		= 'default'
	) {
		$result = array();
		
		try {
			self::beginTransaction( $name );
			foreach ( $statements as $sql ) {
				$stmt = self::prepare( $sql[0], $name );
				if ( $stmt->execute( $sql[1] ) ) {
					$result[] = $stmt->rowCount();
				}
			}
			
			self::commit( $name );
		} catch( \PDOException $e ) {
			self::$errors[] = array( "executeMultiple", $e->getMessage() );
			self::rollback( $name );
		}
		
		return $result;
	}
	
	/**
	 * Safe transaction start
	 */
	protected static function beginTransaction( $name = 'default' ) {
		self::init( $name );
		if ( ++self::$connections[$name][2] == 1 ) {
			return self::$connections[$name][0]->beginTransaction();
		}
		return self::$connections[$name][2] >= 0;
	}
	
	/**
	 * Safe transaction commit
	 */
	protected static function commit() {
		if ( --self::$connections[$name][2] == 0 ) {
			return self::$connections[$name][0]->commit();
		}
		return self::$connections[$name][2] >= 0;
	}
	
	/**
	 * Safe transaction rollback
	 */
	protected static function rollback( $name = 'default' ) {
		if ( self::$connections[$name][2] > 0 ) {
			self::$connections[$name][2] = 0;
			return self::$connections[$name][0]->rollback();
		}
		self::$connections[$name][2] = 0;
		return false;
	}
	
	/**
	 * Get the id of the last inserted record
	 * @return int Record primary key
	 */
	protected static function lastId( $name = 'default' ) {
		return ( 'postgres' === self::getDbType( $name ) ) ? 
			self::$connections[$name][0]->lastInsertId( 'id' ) : 
			self::$connections[$name][0]->lastInsertId();
	}
	
	protected static function metaJoin( $table, $labels ) {
		$table	= preg_replace( self::FIELD_REGEX, '', $table );
		$params	= parent::filterFields( $labels );
		return self::metaJoinAll( $table ) . " AND meta.label IN ( $params )";
	}
	
	private static function metaJoinAll( $table ) {
		return "LEFT JOIN $table_meta ON post.id = $table_meta.post_id 
			LEFT JOIN meta ON $table_meta.meta_id = meta.id";
	}
	
	protected static function newFamily( $table, $id, $parent ) {
		$table	= preg_replace( self::FIELD_REGEX, '', $table );
		$params = array(
			'parent_id'	=> $parent,
			'child_id'	=> $id
		);
		
		return self::put( $table, $params, true );
	}
	
	
	/**
	 * Add parameters to conditional IN/NOT IN ( x,y,z ) query
	 */
	protected static function _addParams( 
		$t, 
		&$values, 
		&$params	= array(), 
		&$in		= '' 
	) {
		$vc = count( $values );
		for ( $i = 0; $i < $vc; $i++ ) {
			$in			= $in . ":v{$i},";
			$params["v{$i}"]	= array( $values[$i], $t );
		}
		
		$in = rtrim( $in, ',' );
	}
	
	/**
	 * Prepares parameters for SELECT, UPDATE or INSERT SQL statements.
	 * 
	 * E.G. For INSERT
	 * :name, :email, :password etc...
	 * 
	 * For UPDATE or DELETE
	 * name = :name, email = :email, password = :password etc...
	 */
	protected static function _setParams( 
		$fields		= array(), 
		$mode		= 'select', 
		$table		= '' 
	) {
		$columns = is_array( $fields ) ? 
				array_keys( $fields ) : 
				array_map( 'trim', explode( ',', $fields )
			);
		
		switch( $mode ) {
			case 'select':
				return implode( ', ', $columns );
				
			case 'insert':
				return ':' . implode( ', :', $columns );
			
			case 'update':
			case 'delete':
				$v = array_map( 
					function( $field ) use ( $table, $fields ) {
						if ( empty( $field ) ) { 
							return '';
						}
						return "$field = :$field";
					}, $columns );
				return implode( ', ', $v );
		}
	}
	
	protected static function _selectStatement(
		$table,
		$fields,
		$params
	) {
		$cols = self::_setParams( $fields, 'select', $table );
		$vals = self::_setParams( $fields, 'update', array_keys( $params ) );
		return	"SELECT $cols FROM $table WHERE $vals;";
	}
	
	/**
	 * Prepares SQL INSERT query with parameters matching field names
	 * 
	 * @param string $table Table name
	 * @param string|array $fields A comma delimited string of fields or 
	 * 		an array
	 * 
	 * @example field => value pairs (the 'field' key will be extracted as 
	 * 		the parameter)
	 * 		_insertStatement(
	 * 			'posts', 
	 * 			array( 
	 * 				'title' => 'Test title', 
	 * 				'author' => 'Guest'
	 * 			) 
	 * 		);
	 * 
	 * @return string;
	 */
	protected static function _insertStatement( $table, $fields ) {
		$cols = self::_setParams( $fields, 'select', $table );
		$vals = self::_setParams( $fields, 'insert', $table );
		return	"INSERT INTO $table ( $cols ) VALUES ( $vals );";
	}
	
	/**
	 * Prepares sql UPDATE query with parameters matching field names
	 * 
	 * @param string $table Table name
	 * @param string|array $fields A single field or comma delimited string 
	 * 		of fields or an array
	 * 
	 * @example field => value pairs (the 'field' key will be extracted as 
		the parameter)
	 * 		_updateStatement(
	 * 			'posts', 
	 * 			array( 
	 * 				'title' => 'Changed title', 
	 * 				'author' => 'Edited Guest' 
	 * 			),
	 * 			array( 'id' => 223 )
	 * 		);
	 * @return string;
	 */
	protected static function _updateStatement(
		$table, 
		$fields		= null, 
		$cond		= '' 
	) {
		$params = self::_setParams( $fields, 'update', $table );
		$sql	= "UPDATE $table SET $params";
		
		if ( !empty( $cond ) ) {
			$sql .= " WHERE $cond";
		}
		
		return $sql . ';';
	}
	
	/**
	 * Prepares sql DELETE query with parameters matching field names
	 * 
	 * @param string $table Table name
	 * @param string|array $fields A comma delimited string of fields or an array of 
	 *		field => value pairs 
	 * 		(the 'field' key will be extracted as the parameter)
	 * @return string;
	 */
	protected static function _deleteStatement( 
		$table, 
		$fields		= null, 
		$limit		= null
	) {
		$params	= self::_setParams( $fields, 'delete', $table );
		$sql	= "DELETE FROM $table WHERE ( $params )";
		
		/**
	 	 * Limit must consist of an integer and not start with a '0'.
		 * Else limit the deletion to one row (damage control)
	 	 */
		if ( self::checkLimit( $limit ) ) {
			$sql .= " LIMIT $limit";
		} elseif ( null !== $limit ) {
			$sql .= " LIMIT 1";
		}
		
		return $sql . ';';
	}
	
	/**
	 * Pagination offset calculator
	 * 
	 * @param int $page Currently requested index (starting from 1)
	 * @param int $limit Maximum number of records per page
	 * @return int Pagination offset
	 */
	protected static function _offset( $page, $limit ) {
		$page	= ( empty( $page ) )? 0 : ( int ) $page;
		$limit	= ( empty( $limit ) )? 0 : ( int ) $limit;
		
		if ( $page < 0 || $limit < 0 ) {
			return 0; 
		}
		
		return ( $page - 1 ) * $limit;
	}
	
	/**
	 * Convert a unix timestamp a datetime-friendly timestamp
	 * 
	 * @param int $time Unix timestamp
	 * @return string 'Year-month-date Hour:minute:second' format
	 */
	protected static function _myTime( $time ) {
		return gmdate( 'Y-m-d H:i:s', $time );
	}
	
	/**
	 * Limit the maximum page limit to 3 digits ( 1 - 999 )
	 */
	protected static function checkLimit( $limit = null ) {
		if (
			null !== $limit && 
			preg_match( '/^([1-9][0-9]?+){1,3}$/', $limit )
		) {
			return true;
		}
		
		return false;
	}
	
	protected static defaultLimit(
		$filter,
		$value,
		$default 
	) {
		if ( isset( $filter[$value] ) ) {
			return self::checkLimit( $filter[$value] ) ? 
				$filter[$value] : $default;
		}
		return $default;
	}
	
	/**
	 * Check if this is a numeric id
	 */
	protected static function isId( $id ) {
		if ( null !== $id && preg_match( '/^([1-9][0-9]?+){1,}$/', $id ) ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Sets filter configuration ( pagination, limit, id etc... )
	 */
	protected static function filterConfig( $filter = array() ) {
		if ( isset( $filter['id'] ) ) {
			$filter['id']	= self::isId( $filter['id'] )? $filter['id'] : 0;
		} else {
			$filter['id']	= 0;
		}
		
		$filter['limit']	= self::defaultLimit( $filter, 'limit', 1 );
		$filter['page']		= self::defaultLimit( $filter, 'page', 1 );
		$filter['search']	= isset( $filter['search'] ) ?	
						preg_replace( 
							self::SEARCH_REGEX, 
							$filter['search'] 
						) : '';
		
		$offset			= self::_offset(
						$filter['page'] , $filter['limit']
					);
		
		if ( $offset > 0 ) {
			$filter['offset'] = $offset;
		}
		return $filter;
	}
	
	/**
	 * Composite field for multi-column aggregate data from a single table
	 * E.G. label,term1|label,term2 format
	 * 
	 * @param string $table Parent database table
	 * @param string $field Composite column name
	 * @param array $fields List of columns to aggregate from parent table
	 */
	protected static function aggregateField( 
		$table, 
		$field, 
		Array $fields, 
		$name		= 'default' 
	) {
		/**
		 * If this is SQLite (I find your lack of faith, disturbing)
		 */
		$dbType = self::getDbType( $name );
		
		if ( "sqlite" === $dbType ) {
			$params = $table . '.' . implode( "||','||{$table}.", $fields );
			return "GROUP_CONCAT( {$params}, '|' ) AS {$field}";
		}
		
		$params = $table . '.'. implode( ",',',{$table}.", $fields );
		
		/**
		 * If this is Postgres...
		 */
		if ( "postgres" === $dbType ) {
			return "ARRAY_TO_STRING( 
					ARRAY_AGG( CONCAT( {$params} ) ), '|'
				) AS {$field}";
		}
		
		/**
		 * If not, try the MySQL method
		 */
		return "GROUP_CONCAT( CONCAT( {$params} ) SEPARATOR '|' ) AS {$field}";
	}
	
	/**
	 * Converts label,term1|label,term2 format into
	 * label (term1, term2) format
	 * 
	 * @param string $labels Unformatted string from database
	 * @returns array
	 */
	protected static function parseAggregate( $labels ) {
		/**
		 * taxonomy("tags", "categories", "forum" etc...),
		 * label("computers", "programming", "tech" etc...)
		 */
		$params	= array();
		$taxos	= explode( '|', $labels );
		
		foreach( $taxos as $t ) {
			if ( empty( $t ) ) { continue; }
			
			$tx = explode( ',', $t );
			if ( empty( $tx ) ) { continue; }
			
			/**
			 * Do we have an array for this taxonomy label already?
			 * If not, create it
			 */
			if ( !isset( $params[$tx[0]] ) ) {
				$params[$tx[0]] = array();
			}
			
			if ( isset( $tx[1] ) ) {
				$params[$tx[0]][] = $tx[1];
			}
		}
		
		return $params;
	}
	
	/**
	 * Highly restrictive label name filter ('tag', 'category', 'forum' etc...)
	 */
	protected static function filterFields( $labels ) {
		$filter	= function( $v ) {
			return empty( $v ) ? 
				'' : preg_replace( self::FIELD_REGEX, '', trim( $v ) );
		};
		
		if ( !is_array( $labels ) ) {
			return array_map( $filter, explode( ',', $labels ) );
		}
		
		return array_map( $filter, $params );
	}
}
