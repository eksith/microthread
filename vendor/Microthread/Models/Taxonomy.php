<?php
/**
 * Taxonomy (tags and categories etc...) classification
 * 
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */
 
namespace Microthread\Models;
use Microthread;

class Taxonomy extends Model {
	use Entry;
	
	/**
	 * @var string Term label ( tag, forum etc... )
	 */
	public $label		= '';
	
	/**
	 * @var string Term name ( tech, anime etc... )
	 */
	public $term		= '';
	
	/**
	 * @var string URL friendly name
	 */
	public $slug		= '';
	
	public static function apply( $id, Array $taxo ) {
		foreach( $taxo as $t ) {
			if ( is_array( $t ) ) {
				self::assign( 
					$id, $t[0], 
					is_array( $t[1] )? $t[1] : array( $t[1] ) 
				);
			} else {
				self::assign( $id, 'tag', array( $t ) );
			}
		}
	}
	
	/**
	 * Apply a set of terms to a label and assign to a post
	 */
	public static function assign( $id, $label, Array $terms ) {
		$existing	= self::existing( $label, $terms );
		$nterms		= array();
		$ids		= array();
		
		foreach( $existing as $taxo ) {
			if ( in_array( $taxo->term, $terms ) ) {
				$nterms[]	= $taxo->term;
				$ids[]		= $taxo->id;
			}
		}
		
		$nterms	= array_diff( $terms, $nterms );
		if ( count( $nterms ) > 0 ) {
			$tPuts	= array();
			foreach( $nterms as $term ) {
				$tPuts[] = array( 
					'term'	=> $term, 
					'label' => $label,
					'slug'	=> Microthread\Util::slug( $term )
				);
			}
			$ins	= parent::putAll( 'taxonomy', $tPuts );
			$ids	= array_merge( $ins, $ids );
		}
		
		self::scrub( $id, $label );
		
		$nPuts = array();
		foreach( $ids as $id ) {
			$nPuts[] = array( 'taxonomy_id' => $id, 'post_id' => $id );
		}
		
		parent::putAll( 'posts_taxonomy', $nPuts );
	}
	
	/**
	 * Get the existing taxonomies with a specified label
	 * to the post query
	 * 
	 * @param string|array $labels Taxonomy type(s) to include
	 */
	public static function existing( $label = 'tag', Array $terms ) {
		if ( 0 === count( $terms ) ) {
			return array();
		}
		
		self::_addParams( 'str', $terms, $params, $in );
		$params['label']	= $label;
		$sql			= "SELECT id, label, term FROM taxonomy 
						WHERE term IN ( $in ) AND label = :label;";
		return parent::find( $sql, $params );
	}
	
	private static function scrub( $id, $label ) {
		$sql  = "DELETE pt FROM posts_taxonomy AS pt 
			INNER JOIN taxonomy AS ta ON pt.taxonomy_id = ta.id 
			WHERE ta.label = :label AND pt.post_id = :id;";
		
		parent::execute( $sql, array( 'label' => $label, 'id' => $id ) );
	}
}
