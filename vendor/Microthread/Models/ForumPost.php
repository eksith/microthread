<?php
/**
 * Forum post with hashtags
 * 
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */
 
namespace Microthread\Models;
use Microthread;

class ForumPost extends Post {
	
	public function __set( $name, $value ) {
		switch( $name ) {
			case 'tags':
			case 'categories':
				$this->taxonomy[] = array( $name => $value );
				break;
				
			case 'title':		// Post title
				$this->meta[$name] = array( $value, 'text' );
				break;
		}
	}
	
	public function __get( $name ) {
		switch( $name ) {
			case 'tags':
			case 'categories':
				return isset( $this->taxonomy[$name] )? 
					$this->taxonomy[$name] : null;
			
			case 'title':
				return isset( $this->meta[$name] )? 
					$this->meta[$name] : null;
		}
		return null;
	}
	
	public function save() {
		$this->raw			= $this->hashtags( $this->raw, $tags );
		$this->meta['forumpost']	= array( true, 'bool' );
		
		if ( !empty( $tags ) ) {
			$this->tags = array( 'tags' => $tags );
		}
		
		$this->save();
	}
	
	public static function getIndex( $page = 1 ) {
		$filter = array(
			'page'		=> $page,
			'meta'		=> 'title,forumpost'
		);
		
		return parent::find( $filter );
	}
	
	public static function getThread( $id, $page = 1 ) {
		$filter = array(
			'parent'=> $id,
			'page'	=> $page,
			'meta'	=> 'title,forumpost'
		);
		
		return parent::find( $filter );
	}
	
	public static function getPost( $id ) {
		$filter = array(
			'id'	=> $id,
			'meta'	=> 'title,forumpost'
		);
		
		return parent::find( $filter );
	}
	
	/**
	 * Twitter style #hashtags link formatting
	 */
	protected function hashtags( $data, &$tags ) {
		$tags	= array();
		$data	= preg_replace_callback(
				"/(^|)#(\p{L}\p{N}{1,30})/", 
				function( $matches ) use ( $tags ) {
					$tags[] = $match[2];
					return 
					"<a href='/tags/{$match[2]}' class='tag'>{$match[0]}</a>";
				},
				$data, 5 );
		return $data;
	}
}
