<?php
/**
 * The one-click fixes besides the JSON-LD (Inite_Agents_Schema): what an
 * analysis found missing that WordPress itself can put right. Each is a
 * switch in Settings → INITE Agents → Fixes, off until the owner turns it on,
 * with a preview of exactly what it adds.
 *
 *   robots_policy     groups in the generated robots.txt for the AI
 *                     crawlers the owner chose to keep out: none by
 *                     default (training crawlers, Google-Extended, AI
 *                     search and answer fetchers are each ticked by hand);
 *                     an agent another plugin already names in robots.txt
 *                     is left to it;
 *   meta_description  a meta description on the front page from the
 *                     business's confirmed one-line description, only while
 *                     no SEO plugin is active (they write their own);
 *   sitemap_line      the `Sitemap:` line in the generated robots.txt, when
 *                     a sitemap exists and robots.txt does not name it.
 *
 * A physical robots.txt replaces the generated one, so the screen shows the
 * lines to paste instead.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fixes.
 */
final class Inite_Agents_Fixes {

	/**
	 * AI crawlers by what they do with a page, as their operators document
	 * them. Training: the page goes into a model. Google-Extended: Google's
	 * token for Gemini, apart because blocking it takes the site out of the
	 * Gemini app (AI Overviews do not read it). Search: an AI search index
	 * that cites and links. Answer: fetched at once for a person's question.
	 */
	const BOTS = array(
		'training'        => array( 'GPTBot', 'ClaudeBot', 'anthropic-ai', 'Applebot-Extended', 'CCBot', 'Meta-ExternalAgent', 'Bytespider', 'cohere-ai' ),
		'google_extended' => array( 'Google-Extended' ),
		'search'          => array( 'OAI-SearchBot', 'Claude-SearchBot', 'PerplexityBot', 'DuckAssistBot', 'YouBot' ),
		'answer'          => array( 'ChatGPT-User', 'Claude-User', 'Perplexity-User', 'MistralAI-User', 'Meta-ExternalFetcher' ),
	);

	/** The longest meta description written, in characters. */
	const META_MAX = 160;

	/**
	 * Set while the screen reads robots.txt as it would be without these fixes.
	 *
	 * @var bool
	 */
	private static $measuring = false;

