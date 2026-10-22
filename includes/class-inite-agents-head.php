<?php
/**
 * The head: the WebMCP script, the link tags that tell an agent where the
 * facts live, and the JSON-LD of the confirmed facts.
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
			add_filter( 'script_loader_tag', array( __CLASS__, 'crossorigin' ), 10, 2 );
			add_action( 'wp_head', array( __CLASS__, 'print_head' ), 5 );
		}
	}

	/**
	 * The stored head parts.
	 *
	 * @return array{script:string,links:array,jsonLd:array}
	 */
	private static function parts() {
		$copy = Inite_Agents_Store::get();
		$head = isset( $copy['manifest']['head'] ) && is_array( $copy['manifest']['head'] ) ? $copy['manifest']['head'] : array();
		return array(
			'script' => isset( $head['script'] ) ? (string) $head['script'] : '',
			'links'  => isset( $head['links'] ) && is_array( $head['links'] ) ? $head['links'] : array(),
			'jsonLd' => isset( $head['jsonLd'] ) && is_array( $head['jsonLd'] ) ? $head['jsonLd'] : array(),
		);
	}

	/** Enqueue the WebMCP script, async. Its version is in its address. */
	public static function enqueue() {
		$src = self::parts()['script'];
		if ( '' === $src ) {
			return;
		}
		wp_enqueue_script(
			self::HANDLE,
			$src,
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the service puts the facts version in the address itself.
			array(
				'strategy'  => 'async',
				'in_footer' => false,
			)
		);
	}

	/**
	 * The script is fetched cross-origin without credentials.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The handle.
	 * @return string
	 */
	public static function crossorigin( $tag, $handle ) {
		if ( self::HANDLE !== $handle || false !== strpos( $tag, 'crossorigin' ) ) {
			return $tag;
		}
		$tag = str_replace( ' src=', ' crossorigin="anonymous" src=', $tag );
		// WordPress before 6.3 knows no loading strategy: the tag says async itself.
		if ( false === strpos( $tag, ' async' ) ) {
			$tag = str_replace( ' src=', ' async src=', $tag );
		}
		return $tag;
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
