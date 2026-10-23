<?php
/**
 * The head: the WebMCP script (shipped with the plugin) and the link tags
 * that tell an agent where the facts live. Built from the manifest's parts,
 * not printed from its HTML, so every attribute is escaped here. The JSON-LD
 * of the confirmed facts moved to Inite_Agents_Schema in 0.7.0, where each
 * type is a fix of its own and merges with an SEO plugin's graph.
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

	/** Print the link tags. The JSON-LD is Inite_Agents_Schema's, switched in the Fixes tab. */
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
	}
}
