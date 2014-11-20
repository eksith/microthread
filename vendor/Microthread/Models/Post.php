<?php
/**
 * Basic post class
 * 
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */
 
namespace Microthread\Models;
use Microthread;

class Post extends Model {
	use Entry;
	
	const STATUS_COMPLETE		= 0;
	const STATUS_ERROR_STORAGE	= 1;
	const STATUS_ERROR_EXPIRED	= 2;
	
	const POST_MAX_SUMMARY_LENGTH	= 500;
	
	/**
	 * @var int Parent id
	 */
	public $parent_id;
	
	/**
	 * @var string Short excerpt from body (no HTML).
	 */
	public $summary			= '';
	
	/**
	 * @var string Full text from body (no HTML).
	 */
	public $plain			= '';
	
	/**
	 * @var string Unformatted raw user input
	 */
	public $raw			= '';
	
	/**
	 * @var string HTML formatted text.
	 */
	public $body			= '';
	
	/**
	 * @var array Tags, Categories, etc...
	 */
	public $taxonomy		= array();
	
	/**
	 * @var array User author
	 */
	public $user			= array();
	
	/**
	 * @var array Metadata
	 */
	public $meta			= array();
	
	
	/**
	 * Custom properties to populate taxonomy, user and meta
	 */
	public function __set( $name, $value ) {
		switch( $name ) {
			case 'taxonomyData':
				$this->taxonomy	= parent::parseAggregate( $value );
				break;
				
			case 'metaData':
				$this->meta	= parent::parseAggregate( $value );
				break;
				
			case 'userData':
				$this->user	= parent::parseAggregate( $value );
				break;
		}
	}
	
	public function save() {
		$params			= $this->baseParams();
		if ( isset( $this->id ) ) {
			return $this->editPost( $params );
		}
		
		return $this->newPost( $params );
	}
	
	/**
	 * Find a post
	 */
	public static function find( Array $filter ) {
		$filter		= parent::filterConfig( $filter );
		$params		= array();
		$sql		= self::sqlPrep( $filter, $params, $sql );
		
		if ( isset( $params['limit'] ) && $params['limit'] > 1 ) {
			return parent::find( $sql, $params );
		}
		
		return parent::find( $sql, $params, 'object' );
	}
	
	public static function delete( $id, $permanant = true ) {
		if ( $permanant ) {
			return parent::delete( 'posts', array( 'id' => $id ) );
			
		} else { // Only hiding. Change status.
			return parent::edit( 'posts', array( 'id' => $id, 'status' => -1 ) );
		}
	}
	
	/**
	 * ID doesn't exist. Creating a new post
	 */
	protected function newPost( $params, &$code ) {
		$this->id	= parent::put( 'posts', $params );
		if ( !$this->id ) {
			return self::STATUS_ERROR_STORAGE;
		}
		
		if ( !isset( $this->parent_id ) ) {
			$this->parent_id = $this->id; // If it's not a reply
		}
		
		$this->putFamily();
		$this->userInfo();
		$this->setTaxo();
		$this->setMeta();
		return self::STATUS_COMPLETE;
	}
	
	/**
	 * ID exists. Editing an existing post
	 */
	protected function editPost( $params, &$code ) {
		$params['id']	= $this->id;
		if ( parent::edit( 'posts', $params ) ) {
			$this->userInfo();
			$this->setTaxo();
			$this->setMeta();
			return self::STATUS_COMPLETE;
		}
		
		return self::STATUS_ERROR_STORAGE;
	}
	
	/**
	 * Author info for this post
	 */
	private function userInfo() {
		if ( empty( $this->user ) ) {
			return;
		}
		
		/**
		 * Get the last user
		 */
		$k  = array_keys( $this->user );
		$i  = end( $k );
		
		parent::put( 
			'posts_users', 
			array(
				'user_id'	=> $this->user[$i]['id'], 
				'post_id'	=> $this->id,
				'raw'		=> $this->raw
			)
		);
	}
	
	private function setTaxo() {
		if ( empty( $this->taxonomy ) ) {
			return;
		}
		Taxonomy::apply( $this->id, $this->taxonomy );
	}
	
	private function setMeta() {
		if ( empty( $this->meta ) ) {
			return;
		}
		
		Meta::apply( $this->id, $this->meta );
	}
	
	private function putFamily() {
		parent::newFamily( 'posts_family', $this->id, $this->parent_id );
	}
	
