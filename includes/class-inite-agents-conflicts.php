<?php
/**
 * Who else already answers a root path, so this plugin does not take it.
 *
 * A physical file at the path wins: the web server serves it before
 * WordPress runs. A plugin that serves llms.txt itself (Yoast SEO, Rank
 * Math, All in One SEO, SEOPress Pro, Website LLMs.txt) keeps it while its
 * own llms.txt is switched on. Any other plugin can claim a path through
 * the `inite_agents_path_owner` filter. The settings screen shows each
 * owner, and the path is left alone.
 *
 * Every check reads options already loaded or one file's existence, so it
 * is cheap enough to run on the request that asks for the path.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Path ownership checks.
 */
final class Inite_Agents_Conflicts {

	/**
	 * Who owns a root path besides this plugin, or '' when nobody does.
	 *
	 * @param string $path Root path, e.g. /llms.txt.
	 * @return string A short name of the owner.
	 */
	public static function owner( $path ) {
		// The plugin is named before its file: Yoast SEO, for one, writes llms.txt to disk.
		// A plugin that makes llms.txt may make llms-full.txt too (Website LLMs.txt does): both stay its own.
		$owner = in_array( $path, array( '/llms.txt', '/llms-full.txt' ), true ) ? self::llms_plugin() : '';
		if ( '' === $owner && self::physical( $path ) ) {
			$owner = __( 'a file on the server', 'inite-agents' );
		}
		/**
		 * Filters who already answers a root path. Return a non-empty name to
		 * keep INITE Agents away from it.
		 *
		 * @param string $owner The owner found so far, or ''.
		 * @param string $path  The root path.
		 */
		return (string) apply_filters( 'inite_agents_path_owner', $owner, $path );
	}

	/**
	 * Whether a physical file exists at the path in the WordPress root.
	 *
	 * @param string $path Root path.
	 * @return bool
	 */
	public static function physical( $path ) {
		return file_exists( ABSPATH . ltrim( $path, '/' ) );
	}

	/**
	 * The plugin that serves llms.txt itself, or ''.
	 *
	 * @return string
	 */
	private static function llms_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			$wpseo = get_option( 'wpseo', array() );
			if ( is_array( $wpseo ) && ! empty( $wpseo['enable_llms_txt'] ) ) {
				return 'Yoast SEO';
			}
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = get_option( 'rank_math_modules', array() );
			if ( is_array( $modules ) && in_array( 'llms-txt', $modules, true ) ) {
				return 'Rank Math';
			}
		}
		if ( function_exists( 'aioseo' ) && defined( 'AIOSEO_VERSION' ) ) {
			$aioseo = get_option( 'aioseo_options', '' );
			$aioseo = is_array( $aioseo ) ? $aioseo : json_decode( (string) $aioseo, true );
			if ( is_array( $aioseo ) && ! empty( $aioseo['sitemap']['llms']['enable'] ) ) {
				return 'All in One SEO';
			}
		}
		if ( defined( 'SEOPRESS_PRO_VERSION' ) ) {
			$toggle = get_option( 'seopress_toggle', array() );
			if ( is_array( $toggle ) && ! empty( $toggle['toggle-llms'] ) && '1' === (string) $toggle['toggle-llms'] ) {
				return 'SEOPress PRO';
			}
		}
		if ( defined( 'WEBSITE_LLMS_TXT_VERSION' ) ) {
			return 'Website LLMs.txt';
		}
		return '';
	}

	/**
	 * Whether WordPress answers the domain root. Installed in a subdirectory,
	 * it never sees /llms.txt at all.
	 *
	 * @return bool
	 */
	public static function at_root() {
		$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return '' === trim( $path, '/' );
	}

	/**
	 * The SEO plugin that already prints an Organization node in its schema, or ''.
	 *
	 * @return string
	 */
	public static function organization_schema() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'All in One SEO';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$social = get_option( 'seopress_social_option_name', array() );
			$type   = is_array( $social ) && isset( $social['seopress_social_knowledge_type'] ) ? (string) $social['seopress_social_knowledge_type'] : 'none';
			if ( 'none' !== $type && '' !== $type ) {
				return 'SEOPress';
			}
		}
		return (string) apply_filters( 'inite_agents_organization_owner', '' );
	}
}
