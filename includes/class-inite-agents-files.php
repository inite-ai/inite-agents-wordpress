<?php
/**
 * The root files: /llms.txt and three files under /.well-known, served
 * from the stored copy with the content types the manifest gave.
 *
 * There are no rewrite rules. The request's path is compared on
 * `do_parse_request`, before WordPress parses the query, so nothing is
 * left behind in the permalinks when the feature or the plugin is off, and
 * nothing needs a flush. While the feature is off, the hook is not even
 * added.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Root file server.
 */
final class Inite_Agents_Files {

	/** Hook in, only while the feature is on. */
	public static function register() {
		if ( Inite_Agents_Settings::on( 'files' ) ) {
			add_filter( 'do_parse_request', array( __CLASS__, 'maybe_serve' ), 1 );
		}
	}

	/**
	 * The root paths the stored copy has files for, each with its file.
	 *
	 * @return array<string,array> path => file.
	 */
	public static function by_path() {
		$copy  = Inite_Agents_Store::get();
		$paths = array();
		foreach ( isset( $copy['files'] ) && is_array( $copy['files'] ) ? $copy['files'] : array() as $file ) {
			if ( ! empty( $file['path'] ) && in_array( $file['path'], Inite_Agents_Sync::ROOT_PATHS, true ) ) {
				$paths[ $file['path'] ] = $file;
			}
		}
		return $paths;
	}

	/**
	 * The paths this site serves right now: on, stored, and nobody else's.
	 *
	 * @return string[]
	 */
	public static function served() {
		if ( ! Inite_Agents_Settings::on( 'files' ) || ! Inite_Agents_Conflicts::at_root() ) {
			return array();
		}
		$out = array();
		foreach ( array_keys( self::by_path() ) as $path ) {
			if ( '' === Inite_Agents_Conflicts::owner( $path ) ) {
				$out[] = $path;
			}
		}
		return $out;
	}

	/**
	 * The request's path relative to the site's home, or null.
	 *
	 * @return string|null
	 */
	private static function request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return null;
		}
		$uri  = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home ) {
			if ( 0 !== strpos( $path, $home . '/' ) ) {
				return null;
			}
			$path = substr( $path, strlen( $home ) );
		}
		return $path;
	}

	/**
	 * Serve a stored file when the request asks for its path.
	 *
	 * @param bool $parse Whether WordPress should go on parsing the request.
	 * @return bool
	 */
	public static function maybe_serve( $parse ) {
		$path = self::request_path();
		if ( null === $path || ! in_array( $path, Inite_Agents_Sync::ROOT_PATHS, true ) ) {
			return $parse;
		}
		$files = self::by_path();
		if ( ! isset( $files[ $path ] ) || ! Inite_Agents_Conflicts::at_root() || '' !== Inite_Agents_Conflicts::owner( $path ) ) {
			return $parse;
		}
		Inite_Agents_Visitors::count( $path );
		self::send( $files[ $path ] );
		exit;
	}

	/**
	 * Send one file, or 304 when the caller holds it.
	 *
	 * @param array $file The stored file.
	 */
	private static function send( array $file ) {
		$etag = (string) $file['etag'];
		status_header( 200 );
		header( 'Content-Type: ' . $file['type'] );
		header( 'Cache-Control: public, max-age=300' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'X-Content-Type-Options: nosniff' );
		if ( '' !== $etag ) {
			header( 'ETag: ' . $etag );
			$held = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
			if ( '' !== $held && in_array( $etag, array_map( 'trim', explode( ',', $held ) ), true ) ) {
				status_header( 304 );
				return;
			}
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		header( 'Content-Length: ' . strlen( $file['body'] ) );
		if ( 'HEAD' === $method ) {
			return;
		}
		// The body is the file itself, served with its own content type and nosniff; HTML escaping would change its bytes.
		echo $file['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
