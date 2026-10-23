<?php
/**
 * What INITE knows about this site, for the dashboard widget and the
 * Overview tab: GET /api/site/v1/summary with the site key, read only.
 *
 * Held in a transient for FRESH seconds. The hourly sync fetches it when the
 * transient has run out; the Overview fetches it once if nothing is held,
 * and "Refresh" fetches it at once. Never while a visitor's page renders. A
 * failed fetch keeps the last summary and its time, with the error, for
 * RETRY seconds, so a service that is down is not asked every page load.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Summary client.
 */
final class Inite_Agents_Summary {

	const TRANSIENT = 'inite_agents_summary';
	const FRESH     = 4 * HOUR_IN_SECONDS;
	const RETRY     = 30 * MINUTE_IN_SECONDS;

	/**
	 * The held summary, and whether it is fresh.
	 *
	 * @return array{summary:?array,fetched_at:int,error:string}
	 */
	public static function held() {
		$v = get_transient( self::TRANSIENT );
		return is_array( $v ) ? $v + array(
			'summary'    => null,
			'fetched_at' => 0,
			'error'      => '',
		) : array(
			'summary'    => null,
			'fetched_at' => 0,
			'error'      => '',
		);
	}

	/**
	 * The summary: the held one, or a fetch when nothing is held (or $force).
	 *
	 * @param bool $force Fetch now, whatever is held.
	 * @return array{summary:?array,fetched_at:int,error:string}
	 */
	public static function get( $force = false ) {
		$held = self::held();
		if ( ! $force && ( $held['summary'] || '' !== $held['error'] ) ) {
			return $held;
		}
		if ( '' === Inite_Agents_Settings::get()['key'] ) {
			return $held;
		}
		return self::fetch( $held );
	}

	/** The hourly sync's turn: fetch when nothing is held. */
	public static function cron() {
		if ( false === get_transient( self::TRANSIENT ) && '' !== Inite_Agents_Settings::get()['key'] ) {
			self::fetch( self::held() );
		}
	}

	/**
	 * One fetch, held.
	 *
	 * @param array $held What is held now, kept on failure.
	 * @return array
	 */
	private static function fetch( array $held ) {
		$res = Inite_Agents_Client::get( '/api/site/v1/summary', Inite_Agents_Settings::get()['key'] );
		$got = 200 === $res['status'] ? json_decode( $res['body'], true ) : null;
		if ( is_array( $got ) && ! empty( $got['ok'] ) && isset( $got['summary'] ) && is_array( $got['summary'] ) ) {
			$out = array(
				'summary'    => $got['summary'],
				'fetched_at' => time(),
				'error'      => '',
			);
			set_transient( self::TRANSIENT, $out, self::FRESH );
			return $out;
		}
		$said = is_array( json_decode( $res['body'], true ) ) ? json_decode( $res['body'], true ) : array();
		/* translators: %s: HTTP status code or network error. */
		$error = isset( $said['message'] ) ? sanitize_text_field( (string) $said['message'] ) : sprintf( __( 'INITE did not answer (%s).', 'inite-agents' ), $res['status'] ? (string) $res['status'] : $res['error'] );
		$out   = array(
			'summary'    => $held['summary'],
			'fetched_at' => (int) $held['fetched_at'],
			'error'      => $error,
		);
		set_transient( self::TRANSIENT, $out, self::RETRY );
		return $out;
	}

	/**
	 * A cabinet address from a summary link: `{lang}` becomes the admin's language when INITE speaks it.
	 *
	 * @param string $path The path with {lang}.
	 * @return string
	 */
	public static function link( $path ) {
		$lang = substr( get_user_locale(), 0, 2 );
		$lang = in_array( $lang, array( 'en', 'ru', 'es', 'pt' ), true ) ? $lang : 'en';
		$path = str_replace( '{lang}', $lang, (string) $path );
		return 0 === strpos( $path, '/' ) ? Inite_Agents_Settings::api_base() . $path : '';
	}
}
