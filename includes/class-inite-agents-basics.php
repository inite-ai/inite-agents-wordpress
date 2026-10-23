<?php
/**
 * The site's basics, as WordPress itself knows them: the title and tagline
 * from Settings → General, the site language and address, and with
 * WooCommerce active the number of published products. Sent to INITE on the
 * hourly sync as the `site` field of a ping, only when they changed since the
 * last send or a day has passed, so the INITE cabinet can suggest them to the
 * owner. Nothing about a person: no user, no email address, no visitor data
 * (readme.txt, "External services").
 *
 * The products themselves are not sent: `woocommerce` carries the count only,
 * and is where a later version adds more.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basics.
 */
final class Inite_Agents_Basics {

	/** The hash of the last basics sent and when, so an unchanged site is not sent again within a day. */
	const OPTION = 'inite_agents_basics_sent';

	/**
	 * What the site says about itself.
	 *
	 * @return array
	 */
	public static function read() {
		$site = array(
			'title'    => wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
			'tagline'  => wp_strip_all_tags( (string) get_bloginfo( 'description' ) ),
			'language' => (string) get_bloginfo( 'language' ),
			'url'      => esc_url_raw( home_url( '/' ) ),
		);
		if ( class_exists( 'WooCommerce' ) ) {
			$counts = wp_count_posts( 'product' );
			$site['woocommerce'] = array(
				'products' => isset( $counts->publish ) ? (int) $counts->publish : 0,
			);
		}
		return $site;
	}

	/**
	 * Send them when they changed, or once a day.
	 *
	 * @return array|null The client's answer, or null when nothing was sent.
	 */
	public static function run() {
		$key = Inite_Agents_Settings::get()['key'];
		if ( '' === $key ) {
			return null;
		}
		$site = self::read();
		$hash = md5( wp_json_encode( $site ) );
		$sent = get_option( self::OPTION, array() );
		if ( is_array( $sent ) && isset( $sent['hash'], $sent['at'] ) && $sent['hash'] === $hash && time() - (int) $sent['at'] < DAY_IN_SECONDS ) {
			return null;
		}
		$res = Inite_Agents_Client::post(
			'/api/site/v1/ping',
			$key,
			array(
				'platform'      => 'wordpress',
				'pluginVersion' => INITE_AGENTS_VERSION,
				'site'          => $site,
			)
		);
		if ( isset( $res['status'] ) && 200 === $res['status'] ) {
			update_option(
				self::OPTION,
				array(
					'hash' => $hash,
					'at'   => time(),
				),
				false
			);
		}
		return $res;
	}
}
