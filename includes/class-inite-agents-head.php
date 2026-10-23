<?php
/**
 * The head: the WebMCP script (shipped with the plugin), the link tags that
 * tell an agent where the facts live, and the JSON-LD of the confirmed facts.
 *
 * Built from the manifest's parts, not printed from its HTML, so every
 * attribute is escaped here and a duplicate can be left out:
 *   - Organization: when an SEO plugin (Yoast SEO, Rank Math, All in One SEO,
 *     SEOPress with a knowledge graph) already prints one, ours is dropped;
 *   - FAQPage: dropped on a page that carries an SEO plugin's FAQ block,
 *     which prints its own.
 * The JSON-LD goes on the front page only: it describes the business, and a
 * FAQPage belongs where its answers can be read.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Head output.
 */
final class Inite_Agents_Head {

	const HANDLE = 'inite-agents-webmcp';

	/** The FAQ blocks of SEO plugins that print their own FAQPage. */
	const FAQ_BLOCKS = array( 'yoast/faq-block', 'rank-math/faq-block', 'aioseo/faq' );

	/** Hook in, only while the feature is on. */
	public static function register() {
		if ( Inite_Agents_Settings::on( 'head' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'wp_head', array( __CLASS__, 'print_head' ), 5 );
		}
	}

	/**
	 * The stored head parts.
	 *
	 * @return array{links:array,jsonLd:array}
	 */
	private static function parts() {
		$copy = Inite_Agents_Store::get();
		$head = isset( $copy['manifest']['head'] ) && is_array( $copy['manifest']['head'] ) ? $copy['manifest']['head'] : array();
		return array(
			'links'  => isset( $head['links'] ) && is_array( $head['links'] ) ? $head['links'] : array(),
			'jsonLd' => isset( $head['jsonLd'] ) && is_array( $head['jsonLd'] ) ? $head['jsonLd'] : array(),
		);
	}

	/**
	 * The script's one setting: the facts address and, while the owner takes
	 * requests from agents, where request_contact (and request_booking) post.
	 * Both addresses are on inite.ai, as the manifest names them.
	 *
	 * @param array  $copy  The stored copy.
	 * @param string $facts The facts address.
	 * @return array
	 */
	private static function settings( $copy, $facts ) {
		$out = array( 'facts' => $facts );
		$req = isset( $copy['manifest']['features']['requests'] ) && is_array( $copy['manifest']['features']['requests'] ) ? $copy['manifest']['features']['requests'] : array();
		$url = isset( $req['url'] ) && is_string( $req['url'] ) ? $req['url'] : '';
		if ( ! empty( $req['enabled'] ) && 0 === strpos( $url, Inite_Agents_Settings::api_base() . '/api/site/v1/public/' ) ) {
			$out['requests'] = $url;
			$out['booking']  = ! empty( $req['booking'] );
		}
		return $out;
	}

	/**
	 * Enqueue the WebMCP script. It ships inside the plugin (js/webmcp.js,
	 * built from the same source as the script inite.ai serves to other
	 * sites), so no code is loaded from another host; the one setting printed
	 * before it is the address of the business's public facts, the only thing
	 * it reads from INITE, and only when an agent calls one of its tools.
	 */
	public static function enqueue() {
		$copy  = Inite_Agents_Store::get();
		$facts = isset( $copy['manifest']['site'] ) && is_array( $copy['manifest']['site'] ) ? Inite_Agents_Sync::facts_url( $copy['manifest']['site'] ) : '';
		if ( '' === $facts ) {
			return;
		}
		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'js/webmcp.js', INITE_AGENTS_FILE ),
			array(),
			INITE_AGENTS_VERSION,
			array(
				'strategy'  => 'async',
				'in_footer' => false,
			)
		);
		wp_add_inline_script(
			self::HANDLE,
			'var initeAgentsWebmcp = ' . wp_json_encode( self::settings( $copy, $facts ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);
	}

	/** Print the link tags and, on the front page, the JSON-LD. */
	public static function print_head() {
		$parts = self::parts();
		foreach ( $parts['links'] as $link ) {
			if ( empty( $link['rel'] ) || empty( $link['href'] ) ) {
				continue;
			}
			printf(
				"<link rel=\"%s\" href=\"%s\"%s>\n",
				esc_attr( $link['rel'] ),
				esc_url( $link['href'] ),
				! empty( $link['type'] ) ? ' type="' . esc_attr( $link['type'] ) . '"' : ''
			);
		}
		if ( ! is_front_page() && ! apply_filters( 'inite_agents_jsonld_everywhere', false ) ) {
			return;
		}
		$json = self::json_ld( $parts['jsonLd'] );
		if ( null === $json ) {
			return;
		}
		$encoded = wp_json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $encoded, array( 'type' => 'application/ld+json' ) );
		} else {
			// JSON_HEX_TAG keeps "<" and ">" out of the body, so it cannot close the element.
			echo '<script type="application/ld+json">' . $encoded . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * The JSON-LD with what another plugin already prints left out, or null
	 * when nothing is left.
	 *
	 * @param array $json_ld The manifest's object.
	 * @return array|null
	 */
	public static function json_ld( array $json_ld ) {
		if ( empty( $json_ld['@graph'] ) || ! is_array( $json_ld['@graph'] ) ) {
			return null;
		}
		$skip = self::skipped();
		$keep = array();
		foreach ( $json_ld['@graph'] as $node ) {
			$type = is_array( $node ) && isset( $node['@type'] ) ? (string) $node['@type'] : '';
			if ( ( 'Organization' === $type || 'LocalBusiness' === $type ) && '' !== $skip['organization'] ) {
				continue;
			}
			if ( 'FAQPage' === $type && '' !== $skip['faq'] ) {
				continue;
			}
			$keep[] = $node;
		}
		if ( ! $keep ) {
			return null;
		}
		$json_ld['@graph'] = $keep;
		return $json_ld;
	}

	/**
	 * What is left out and because of whom: '' when nothing is.
	 *
	 * @return array{organization:string,faq:string}
	 */
	public static function skipped() {
		$faq = '';
		if ( is_singular() ) {
			foreach ( self::FAQ_BLOCKS as $block ) {
				if ( has_block( $block ) ) {
					$faq = $block;
					break;
				}
			}
		}
		return array(
			'organization' => Inite_Agents_Conflicts::organization_schema(),
			'faq'          => $faq,
		);
	}
}
