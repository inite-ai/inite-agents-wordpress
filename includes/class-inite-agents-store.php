<?php
/**
 * The last good copy of what the service published for this site.
 *
 * One option, never autoloaded, written whole after a sync succeeded:
 * the manifest and its ETag, the bodies of the files, and the public facts
 * the abilities answer from, all of one facts version. A failed sync never
 * touches it, so the site keeps serving the last good copy.
 *
 * The state of the last attempt (when, and the error if any) lives apart
 * in a second small option, so a failure does not rewrite the copy.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store: the copy and the sync status.
 */
final class Inite_Agents_Store {

	const OPTION = 'inite_agents_store';
	const STATUS = 'inite_agents_status';

	/**
	 * Per-request copy.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * The stored copy, or an empty array when nothing was synced yet.
	 *
	 * @return array{version?:int,etag?:string,manifest?:array,files?:array,facts?:array,synced_at?:int}
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$v           = get_option( self::OPTION, array() );
			self::$cache = is_array( $v ) ? $v : array();
		}
		return self::$cache;
	}

	/**
	 * Replace the copy.
	 *
	 * @param array $copy The new copy.
	 */
	public static function put( array $copy ) {
		self::$cache = $copy;
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $copy, '', false );
		} else {
			update_option( self::OPTION, $copy, false );
		}
	}

	/** Drop the copy: the service said this site must not publish. */
	public static function clear() {
		self::$cache = array();
		delete_option( self::OPTION );
	}

	/**
	 * One stored file by its service name, or null.
	 *
	 * @param string $name File name (llms.txt, agent-card.json, ...).
	 * @return array{body:string,type:string,path:?string,etag:string}|null
	 */
	public static function file( $name ) {
		$copy = self::get();
		return isset( $copy['files'][ $name ] ) && is_array( $copy['files'][ $name ] ) ? $copy['files'][ $name ] : null;
	}

	/**
	 * The status of the last sync attempt.
	 *
	 * @return array{checked_at?:int,ok?:bool,error?:string,code?:string}
	 */
	public static function status() {
		$v = get_option( self::STATUS, array() );
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Record the last attempt.
	 *
	 * @param array $status The status.
	 */
	public static function set_status( array $status ) {
		if ( false === get_option( self::STATUS, false ) ) {
			add_option( self::STATUS, $status, '', false );
		} else {
			update_option( self::STATUS, $status, false );
		}
	}

	/** Forget the per-request copy. */
	public static function flush() {
		self::$cache = null;
	}
}
