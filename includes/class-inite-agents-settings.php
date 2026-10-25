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

	/** What the site sends to INITE, each switched on by the owner (Inite_Agents_Admin_Sending lists what each sends). */
	const SENDS = array( 'send_basics', 'send_content', 'send_products', 'send_events', 'send_services', 'send_locations' );

	/** The features the owner switches. None does anything until a key is saved. */
	const FEATURES = array( 'files', 'robots', 'head', 'abilities', 'ping', 'content', 'indexnow', 'survey', 'send_basics', 'send_content', 'send_products', 'send_events', 'send_services', 'send_locations' );

	/**
	 * Features that stay off until the owner switches them on: the daily
	 * report sends INITE counts from this site, articles write into it, and
	 * nothing about the site's own content leaves it until its switch is on.
	 * IndexNow tells search engines about new addresses, and the survey asks
	 * the site's visitors a question: both are the owner's choice.
	 */
	const OFF_BY_DEFAULT = array( 'ping', 'content', 'indexnow', 'survey', 'send_basics', 'send_content', 'send_products', 'send_events', 'send_services', 'send_locations' );

	/**
	 * The one-click fixes (Inite_Agents_Fixes). The two JSON-LD types the
	 * page head printed before 0.7.0 start as the "Page head" switch was, so
	 * an update changes nothing; every other fix is off until switched on.
	 */
	const FIXES = array( 'schema_business', 'schema_faq', 'schema_products', 'robots_policy', 'meta_description', 'sitemap_line' );

	/**
	 * What the robots.txt policy may block, each only when the owner ticks it.
	 * Nothing is blocked by default: assistants answer from search, from pages
	 * they open, and from what they were trained on. Google-Extended is its
	 * own choice, because blocking it takes the site out of the Gemini app.
	 */
	const ROBOTS_BLOCK = array( 'training', 'google_extended', 'search', 'answer' );

	/**
	 * Cached settings for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * The settings with defaults filled in.
	 *
	 * @return array{key:string,features:array<string,bool>,content_status:string,fixes:array<string,bool>,robots_block:array<string,bool>}
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
		$fixes = array();
		foreach ( self::FIXES as $fix ) {
			$was           = in_array( $fix, array( 'schema_business', 'schema_faq' ), true ) ? $features['head'] : false;
			$fixes[ $fix ] = isset( $raw['fixes'][ $fix ] ) ? (bool) $raw['fixes'][ $fix ] : $was;
		}
		$block = array();
		foreach ( self::ROBOTS_BLOCK as $group ) {
			$block[ $group ] = isset( $raw['robots_block'][ $group ] ) ? (bool) $raw['robots_block'][ $group ] : false;
		}
		// Before 0.10.0 Google-Extended was one of the training crawlers: a site that blocked those keeps blocking it until the owner says otherwise.
		if ( isset( $raw['robots_block']['training'] ) && ! isset( $raw['robots_block']['google_extended'] ) ) {
			$block['google_extended'] = (bool) $raw['robots_block']['training'];
		}
		self::$cache = array(
			'key'            => isset( $raw['key'] ) && is_string( $raw['key'] ) ? $raw['key'] : '',
			'features'       => $features,
			'content_status' => isset( $raw['content_status'] ) && 'publish' === $raw['content_status'] ? 'publish' : 'draft',
			'fixes'          => $fixes,
			'robots_block'   => $block,
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
	 * Whether a fix is on: a key is saved and the owner switched it on.
	 *
	 * @param string $fix One of FIXES.
	 * @return bool
	 */
	public static function fix( $fix ) {
		$s = self::get();
		return '' !== $s['key'] && ! empty( $s['fixes'][ $fix ] );
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
		// The Fixes tab posts only the fixes: everything else stays as it was.
		if ( isset( $input['_tab'] ) && 'fixes' === $input['_tab'] ) {
			$fixes = array();
			foreach ( self::FIXES as $fix ) {
				$fixes[ $fix ] = ! empty( $input['fixes'][ $fix ] );
			}
			$block = array();
			foreach ( self::ROBOTS_BLOCK as $group ) {
				$block[ $group ] = ! empty( $input['robots_block'][ $group ] );
			}
			self::flush();
			return array_merge( $old, array( 'fixes' => $fixes, 'robots_block' => $block ) );
		}
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
			'fixes'          => $old['fixes'],
			'robots_block'   => $old['robots_block'],
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
