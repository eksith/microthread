<?php
/**
 * Metadata for posts
 * 
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */

namespace Microthread\Models;

class Meta extends Model {
	public static function apply( $id, array $meta ) {
		self::scrub( $id );
		$params = array();
		
		foreach( $meta as $k = $v ) {
			$params[] = array( 
				'label'		=> $k,
				'parse_as'	=> $v[1],
				'content'	=> $v[0]
			);
		}
		
		$ids	= parent::putAll( 'meta', $params );
		$params	= array();
		
		foreach( $ids as $k = $v ) {
			$params[] = array(
				'post_id' => $id,
				'meta_id' => $v
			);
		}
		
		parent::putAll( 'posts_meta', $params );
	}
	
	private static function scrub( $id ) {
		$sql = "DELETE FROM meta AS m 
			INNER JOIN posts_meta pm ON m.id = pm.meta_id 
			WHERE pm.post_id = :id";
		
		parent::execute( $sql, array( 'id' => $id ) );
	}
}
