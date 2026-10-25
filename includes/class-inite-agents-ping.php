<?php
/**
 * The daily ping: which plugin and WordPress versions run, which paths the
 * plugin serves, how many times each known AI agent read them per day, and
 * how many visits came from AI assistants per day and page
 * (Inite_Agents_Referrals). No address, no user agent string, no visitor
 * data (readme.txt,
 * "External services").
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ping.
 */
final class Inite_Agents_Ping {

	const CRON = 'inite_agents_ping';

	/**
	 * The paths this site serves for agents right now.
	 *
	 * @return string[]
	 */
	public static function paths() {
		$paths = Inite_Agents_Files::served();
		if ( Inite_Agents_Settings::on( 'robots' ) && '' !== Inite_Agents_Robots::line() && ! Inite_Agents_Robots::physical() ) {
			$paths[] = '/robots.txt';
		}
		return $paths;
	}

	/**
	 * The body the ping sends.
	 *
	 * @return array
	 */
	public static function body() {
		return array(
			'platform'      => 'wordpress',
			'pluginVersion' => INITE_AGENTS_VERSION,
			'wpVersion'     => get_bloginfo( 'version' ),
			'paths'         => self::paths(),
			'visits'        => Inite_Agents_Visitors::rows(),
			'referrals'     => Inite_Agents_Referrals::rows(),
			'survey'        => Inite_Agents_Settings::on( 'survey' ) ? Inite_Agents_Survey::rows() : array(),
		);
	}

	/**
	 * Send it, when the feature is on.
	 *
	 * @return array|null The client's answer, or null when off.
	 */
	public static function run() {
		if ( ! Inite_Agents_Settings::on( 'ping' ) ) {
			return null;
		}
		return Inite_Agents_Client::post( '/api/site/v1/ping', Inite_Agents_Settings::get()['key'], self::body() );
	}
}
