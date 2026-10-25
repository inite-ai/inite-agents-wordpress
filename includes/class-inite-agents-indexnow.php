<?php
/**
 * IndexNow: when a public page, post or product is published, changed or
 * taken down, the search engines that share the protocol (Bing, Yandex,
 * Seznam, Naver and others) are told its address at once instead of finding
 * out at their next crawl. Google does not use IndexNow.
 *
 * Off until the owner switches it on. Stands down when another plugin
 * already speaks IndexNow for the site (Bing's own plugin, Yoast, Rank Math,
 * SEOPress, All in One SEO), so the engines are not told twice.
 *
 * The key is made on this site (32 hex characters) and served at
 * /<key>.txt, which is how the engines check that the site sent it.
 * Addresses wait in a queue and go a minute later in one request of at most
 * BATCH addresses, at most one request every GAP seconds and DAILY addresses
 * a day. What is sent: the site's host, the key, where the key is served and
 * the addresses. Nothing about visitors.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IndexNow pings.
 */
final class Inite_Agents_Indexnow {

	const OPTION   = 'inite_agents_indexnow';
	const CRON     = 'inite_agents_indexnow';
	const ENDPOINT = 'https://api.indexnow.org/indexnow';
	/** Addresses a request carries at most (the protocol allows 10 000). */
	const BATCH = 100;
	/** Seconds between two requests at least. */
	const GAP = 300;
	/** Addresses sent a day at most. */
	const DAILY = 500;
	/** Addresses held in the queue at most. */
	const QUEUE = 1000;

