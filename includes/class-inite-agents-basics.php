<?php
/**
 * The site's basics, as WordPress itself knows them: the title and tagline
 * from Settings → General, the site language and address, and with
 * WooCommerce active the number of published products (switch "Site
 * basics"). Since 0.8.0 also what the site's booking and location plugins
 * publish (switches "Services from booking plugins" and "Locations and
 * hours", Inite_Agents_Plugins). Each part only while its switch is on.
 *
 * Sent to INITE on the hourly sync as the `site` field of a ping, only when
 * it changed since the last send or a day has passed, so the INITE cabinet
 * can suggest it to the owner. Nothing about a person: no user, no email
 * address, no visitor data (readme.txt, "External services").
 *
 * The products themselves go through Inite_Agents_Records, under their own
 * switch; `woocommerce` here carries the count only.
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
		$site = array();
		if ( Inite_Agents_Settings::on( 'send_basics' ) ) {
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
		}
		$from = array();
		if ( Inite_Agents_Settings::on( 'send_services' ) ) {
			$got = Inite_Agents_Plugins::services();
			if ( $got['services'] ) {
				$site['services'] = $got['services'];
				$from             = array_merge( $from, $got['from'] );
			}
		}
		if ( Inite_Agents_Settings::on( 'send_locations' ) ) {
			$got = Inite_Agents_Plugins::locations();
			if ( $got['locations'] ) {
				$site['locations'] = $got['locations'];
			}
			if ( $got['hours'] ) {
				$site['hours'] = $got['hours'];
			}
			if ( $got['locations'] || $got['hours'] ) {
				$from = array_merge( $from, $got['from'] );
			}
		}
		if ( $from ) {
			$site['from'] = array_values( array_unique( $from ) );
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
		// Nothing switched on: nothing leaves the site.
		if ( ! $site ) {
			return null;
		}
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
