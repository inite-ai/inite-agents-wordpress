<?php
/**
 * The calls to the INITE connector API (/api/site/v1/*).
 *
 * Every address is built from the configured origin, never taken from a
 * response, so the site key is only ever sent to that origin. Each call
 * waits five seconds at most and runs only from cron, activation or the
 * settings screen, never while a visitor's page renders.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client: one HTTP call, its answer reduced to what the sync needs.
 */
final class Inite_Agents_Client {

	const TIMEOUT = 5;

	/** Largest body taken from the service, in bytes. */
	const MAX_BODY = 1048576;

	/**
	 * GET a connector path with the site key.
	 *
	 * @param string      $path  Path under the origin, starting with /api/site/v1/.
	 * @param string      $key   Site key.
	 * @param string|null $etag  ETag held, sent as If-None-Match.
	 * @param bool        $auth  Whether to send the key (the public facts need none).
	 * @return array{status:int,body:string,etag:string,type:string,error:string}
	 */
	public static function get( $path, $key, $etag = null, $auth = true ) {
		$headers = array(
			'Accept'     => 'application/json, text/plain;q=0.9, */*;q=0.5',
			'User-Agent' => self::user_agent(),
		);
		if ( $auth ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		if ( $etag ) {
			$headers['If-None-Match'] = $etag;
		}
		return self::answer(
			wp_remote_get(
				Inite_Agents_Settings::api_base() . $path,
				array(
					'timeout'             => self::TIMEOUT,
					'redirection'         => 0,
					'headers'             => $headers,
					'limit_response_size' => self::MAX_BODY,
				)
			)
		);
	}

	/**
	 * POST JSON to a connector path with the site key.
	 *
	 * @param string $path Path under the origin.
	 * @param string $key  Site key.
	 * @param array  $body Body, sent as JSON.
	 * @return array{status:int,body:string,etag:string,type:string,error:string}
	 */
	public static function post( $path, $key, array $body ) {
		return self::answer(
			wp_remote_post(
				Inite_Agents_Settings::api_base() . $path,
				array(
					'timeout'     => self::TIMEOUT,
					'redirection' => 0,
					'headers'     => array(
						'Authorization' => 'Bearer ' . $key,
						'Content-Type'  => 'application/json',
						'User-Agent'    => self::user_agent(),
					),
					'body'        => wp_json_encode( $body ),
				)
			)
		);
	}

	/**
	 * A response reduced to status, body and the two headers the sync reads.
	 *
	 * @param array|WP_Error $res The wp_remote_* result.
	 * @return array{status:int,body:string,etag:string,type:string,error:string}
	 */
	private static function answer( $res ) {
		if ( is_wp_error( $res ) ) {
			return array(
				'status' => 0,
				'body'   => '',
				'etag'   => '',
				'type'   => '',
				'error'  => $res->get_error_message(),
			);
		}
		return array(
			'status' => (int) wp_remote_retrieve_response_code( $res ),
			'body'   => (string) wp_remote_retrieve_body( $res ),
			'etag'   => (string) wp_remote_retrieve_header( $res, 'etag' ),
			'type'   => (string) wp_remote_retrieve_header( $res, 'content-type' ),
			'error'  => '',
		);
	}

	/**
	 * The plugin's user agent: its name and version, nothing about the site's visitors.
	 *
	 * @return string
	 */
	private static function user_agent() {
		return 'INITE-Agents-WordPress/' . INITE_AGENTS_VERSION . '; WordPress/' . get_bloginfo( 'version' );
	}
}