	/** Hook in, only while the feature is on. */
	public static function register() {
		add_action( self::CRON, array( __CLASS__, 'flush' ) );
		if ( ! Inite_Agents_Settings::on( 'indexnow' ) ) {
			return;
		}
		add_filter( 'do_parse_request', array( __CLASS__, 'maybe_serve_key' ), 1 );
		if ( '' === self::other() ) {
			add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 20, 3 );
		}
	}

	/**
	 * The stored state with defaults.
	 *
	 * @return array{key:string,queue:string[],day:string,sent_today:int,last:array}
	 */
	public static function state() {
		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'key'        => isset( $raw['key'] ) && is_string( $raw['key'] ) && preg_match( '/^[a-f0-9]{32}$/', $raw['key'] ) ? $raw['key'] : '',
			'queue'      => isset( $raw['queue'] ) && is_array( $raw['queue'] ) ? array_values( array_filter( $raw['queue'], 'is_string' ) ) : array(),
			'day'        => isset( $raw['day'] ) && is_string( $raw['day'] ) ? $raw['day'] : '',
			'sent_today' => isset( $raw['sent_today'] ) ? (int) $raw['sent_today'] : 0,
			'last'       => isset( $raw['last'] ) && is_array( $raw['last'] ) ? $raw['last'] : array(),
		);
	}

	/**
	 * Write the state, never autoloaded.
	 *
	 * @param array $state State.
	 */
	private static function save( array $state ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $state, '', false );
		} else {
			update_option( self::OPTION, $state, false );
		}
	}

	/**
	 * The site's key, made on first use.
	 *
	 * @return string
	 */
	public static function key() {
		$state = self::state();
		if ( '' === $state['key'] ) {
			$state['key'] = bin2hex( random_bytes( 16 ) );
			self::save( $state );
		}
		return $state['key'];
	}

	/**
	 * The plugin that already speaks IndexNow for this site, or ''.
	 *
	 * @return string
	 */
	public static function other() {
		if ( class_exists( 'BWT_IndexNow' ) || defined( 'BWT_INDEXNOW_PLUGIN_VERSION' ) || ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'indexnow/indexnow-url-submission.php' ) ) || in_array( 'indexnow/indexnow-url-submission.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			return 'IndexNow (Microsoft Bing)';
		}
		// Yoast stores the switch in its free plugin too, on by default, but only Yoast SEO Premium sends IndexNow.
		if ( defined( 'WPSEO_PREMIUM_VERSION' ) ) {
			$wpseo = get_option( 'wpseo', array() );
			if ( is_array( $wpseo ) && ! empty( $wpseo['enable_index_now'] ) ) {
				return 'Yoast SEO';
			}
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = get_option( 'rank_math_modules', array() );
			if ( is_array( $modules ) && in_array( 'instant-indexing', $modules, true ) ) {
				return 'Rank Math';
			}
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$seopress = get_option( 'seopress_instant_indexing_option_name', array() );
			if ( is_array( $seopress ) && ! empty( $seopress['seopress_instant_indexing_automate_submission'] ) ) {
				return 'SEOPress';
			}
		}
		if ( defined( 'AIOSEO_VERSION' ) && function_exists( 'aioseo' ) ) {
			$aioseo = json_decode( (string) get_option( 'aioseo_options_pro', '' ), true );
			if ( is_array( $aioseo ) && ! empty( $aioseo['advanced']['indexNow'] ) ) {
				return 'All in One SEO';
			}
		}
		return (string) apply_filters( 'inite_agents_indexnow_owner', '' );
	}

	/**
	 * Whether a post's address is one the engines should hear about.
	 *
	 * @param WP_Post $post The post.
	 * @return bool
	 */
	public static function eligible( $post ) {
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || '' !== $post->post_password ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || empty( $type->public ) || 'attachment' === $post->post_type ) {
			return false;
		}
		// Yoast and Rank Math store "noindex" on the post; a page kept out of search is not announced.
		if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return false;
		}
		$rank_math = get_post_meta( $post->ID, 'rank_math_robots', true );
		return ! ( is_array( $rank_math ) && in_array( 'noindex', $rank_math, true ) );
	}

	/**
	 * A post moved into, within or out of "publish": its address goes in the queue.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       The post.
	 */
	public static function transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		if ( ! self::eligible( $post ) ) {
			return;
		}
		$known = $post;
		if ( 'publish' !== $new_status ) {
			// Off the site now: its permalink falls back to ?p=, and a trashed slug gains "__trashed". The engines know the address it had.
			$known              = clone $post;
			$known->post_status = 'publish';
			$known->post_name   = preg_replace( '/__trashed$/', '', (string) $known->post_name );
		}
		self::queue( (string) get_permalink( $known ) );
	}

	/**
	 * Put an address in the queue and the flush a minute out.
	 *
	 * @param string $url Address on this site.
	 */
	public static function queue( $url ) {
		$url  = esc_url_raw( $url );
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( '' === $url || wp_parse_url( $url, PHP_URL_HOST ) !== $host ) {
			return;
		}
		$state = self::state();
		if ( ! in_array( $url, $state['queue'], true ) ) {
			$state['queue'][] = $url;
			$state['queue']   = array_slice( $state['queue'], -self::QUEUE );
			self::save( $state );
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON );
		}
	}

	/**
	 * Where the pings go: the shared IndexNow endpoint, or a test stub.
	 *
	 * @return string
	 */
	public static function endpoint() {
		return esc_url_raw( (string) apply_filters( 'inite_agents_indexnow_endpoint', self::ENDPOINT ) );
	}

	/**
	 * Send one batch from the queue, within the gap and the daily cap; schedule the next if more wait.
	 *
	 * @return array|null The result recorded, or null when nothing went.
	 */
	public static function flush() {
		if ( ! Inite_Agents_Settings::on( 'indexnow' ) || '' !== self::other() ) {
			return null;
		}
		$state = self::state();
		$today = gmdate( 'Y-m-d' );
		if ( $state['day'] !== $today ) {
			$state['day']        = $today;
			$state['sent_today'] = 0;
		}
		if ( ! $state['queue'] ) {
			return null;
		}
		$last_at = isset( $state['last']['at'] ) ? (int) $state['last']['at'] : 0;
		$room    = min( self::BATCH, self::DAILY - $state['sent_today'] );
		if ( time() - $last_at < self::GAP || $room <= 0 ) {
			$next = $room <= 0 ? strtotime( 'tomorrow', time() ) + 60 : $last_at + self::GAP + 5;
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_single_event( $next, self::CRON );
			}
			self::save( $state );
			return null;
		}
		$urls = array_slice( $state['queue'], 0, $room );
		$key  = self::key();
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$res  = wp_remote_post(
			self::endpoint(),
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode(
					array(
						'host'        => $host,
						'key'         => $key,
						'keyLocation' => home_url( '/' . $key . '.txt' ),
						'urlList'     => $urls,
					)
				),
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		// 200 and 202 are accepted; 4xx means the request itself is wrong and is not retried; anything else waits for the next try.
		$done = ( $code >= 200 && $code < 300 ) || ( $code >= 400 && $code < 500 && 429 !== $code );
		// Read again: a post saved while the request was out added to the queue.
		$fresh               = self::state();
		$fresh['day']        = $state['day'];
		$fresh['sent_today'] = $state['sent_today'];
		$fresh['last']       = array(
			'at'     => time(),
			'status' => $code,
			'count'  => count( $urls ),
			'error'  => is_wp_error( $res ) ? sanitize_text_field( $res->get_error_message() ) : '',
		);
		if ( $done ) {
			$fresh['queue']       = array_values( array_diff( $fresh['queue'], $urls ) );
			$fresh['sent_today'] += count( $urls );
		}
		$state = $fresh;
		self::save( $state );
		if ( $state['queue'] && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + self::GAP + 5, self::CRON );
		}
		return $state['last'];
	}

	/**
	 * Answer /<key>.txt with the key, as the engines check it.
	 *
	 * @param bool $parse Whether WordPress should parse the request.
	 * @return bool
	 */
	public static function maybe_serve_key( $parse ) {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return $parse;
		}
		$path  = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$state = self::state();
		if ( '' === $state['key'] || (string) wp_parse_url( home_url( '/' . $state['key'] . '.txt' ), PHP_URL_PATH ) !== $path ) {
			return $parse;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		echo esc_html( $state['key'] );
		exit;
	}
}