	protected static function sqlPrep( $filter, &$params, &$sql ) {
		$sql	= 'SELECT ';
		
		foreach( $filter['fields'] as $field ) {
			$sql .= "posts.$field AS $field, ";
		}
		
		$sql	= rtrim( $sql, ', ') . 
				self::aggregates( $filter ) . 
				' posts_family.parent_id AS parent_id FROM posts ' . 
				self::addJoins( $filter, $params ) . 
				' posts.status > -1 ORDER BY posts.id DESC';
		
		if ( isset( $filter['limit'] ) ) {
			$params['limit']	= $filter['limit'];
			$sql			.= ' LIMIT :limit';
		}
		
		if ( isset( $filter['offset'] ) ) {
			$params['offset']	= $filter['offset'];
			$sql			.= ' OFFSET :offset';
		}
		
		return $sql . ';';
	}
	
	/**
	 * Filter all the basic fields for creating/editing
	 */
	private function baseParams( &$tags ) {
		$this->plain	= Microthread\Html::plainText( $this->body )
		$this->summary	= Microthread\Util::smartTrim( self::POST_MAX_SUMMARY_LENGTH );
		
		$html = new \Microthread\Html;
		
		return array(
			'raw'		=> $this->raw,
			'body'		=> $html->filter( $this->raw ),
			'plain'		=> $this->plain,
			'summary'	=> $this->summary,
			'status'	=> isset( $this->status )? $this->status : 0
		);
	}
	
	protected static function aggregates( $filter ) {
		$sql = '';
		
		/**
		 * Taxonomy labels and term selector
		 */
		if ( isset( $filter['taxonomy'] ) ) {
			$sql .= ', '. parent::aggregateField(
					'taxonomy', 
					'taxonomyData', 
					array( 'label', 'term' ) 
				) . ', ';
		}
		
		/**
		 * Metadata field selector
		 */
		if ( isset( $filter['meta'] ) ) {
			$sql .= ', '. parent::aggregateField(
					'meta', 
					'metaData', 
					array( 'id', 'label', 'parse_as', 'content' ) 
				) . ', ';
		}
		
		/**
		 * User edit history
		 */
		if ( isset( $filter['user'] ) ) {
			$sql .= ', '. parent::aggregateField(
					'users', 
					'userData', 
					array( 'id', 'username', 'status' )
				) . ', ';
		}
		return $sql;
	}
	
	/**
	 * Add relevant JOINs to the list of fields/tables depending on what's being queried.
	 */
	protected static function addJoins( &$filter, &$params ) {
		$sql = '';
		
		/**
		 * Taxonomy info requested
		 */
		if ( isset( $filter['taxonomy'] ) ) {
			$sql .= self::taxonomyJoin( $filter['taxonomy'] ) . ' ';
		}
		
		/**
		 * Meta data requested
		 */
		if ( isset( $filter['meta'] ) ) {
			$sql .= parent::metaJoin( 'posts_meta', 'post', $filter['meta'] ) . ' ';
		}
		
		/**
		 * Include author info
		 */
		if ( isset( $filter['user'] ) ) {
			$sql .= self::userJoin( $filter['user'] ) . ' ';
		}
		
		/**
		 * Content body searching
		 */
		if ( isset( $filter['search'] ) ) {
			// TODO: After more coffee, add content body
		}
		
		if ( isset( $filter['id'] ) && $filter['id'] > 0 ) {
			$params['id']	= $filter['id'];
			$sql		.="JOIN posts_family ON 
						posts_family.child_id = posts.id 
						WHERE posts.id = :id AND";
			
		} elseif ( isset( $filter['parent'] ) ) {
			$params['parent'] = $filter['parent'];
			
			$sql .= "JOIN posts_family ON 
					posts_family.parent_id = :parent AND 
					posts.id = posts_family.child_id";
			
			if ( $filter['exclusive'] ) {
				$sql .= ' AND posts_family.child_id <> posts_family.parent_id';
			}
			
			$sql .= ' WHERE';
			
		} else {
			$sql .= "JOIN posts_family ON 
					posts_family.child_id = posts.id WHERE";
		}
		
		
		return $sql;
	}
	
	/**
	 * Post taxonomy query. Appends any taxonomies (tags, categories etc...)
	 * to the post query
	 * 
	 * @param string|array $labels Taxonomy type(s) to include
	 */
	protected static function taxonomyJoin( $labels ) {
		$params = '\'' . implode( '\', \'', parent::filterFields( $labels ) ) . '\'';
		
		if ( empty( $params ) ) {
			return '';
		}
		
		return "LEFT JOIN posts_taxonomy ON posts.id = posts_taxonomy.post_id
			LEFT JOIN taxonomy ON taxonomy.id = posts_taxonomy.taxonomy_id 
				AND taxonomy.label IN ( $params ) ";
	}
	
	/**
	 * Append user data table joins
	 */
	protected static function userJoin() {
		return "LEFT JOIN posts_users ON post.id = posts_users.post_id 
			LEFT JOIN users ON posts_users.user_id = users.id ";
	}
}
