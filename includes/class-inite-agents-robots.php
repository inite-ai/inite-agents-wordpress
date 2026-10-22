<?php
/**
 * robots.txt: the Content-Signal line the service published, placed in the
 * `User-agent: *` group of the robots.txt WordPress generates.
 *
 * WordPress only generates robots.txt when no physical file exists; with
 * one, the settings screen shows the line to paste instead.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * robots.txt filter.
 */
final class Inite_Agents_Robots {

	/** Hook in, only while the feature is on. */
	public static function register() {
		if ( Inite_Agents_Settings::on( 'robots' ) ) {
			add_filter( 'robots_txt', array( __CLASS__, 'filter' ), 20, 2 );
			add_action( 'do_robotstxt', array( __CLASS__, 'count' ) );
		}
	}

	/**
	 * The stored Content-Signal lines, or ''.
	 *
	 * @return string
	 */
	public static function line() {
		$file = Inite_Agents_Store::file( 'robots-signal' );
		if ( ! $file ) {
			return '';
		}
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $file['body'] ) as $l ) {
			$l = trim( $l );
			if ( preg_match( '/^Content-Signal:\s*[a-z0-9=,\s-]+$/i', $l ) ) {
				$lines[] = $l;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Add the line to the `User-agent: *` group, or a group of its own.
	 *
	 * @param string $output The robots.txt WordPress built.
	 * @param bool   $is_public Whether the site is public (unused: the line says how content may be used, not whether to crawl).
	 * @return string
	 */
	public static function filter( $output, $is_public = true ) {
		unset( $is_public );
		$line = self::line();
		if ( '' === $line || false !== stripos( (string) $output, $line ) ) {
			return $output;
		}
		$output = (string) $output;
		$placed = preg_replace( '/^(User-agent:\s*\*[ \t]*)$/mi', '$1' . "\n" . $line, $output, 1, $count );
		if ( $count > 0 && is_string( $placed ) ) {
			return $placed;
		}
		return rtrim( $output ) . "\n\nUser-agent: *\n" . $line . "\n";
	}

	/** Count an agent reading robots.txt, as the ping reports. */
	public static function count() {
		if ( '' !== self::line() ) {
			Inite_Agents_Visitors::count( '/robots.txt' );
		}
	}

	/**
	 * Whether a physical robots.txt takes the place of the generated one.
	 *
	 * @return bool
	 */
	public static function physical() {
		return Inite_Agents_Conflicts::physical( '/robots.txt' );
	}
}
