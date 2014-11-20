<?php
/**
 * Blog post with tags, categories and comments
 * 
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */
 
namespace Microthread\Models;
use Microthread;

class BlogPost extends NewsPost {
	
	public $replies;
	
	public function __set( $name, $value ) {
		switch( $name ) {
			case 'allowVisitorComments':	// Allow anonymous visitor feedback
			case 'allowUserComments':	// Allow registered user feedback
			case 'allowGuestView':		// Allow unregistered users to view
				$this->meta[$name] = array( $value, 'bool' );
				break;
				
			case 'password'			// Password protected blog post
				$this->meta[$name] = array( password_hash( $value ), 'password' );
				break;
				
			default:
				parent::__set( $name, $value );
		}
	}
	
	public function __get( $name ) {
		switch( $name ) {
			case 'allowVisitorComments':
			case 'allowUserComments':
			case 'allowGuestView':
			case 'password':
				return isset( $this->meta[$name] )? 
					$this->meta[$name] : null;
			default:
				return parent::__get( $name );
		}
	}
	
	public static function getIndex( $page = 1 ) {
		
	}
	
	public static function getPost( $id, $page = 1 ) {
		$filter = array(
			'id'	=> $id,
			'meta'	=> 'title,published_at,expires_at,blogpost,'.
				'allowVisitorComments,allowUserComments,allowGuestView,password'
		);
		
		return parent::find( $filter );
	}
	
	public static function getComments( $id, $page = 1 ) {
		$filter = array(
			'parent'	=> $id,
			'page'		=> $page,
			'meta'		=> 'blogcomment',
			'exclusive'	=> true
		);
		
		return parent::find( $filter );
	}
	
	public function save() {
		if ( $this->parent_id ) {
			$this->meta['blogcomment']	= array( true, 'bool');
		} else {
			$this->meta['blogpost']		= array( true, 'bool');
		}
		$this->save();
	}
}
