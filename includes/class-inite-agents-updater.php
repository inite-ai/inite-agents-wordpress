<?php
/**
 * Updates from inite.ai, for the copy downloaded from there.
 *
 * The header's `Update URI: https://inite.ai/downloads/inite-agents` tells
 * WordPress (5.8+) that WordPress.org does not own this plugin, and to ask
 * the `update_plugins_inite.ai` filter instead. The filter reads a small
 * JSON the build writes next to the zip (scripts/build-wp-plugin.mjs):
 *
 *   { "version": "0.3.0", "download_url": "https://inite.ai/downloads/inite-agents.zip?v=0.3.0",
 *     "requires": "6.0", "requires_php": "7.4", "tested": "7.1", "changelog": "<h4>0.3.0</h4>…" }
 *
 * It is read only when WordPress checks for updates (its own twice-daily
 * check, or the Updates screen), at most once every twelve hours, with a
 * five-second limit, never while a page renders. Nothing is sent but the
 * request itself: no key, no site address beyond what any HTTP request
 * carries. A package is taken only from an https address on the same host
 * as the JSON.
 *
 * The WordPress.org build leaves this file and the Update URI header out
 * (scripts/build-wp-plugin.mjs --org): a plugin hosted there is updated by
 * WordPress.org alone.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-hosted update check.
 */
final class Inite_Agents_Updater {

	/** The JSON the build writes beside the zip. */
	const INFO_URL = 'https://inite.ai/downloads/inite-agents.json';

	/** Where the answer is kept between checks. */
	const TRANSIENT = 'inite_agents_update';

	/** Hook the update check and the "View details" box. */
	public static function register() {
		add_filter( 'update_plugins_inite.ai', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
	}

	/** The plugin's basename, e.g. inite-agents/inite-agents.php. */
	private static function basename() {
		return plugin_basename( INITE_AGENTS_FILE );
	}

	/**
	 * The update info, from the cache or from inite.ai.
	 *
	 * @return array|null Version, package and the rest, or null when unknown.
	 */
	public static function info() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return empty( $cached['version'] ) ? null : $cached;
		}
		$url  = (string) apply_filters( 'inite_agents_update_info_url', self::INFO_URL );
		$got  = wp_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => 65536,
				'headers'             => array( 'Accept' => 'application/json' ),
			)
		);
		$info = null;
		if ( ! is_wp_error( $got ) && 200 === (int) wp_remote_retrieve_response_code( $got ) ) {
			$info = self::parse( json_decode( (string) wp_remote_retrieve_body( $got ), true ), $url );
		}
		// A failure is kept for an hour, so the Updates screen does not ask again on every load.
		set_transient( self::TRANSIENT, $info ? $info : array(), $info ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $info;
	}

	/**
	 * The JSON reduced to what WordPress reads, or null when it does not read.
	 *
	 * @param mixed  $raw      Decoded JSON.
	 * @param string $info_url Where it came from: the package must be on the same host.
	 * @return array|null
	 */
	public static function parse( $raw, $info_url ) {
		if ( ! is_array( $raw ) || empty( $raw['version'] ) || empty( $raw['download_url'] ) ) {
			return null;
		}
		$version = (string) $raw['version'];
		$package = (string) $raw['download_url'];
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return null;
		}
		$host = wp_parse_url( $info_url, PHP_URL_HOST );
		if ( 'https' !== wp_parse_url( $package, PHP_URL_SCHEME ) || ! $host || wp_parse_url( $package, PHP_URL_HOST ) !== $host ) {
			return null;
		}
		$text = static function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
		};
		return array(
			'version'      => $version,
			'package'      => $package,
			'requires'     => $text( 'requires' ),
			'requires_php' => $text( 'requires_php' ),
			'tested'       => $text( 'tested' ),
			'changelog'    => $text( 'changelog' ),
		);
	}

	/**
	 * `update_plugins_inite.ai`: what WordPress shows on the Plugins and Updates screens.
	 *
	 * @param array|false $update      An answer another filter gave, or false.
	 * @param array       $plugin_data The plugin's headers.
	 * @param string      $plugin_file The plugin's basename.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( self::basename() !== $plugin_file || ! empty( $update ) ) {
			return $update;
		}
		$info = self::info();
		if ( ! $info ) {
			return $update;
		}
		return array(
			'id'           => 'https://inite.ai/downloads/inite-agents',
			'slug'         => 'inite-agents',
			'plugin'       => $plugin_file,
			'version'      => $info['version'],
			'url'          => 'https://inite.ai/',
			'package'      => $info['package'],
			'requires'     => $info['requires'],
			'requires_php' => $info['requires_php'],
			'tested'       => $info['tested'],
		);
	}

	/**
	 * `plugins_api`: the "View details" box, with the changelog.
	 *
	 * @param false|object|array $result The answer so far.
	 * @param string             $action The API action.
	 * @param object             $args   Its arguments.
	 * @return false|object|array
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || 'inite-agents' !== $args->slug ) {
			return $result;
		}
		$info = self::info();
		if ( ! $info ) {
			return $result;
		}
		return (object) array(
			'name'          => 'INITE Agents',
			'slug'          => 'inite-agents',
			'version'       => $info['version'],
			'author'        => '<a href="https://inite.ai/">INITE</a>',
			'homepage'      => 'https://inite.ai/',
			'requires'      => $info['requires'],
			'requires_php'  => $info['requires_php'],
			'tested'        => $info['tested'],
			'download_link' => $info['package'],
			'sections'      => array( 'changelog' => wp_kses_post( $info['changelog'] ) ),
		);
	}
}
