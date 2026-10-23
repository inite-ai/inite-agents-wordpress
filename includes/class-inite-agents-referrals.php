<?php
/**
 * People who came to the site from an AI assistant, counted per day, page
 * and assistant.
 *
 * A front-end page view is counted when its referrer's host, or its
 * `utm_source` (chatgpt.com adds `utm_source=chatgpt.com` to the links it
 * gives), is one of SOURCES. Nothing else about the request is kept: no
 * address, no user agent, no cookie, no query string; the page is its path.
 * Only while "Daily report" is on, which is off until the owner switches it
 * on; the ping sends the last seven days (readme.txt, "External services").
 * The counts stay on the site for thirty days, for the plugin's own Overview.
 *
 * A page served from a full-page cache never reaches PHP, so it is not
 * counted: the numbers are a floor, and the screen says so.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI referral counter.
 */
final class Inite_Agents_Referrals {

	const OPTION = 'inite_agents_referrals';
	const DAYS   = 30;
	/** Days the ping sends, as the service keeps (lib/site-facts/referrals.ts). */
	const SENT_DAYS = 7;
	/** Distinct pages a day; past it, a page is counted as OTHER. */
	const PATHS_A_DAY = 30;
	const OTHER       = '/(other)';

	/**
	 * The assistants counted: the host reported, and the hosts (or utm_source values) that mean it.
	 * Held equal to REFERRAL_SOURCES in lib/site-facts/referrals.ts by the plugin's tests.
	 */
	const SOURCES = array(
		'chatgpt.com'           => array( 'chatgpt.com', 'chat.openai.com' ),
		'perplexity.ai'         => array( 'perplexity.ai', 'www.perplexity.ai' ),
		'gemini.google.com'     => array( 'gemini.google.com', 'bard.google.com' ),
		'copilot.microsoft.com' => array( 'copilot.microsoft.com', 'copilot.cloud.microsoft' ),
		'claude.ai'             => array( 'claude.ai' ),
		'chat.deepseek.com'     => array( 'chat.deepseek.com' ),
		'grok.com'              => array( 'grok.com' ),
		'meta.ai'               => array( 'meta.ai', 'www.meta.ai' ),
		'chat.mistral.ai'       => array( 'chat.mistral.ai' ),
		'you.com'               => array( 'you.com' ),
		'phind.com'             => array( 'phind.com', 'www.phind.com' ),
	);

	/** Hook in, only while the daily report is on. */
	public static function register() {
		if ( Inite_Agents_Settings::on( 'ping' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'count' ), 99 );
		}
	}

	/**
	 * The assistant a referrer or utm_source names, or null.
	 *
	 * @param string $referer    The Referer header.
	 * @param string $utm_source The utm_source parameter.
	 * @return string|null
	 */
	public static function source( $referer, $utm_source = '' ) {
		$host = strtolower( (string) wp_parse_url( (string) $referer, PHP_URL_HOST ) );
		$utm  = strtolower( trim( (string) $utm_source ) );
		foreach ( self::SOURCES as $name => $hosts ) {
			if ( ( '' !== $host && in_array( $host, $hosts, true ) ) || ( '' !== $utm && in_array( $utm, $hosts, true ) ) ) {
				return $name;
			}
		}
		return null;
	}

	/** Count this page view if an assistant sent it. */
	public static function count() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_404() || is_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method || current_user_can( 'edit_posts' ) ) {
			return;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( null !== Inite_Agents_Visitors::agent( $ua ) ) {
			return;
		}
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$utm     = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- counted, not acted on.
		$source  = self::source( $referer, $utm );
		if ( null === $source ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		self::add( gmdate( 'Y-m-d' ), self::path( $uri ), $source );
	}

	/**
	 * The page's path: no query, no fragment, no spaces, at most 200 characters.
	 *
	 * @param string $uri The request URI.
	 * @return string
	 */
	public static function path( $uri ) {
		$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
		$path = '/' . ltrim( preg_replace( '/\s+/', '', $path ), '/' );
		return substr( $path, 0, 200 );
	}

	/**
	 * One more for a day, a page and an assistant.
	 *
	 * @param string $day    Y-m-d.
	 * @param string $path   Path.
	 * @param string $source Assistant.
	 */
	public static function add( $day, $path, $source ) {
		$all = self::all();
		if ( ! isset( $all[ $day ][ $path ] ) && isset( $all[ $day ] ) && count( $all[ $day ] ) >= self::PATHS_A_DAY ) {
			$path = self::OTHER;
		}
		$prev                           = isset( $all[ $day ][ $path ][ $source ] ) ? (int) $all[ $day ][ $path ][ $source ] : 0;
		$all[ $day ][ $path ][ $source ] = $prev + 1;
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $all, '', false );
		} else {
			update_option( self::OPTION, $all, false );
		}
	}

	/**
	 * Every count kept, trimmed to the last DAYS days.
	 *
	 * @return array<string,array<string,array<string,int>>> day => path => source => hits.
	 */
	public static function all() {
		$all    = get_option( self::OPTION, array() );
		$all    = is_array( $all ) ? $all : array();
		$oldest = gmdate( 'Y-m-d', time() - ( self::DAYS - 1 ) * DAY_IN_SECONDS );
		foreach ( array_keys( $all ) as $day ) {
			if ( ! is_string( $day ) || $day < $oldest ) {
				unset( $all[ $day ] );
			}
		}
		return $all;
	}

	/**
	 * The counts as the ping sends them: the last SENT_DAYS days, one row per day, page and assistant, sixty at most.
	 *
	 * @return array<int,array{day:string,path:string,source:string,hits:int}>
	 */
	public static function rows() {
		$oldest = gmdate( 'Y-m-d', time() - ( self::SENT_DAYS - 1 ) * DAY_IN_SECONDS );
		$rows   = array();
		foreach ( self::all() as $day => $paths ) {
			if ( $day < $oldest ) {
				continue;
			}
			foreach ( $paths as $path => $sources ) {
				foreach ( $sources as $source => $hits ) {
					$rows[] = array(
						'day'    => (string) $day,
						'path'   => (string) $path,
						'source' => (string) $source,
						'hits'   => (int) $hits,
					);
				}
			}
		}
		return array_slice( $rows, -60 );
	}

	/**
	 * Thirty days summed, for the Overview: the total, by assistant and by page, largest first.
	 *
	 * @return array{total:int,by_source:array<string,int>,by_path:array<string,int>}
	 */
	public static function totals() {
		$by_source = array();
		$by_path   = array();
		$total     = 0;
		foreach ( self::all() as $paths ) {
			foreach ( $paths as $path => $sources ) {
				foreach ( $sources as $source => $hits ) {
					$total                += (int) $hits;
					$by_source[ $source ]  = ( isset( $by_source[ $source ] ) ? $by_source[ $source ] : 0 ) + (int) $hits;
					$by_path[ $path ]      = ( isset( $by_path[ $path ] ) ? $by_path[ $path ] : 0 ) + (int) $hits;
				}
			}
		}
		arsort( $by_source );
		arsort( $by_path );
		return array(
			'total'     => $total,
			'by_source' => $by_source,
			'by_path'   => array_slice( $by_path, 0, 10, true ),
		);
	}
}