	/** Hook what is switched on. */
	public static function register() {
		if ( Inite_Agents_Settings::fix( 'robots_policy' ) || Inite_Agents_Settings::fix( 'sitemap_line' ) ) {
			add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 30, 2 );
		}
		if ( Inite_Agents_Settings::fix( 'meta_description' ) ) {
			add_action( 'wp_head', array( __CLASS__, 'print_meta' ), 1 );
		}
	}

	/**
	 * The groups the policy adds to this robots.txt, given what it already says.
	 *
	 * @param string $robots The robots.txt so far.
	 * @param array  $block  Which groups to block (the setting, or a preview's).
	 * @return string The lines to add, or ''.
	 */
	public static function policy_lines( $robots, array $block ) {
		$named = array();
		if ( preg_match_all( '/^\s*User-agent:\s*(\S+)/mi', (string) $robots, $m ) ) {
			$named = array_map( 'strtolower', $m[1] );
		}
		$out = array();
		foreach ( self::BOTS as $group => $bots ) {
			if ( empty( $block[ $group ] ) ) {
				continue;
			}
			$agents = array();
			foreach ( $bots as $bot ) {
				if ( ! in_array( strtolower( $bot ), $named, true ) ) {
					$agents[] = 'User-agent: ' . $bot;
				}
			}
			if ( $agents ) {
				$out[] = implode( "\n", $agents ) . "\nDisallow: /";
			}
		}
		return $out ? implode( "\n\n", $out ) : '';
	}

	/**
	 * The sitemap's address, or '' when the site has none: an SEO plugin's, else WordPress's own.
	 *
	 * @return string
	 */
	public static function sitemap_url() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			$wpseo = get_option( 'wpseo', array() );
			if ( ! is_array( $wpseo ) || ! isset( $wpseo['enable_xml_sitemap'] ) || $wpseo['enable_xml_sitemap'] ) {
				return home_url( '/sitemap_index.xml' );
			}
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = get_option( 'rank_math_modules', array() );
			if ( is_array( $modules ) && in_array( 'sitemap', $modules, true ) ) {
				return home_url( '/sitemap_index.xml' );
			}
		}
		if ( function_exists( 'wp_sitemaps_get_server' ) && function_exists( 'get_sitemap_url' ) ) {
			$server = wp_sitemaps_get_server();
			if ( $server && $server->sitemaps_enabled() ) {
				return (string) get_sitemap_url( 'index' );
			}
		}
		return '';
	}

	/**
	 * robots.txt as WordPress would serve it without these fixes, or the
	 * physical file's text. Read on the settings screen only.
	 *
	 * @return array{physical:bool,text:string}
	 */
	public static function robots_now() {
		if ( Inite_Agents_Robots::physical() ) {
			$text = (string) file_get_contents( ABSPATH . 'robots.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file in the WordPress root.
			return array(
				'physical' => true,
				'text'     => $text,
			);
		}
		$public = (string) get_option( 'blog_public' );
		$base   = "User-agent: *\n";
		if ( '0' !== $public ) {
			$site_url = wp_parse_url( site_url() );
			$path     = ! empty( $site_url['path'] ) ? $site_url['path'] : '';
			$base    .= "Disallow: $path/wp-admin/\nAllow: $path/wp-admin/admin-ajax.php\n";
		}
		self::$measuring = true;
		$text            = (string) apply_filters( 'robots_txt', $base, $public ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core's own filter, run as do_robots() runs it, to read robots.txt as it is served.
		self::$measuring = false;
		return array(
			'physical' => false,
			'text'     => $text,
		);
	}

	/**
	 * The `Sitemap:` line to add, or '' when robots.txt names one or there is none.
	 *
	 * @param string $robots The robots.txt so far.
	 * @return string
	 */
	public static function sitemap_line( $robots ) {
		$url = self::sitemap_url();
		if ( '' === $url || preg_match( '/^\s*Sitemap:/mi', (string) $robots ) ) {
			return '';
		}
		return 'Sitemap: ' . esc_url_raw( $url );
	}

	/**
	 * The robots_txt filter: the policy's groups and the sitemap line, as switched on.
	 *
	 * @param string $output    The robots.txt so far.
	 * @param bool   $is_public Whether the site is public (unused: a crawler told to stay out of a private site loses nothing).
	 * @return string
	 */
	public static function robots( $output, $is_public = true ) {
		unset( $is_public );
		if ( self::$measuring ) {
			return $output;
		}
		$output = rtrim( (string) $output );
		if ( Inite_Agents_Settings::fix( 'robots_policy' ) ) {
			$lines = self::policy_lines( $output, Inite_Agents_Settings::get()['robots_block'] );
			if ( '' !== $lines ) {
				$output .= "\n\n" . $lines;
			}
		}
		if ( Inite_Agents_Settings::fix( 'sitemap_line' ) ) {
			$line = self::sitemap_line( $output );
			if ( '' !== $line ) {
				$output .= "\n\n" . $line;
			}
		}
		return $output . "\n";
	}

	/**
	 * The SEO plugin that writes meta descriptions, or ''.
	 *
	 * @return string
	 */
	public static function seo_plugin() {
		$known = array(
			'WPSEO_VERSION'              => 'Yoast SEO',
			'RANK_MATH_VERSION'          => 'Rank Math',
			'AIOSEO_VERSION'             => 'All in One SEO',
			'SEOPRESS_VERSION'           => 'SEOPress',
			'THE_SEO_FRAMEWORK_VERSION'  => 'The SEO Framework',
			'SLIM_SEO_VER'               => 'Slim SEO',
			'SQ_VERSION'                 => 'Squirrly SEO',
		);
		foreach ( $known as $constant => $name ) {
			if ( defined( $constant ) ) {
				return $name;
			}
		}
		return (string) apply_filters( 'inite_agents_meta_owner', '' );
	}

	/**
	 * The front page's description: the business's confirmed one line, else the tagline, cut to META_MAX.
	 *
	 * @return string
	 */
	public static function meta_text() {
		$copy = Inite_Agents_Store::get();
		$line = isset( $copy['facts']['business']['line'] ) ? (string) $copy['facts']['business']['line'] : '';
		if ( '' === trim( $line ) ) {
			$line = (string) get_bloginfo( 'description' );
		}
		$line = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $line ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $line ) > self::META_MAX ) {
			$line = rtrim( mb_substr( $line, 0, self::META_MAX - 1 ) ) . '…';
		}
		return $line;
	}

	/** The meta description on the front page, while no SEO plugin writes one. */
	public static function print_meta() {
		if ( ! is_front_page() || '' !== self::seo_plugin() ) {
			return;
		}
		$text = self::meta_text();
		if ( '' !== $text ) {
			printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $text ) );
		}
	}
}
