<?php
/**
 * Page caching with APC and/or files. If APC isn't present, defaults to file cache
 *
 * @author Eksith Rodrigo <reksith at gmail.com>
 * @license http://opensource.org/licenses/ISC ISC License
 * @version 0.1
 */

namespace Microthread;

class Cache {
	
	public static function get( $key, $temp = true ) {
		if ( $temp && function_exists( 'apc_fetch' ) ) {
			$data = apc_fetch( $key );
			return ( false === $data )? null : $data;
		}
		
		$path = self::path( $key, $temp );
		if ( false !== ( $ftme = @filemtime( $path ) ) ) {
			if ( time() - $ftme > CACHE_TIME ) {
				return null;
			}
		}
		
		$data = file_get_contents( $path );
		return ( false === $data )? null : $data;
	}
	
	public static function put( $key, $data, $temp = true ) {
		if ( $temp && function_exists( 'apc_store' ) ) {
			return apc_store( $key, $data, CACHE_TIME );
		}
		
		$path = self::path( $key, $temp, true );
		
		if ( false === file_put_contents( $path, $data, LOCK_EX ) ) {
			return false;
		}
		
		return true;
	}
	
	public static function delete( $key, $temp = true ) {
		if ( $temp && function_exists( 'apc_delete' ) ) {
			return apc_delete( $key );
		}
		
		$path = self::path( $key, $temp );
		return self::del( $path );
	}
	
	public static function append( $key, $data, $temp = true ) {
		$data = self::get( $key, $temp ) . $data;
		self::delete( $key, $temp );
		self::put( $key, $data, $temp );
	}
	
	/**
	 * This only applies to file caches. APC takes care of stored variables on its own
	 */
	public static function gc() {
		$time	= time();
		self::cleanup( CACHE, CACHE_TIME );
	}
	
	/**
	 * Completely empties the cache. Use this only when making *major* changes
	 * I.E. Changing the number of displayed items per page
	 */
	public static function scrub() {
		if ( function_exists( 'apc_clear_cache' ) ) {
			apc_clear_cache( 'user' );
			return;
		}
		self::cleanup( CACHE, 0 );
	}
	
	private static function path( $key, $temp, $create = false ) {
		$root = ( $temp )? CACHE : ARCHIVE;
		return self::filePath( $root, $key, $create );
	}
	
	public static function filePath( $root, $name, $create = true, $dlen = 3, $depth = 3 ) {
		$h = sha1( $name );
		$p = array_slice( str_split( $h, $dlen ), 0, $depth );

		$t = $root . implode( DIRECTORY_SEPARATOR, $p ) . DIRECTORY_SEPARATOR;
		
		if ( $create && !is_dir( $t ) ) {
			$s = $root;
			foreach ( $p as $d ) {
				$s .= $d . DIRECTORY_SEPARATOR;
				if ( is_dir( $s ) ) { continue; }
				
				/**
				 * Read and write for owner, nothing for everybody else
				 */
				mkdir( $s, 0600 );
			}
		}
		
		return $t . $h;
	}
	
	public static function cleanup( $root, $exp ) {
		$t	= time();
		$it	= new \RecursiveDirectoryIterator(
				new \RecursiveDirectoryIterator(
					$root, \FilesystemIterator::SKIP_DOTS
				), 
				\RecursiveIteratorIterator::CHILD_FIRST
			);
		
		foreach( $it as $obj ) {
			if ( $obj->isDir() ) {
				self::del( $obj->getPathname(), true );
				continue;
			}
			if ( $exp ) {
				if ( $t - $obj->getMTime() > $exp ) {
					self::del( $f->getRealPath() );
				}
			} else {
				self::del( $f->getRealPath() );
			}
		}
	}
	
	private static function del( $file, $dir = false ) {
		try {
			if ( $dir ) {
				rmdir( $file );
			} else {
				unlink( $file );
			}
		} catch ( \Exception $e ) {
			return false;
		}
		
		return true;
	}
}
