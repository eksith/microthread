<?php

namespace Microthread\Models

trait Entry {
	
	/**
	 * @var int Class object identifier key (every object should have one)
	 */
	public $id;
	
	/**
	 * @var int Parent id
	 */
	public $parent_id		= 0;
	
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
	public $status		= 0;
	
	
	public function __construct( array $data = null ) {
		if ( empty( $data ) ) {
			return;
		}
		
		foreach ( $data as $field => $value ) {
			$this->$field = $value;
		}
	}
}
