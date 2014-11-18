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
	
	public function save() {
		$this->raw = $this->hashtags( $this->raw, $tags );
		if ( !empty( $tags ) ) {
			$this->taxonomy[] = array( 'tags' => $tags );
		}
		
		$this->save();
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
