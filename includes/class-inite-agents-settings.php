<?php
/**
 * The owner's settings: the site key and which features are on.
 *
 * One small autoloaded option, read on every request to decide whether a
 * feature claims anything at all. The bulky copy of the files lives in
 * Inite_Agents_Store, which is never autoloaded.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings: read, defaults, sanitize.
 */
final class Inite_Agents_Settings {

	const OPTION = 'inite_agents_settings';

	/** The features the owner switches, each off by default until a key is saved. */
	const FEATURES = array( 'files', 'robots', 'head', 'abilities', 'ping', 'content' );

	/** Features that stay off until the owner switches them on: articles write into the site. */
	const OFF_BY_DEFAULT = array( 'content' );

	/**
	 * Cached settings for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * The settings with defaults filled in.
	 *
	 * @return array{key:string,features:array<string,bool>}
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$raw      = get_option( self::OPTION, array() );
		$raw      = is_array( $raw ) ? $raw : array();
		$features = array();
		foreach ( self::FEATURES as $feature ) {
			$features[ $feature ] = isset( $raw['features'][ $feature ] ) ? (bool) $raw['features'][ $feature ] : ! in_array( $feature, self::OFF_BY_DEFAULT, true );
		}
		self::$cache = array(
			'key'      => isset( $raw['key'] ) && is_string( $raw['key'] ) ? $raw['key'] : '',
			'features'       => $features,
			'content_status' => isset( $raw['content_status'] ) && 'publish' === $raw['content_status'] ? 'publish' : 'draft',
		);
		return self::$cache;
	}

	/** Forget the per-request copy after a write. */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Whether a feature is on: a key is saved and the owner left it switched on.
	 *
	 * @param string $feature One of FEATURES.
	 * @return bool
	 */
	public static function on( $feature ) {
		$s = self::get();
		return '' !== $s['key'] && ! empty( $s['features'][ $feature ] );
	}

	/**
	 * The site key's shape, as the service issues it.
	 *
	 * @param string $key Candidate key.
	 * @return bool
	 */
	public static function is_key( $key ) {
		return is_string( $key ) && 1 === preg_match( '/^isk_[A-Za-z0-9_-]{32}$/', $key );
	}

	/**
	 * Sanitize callback for register_setting.
	 *
	 * @param mixed $input The submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old   = self::get();
		$input = is_array( $input ) ? $input : array();
		$typed = isset( $input['key'] ) ? trim( sanitize_text_field( wp_unslash( $input['key'] ) ) ) : '';
		// The field is left empty to keep the saved key: the key is never printed back into the page.
		$key = '' === $typed ? $old['key'] : $typed;
		if ( ! empty( $input['forget_key'] ) ) {
			$key = '';
		}
		if ( '' !== $key && ! self::is_key( $key ) ) {
			add_settings_error( self::OPTION, 'inite_agents_bad_key', __( 'That does not look like a site key. It starts with isk_ and is 36 characters long.', 'inite-agents' ) );
			$key = $old['key'];
		}
		$features = array();
		foreach ( self::FEATURES as $feature ) {
			$features[ $feature ] = ! empty( $input['features'][ $feature ] );
		}
		self::flush();
		return array(
			'key'            => $key,
			'features'       => $features,
			'content_status' => isset( $input['content_status'] ) && 'publish' === $input['content_status'] ? 'publish' : 'draft',
		);
	}

	/**
	 * Where the INITE service lives. A constant or a filter can point it at a
	 * staging server; the key is only ever sent to this origin.
	 *
	 * @return string Origin without a trailing slash.
	 */
	public static function api_base() {
		$base = defined( 'INITE_AGENTS_API' ) ? (string) INITE_AGENTS_API : 'https://inite.ai';
		$base = (string) apply_filters( 'inite_agents_api_base', $base );
		return untrailingslashit( esc_url_raw( $base ) );
	}
}
