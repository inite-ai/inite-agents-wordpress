<?php
/**
 * Sync: the manifest with the ETag held, then any file whose ETag moved,
 * then the public facts, and only when all of them arrived, the new copy.
 *
 * What the service answers decides what happens to the copy:
 *   200       a new copy, written whole;
 *   304       nothing changed;
 *   401, 403, 409  the key is unknown or revoked, the connector is off, or
 *             nothing is confirmed to publish: the copy is dropped and the
 *             site stops serving, as the owner asked in the cabinet;
 *   anything else (a timeout, a 5xx, a body that does not read): the last
 *             good copy stays and keeps being served.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync with the service.
 */
final class Inite_Agents_Sync {

	const CRON = 'inite_agents_sync';

	/** The files the service names that this plugin knows how to place. */
	const KNOWN_FILES = array( 'llms.txt', 'agent-card.json', 'mcp.json', 'api-catalog', 'robots-signal', 'jsonld' );

	/** The root paths a file may be served at. Nothing else is ever claimed. */
	const ROOT_PATHS = array( '/llms.txt', '/.well-known/agent-card.json', '/.well-known/mcp.json', '/.well-known/api-catalog' );

	/**
	 * One sync.
	 *
	 * @param bool $force Ask for everything again, ignoring the ETags held.
	 * @return array{ok:bool,changed?:bool,code?:string,error?:string}
	 */
	public static function run( $force = false ) {
		$key = Inite_Agents_Settings::get()['key'];
		if ( '' === $key ) {
			return self::done( false, 'no_key', __( 'No site key is saved yet.', 'inite-agents' ) );
		}
		$copy = Inite_Agents_Store::get();
		$etag = ( ! $force && ! empty( $copy['etag'] ) ) ? $copy['etag'] : null;
		$res  = Inite_Agents_Client::get( '/api/site/v1/manifest', $key, $etag );

		if ( 304 === $res['status'] && ! empty( $copy['version'] ) ) {
			return self::done( true, 'not_modified', '', false );
		}
		if ( in_array( $res['status'], array( 401, 403, 409 ), true ) ) {
			$said = json_decode( $res['body'], true );
			$code = is_array( $said ) && isset( $said['error'] ) ? sanitize_key( $said['error'] ) : 'refused';
			$msg  = is_array( $said ) && isset( $said['message'] ) ? sanitize_text_field( $said['message'] ) : __( 'The service refused this site key.', 'inite-agents' );
			Inite_Agents_Store::clear();
			return self::done( false, $code, $msg );
		}
		if ( 200 !== $res['status'] ) {
			/* translators: %s: HTTP status code or network error. */
			return self::done( false, 'unreachable', sprintf( __( 'The INITE service did not answer (%s). The last good copy is still served.', 'inite-agents' ), $res['status'] ? (string) $res['status'] : $res['error'] ) );
		}
		$manifest = self::manifest( $res['body'] );
		if ( ! $manifest ) {
			return self::done( false, 'bad_manifest', __( 'The manifest did not read. The last good copy is still served.', 'inite-agents' ) );
		}

		$files = array();
		foreach ( $manifest['files'] as $name => $meta ) {
			// The body is reused when the service says it did not change; where and how it is served is always the new manifest's.
			$held = ( ! $force && isset( $copy['files'][ $name ]['body'] ) ) ? $copy['files'][ $name ] : null;
			if ( $held && $held['etag'] === $meta['etag'] ) {
				$files[ $name ] = array( 'body' => $held['body'] ) + $meta;
				continue;
			}
			$got = Inite_Agents_Client::get( '/api/site/v1/files/' . rawurlencode( $name ), $key, $held ? $held['etag'] : null );
			if ( 304 === $got['status'] && $held ) {
				$files[ $name ] = array( 'body' => $held['body'] ) + $meta;
				continue;
			}
			if ( 200 !== $got['status'] ) {
				/* translators: %s: file name. */
				return self::done( false, 'file_failed', sprintf( __( 'The file %s did not arrive. The last good copy is still served.', 'inite-agents' ), $name ) );
			}
			$files[ $name ] = array(
				'body' => $got['body'],
				'type' => $meta['type'],
				'path' => $meta['path'],
				'etag' => $got['etag'] ? $got['etag'] : $meta['etag'],
			);
		}

		$facts = self::facts( $manifest['site'] );
		if ( null === $facts ) {
			return self::done( false, 'facts_failed', __( 'The public facts did not arrive. The last good copy is still served.', 'inite-agents' ) );
		}

		Inite_Agents_Store::put(
			array(
				'version'   => $manifest['site']['version'],
				'etag'      => $res['etag'],
				'manifest'  => $manifest,
				'files'     => $files,
				'facts'     => $facts,
				'synced_at' => time(),
			)
		);
		return self::done( true, 'synced', '', true );
	}

	/**
	 * The manifest, reduced to what the plugin uses, or null when it does not read.
	 *
	 * @param string $body Response body.
	 * @return array|null
	 */
	public static function manifest( $body ) {
		$m = json_decode( $body, true );
		if ( ! is_array( $m ) || empty( $m['ok'] ) || ! isset( $m['site']['slug'], $m['site']['version'], $m['files'] ) || ! is_array( $m['files'] ) ) {
			return null;
		}
		$slug = (string) $m['site']['slug'];
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,62}$/', $slug ) ) {
			return null;
		}
		$files = array();
		foreach ( $m['files'] as $f ) {
			if ( ! is_array( $f ) || ! isset( $f['name'] ) || ! in_array( $f['name'], self::KNOWN_FILES, true ) ) {
				continue;
			}
			$path = isset( $f['path'] ) && in_array( $f['path'], self::ROOT_PATHS, true ) ? $f['path'] : null;
			$type = isset( $f['contentType'] ) ? (string) $f['contentType'] : '';
			// A media type with parameters (a profile, a charset), and nothing that could end the header line.
			if ( ! preg_match( '#^[a-z]+/[a-z0-9.+-]+(\s*;\s*[a-z0-9-]+=("[^"\x00-\x1f]*"|[a-z0-9.+-]+))*$#i', $type ) ) {
				$type = 'text/plain; charset=utf-8';
			}
			$files[ $f['name'] ] = array(
				'path' => $path,
				'type' => $type,
				'etag' => isset( $f['etag'] ) ? (string) $f['etag'] : '',
			);
		}
		$head  = isset( $m['head'] ) && is_array( $m['head'] ) ? $m['head'] : array();
		$links = array();
		foreach ( isset( $head['links'] ) && is_array( $head['links'] ) ? $head['links'] : array() as $l ) {
			if ( is_array( $l ) && isset( $l['rel'], $l['href'] ) && preg_match( '/^[a-z-]+$/', (string) $l['rel'] ) ) {
				$links[] = array(
					'rel'  => (string) $l['rel'],
					'href' => esc_url_raw( (string) $l['href'], array( 'https', 'http' ) ),
					'type' => isset( $l['type'] ) ? sanitize_text_field( (string) $l['type'] ) : '',
				);
			}
		}
		return array(
			'site'  => array(
				'slug'        => $slug,
				'domain'      => sanitize_text_field( (string) ( isset( $m['site']['domain'] ) ? $m['site']['domain'] : '' ) ),
				'version'     => (int) $m['site']['version'],
				'confirmedAt' => sanitize_text_field( (string) ( isset( $m['site']['confirmedAt'] ) ? $m['site']['confirmedAt'] : '' ) ),
			),
			'mcp'   => isset( $m['mcp']['url'] ) ? esc_url_raw( (string) $m['mcp']['url'], array( 'https', 'http' ) ) : '',
			'files' => $files,
			'head'  => array(
				'links'  => $links,
				'jsonLd' => isset( $head['jsonLd'] ) && is_array( $head['jsonLd'] ) ? $head['jsonLd'] : array(),
			),
		);
	}

	/**
	 * The path of the site's public facts: what the sync reads, and what the
	 * WebMCP script in the head reads when an agent calls a tool.
	 *
	 * @param array $site The manifest's site block.
	 * @return string
	 */
	public static function facts_path( array $site ) {
		$slug   = isset( $site['slug'] ) ? (string) $site['slug'] : '';
		$domain = isset( $site['domain'] ) ? (string) $site['domain'] : '';
		return '/api/site/v1/public/' . rawurlencode( $slug ) . ( '' !== $domain ? '?d=' . rawurlencode( $domain ) : '' );
	}

	/**
	 * The full address of the site's public facts, or '' without a slug.
	 *
	 * @param array $site The manifest's site block.
	 * @return string
	 */
	public static function facts_url( array $site ) {
		if ( empty( $site['slug'] ) || ! preg_match( '/^[a-z0-9][a-z0-9-]{0,62}$/', (string) $site['slug'] ) ) {
			return '';
		}
		return Inite_Agents_Settings::api_base() . self::facts_path( $site );
	}

	/**
	 * The public facts of the site's confirmed version, or null.
	 *
	 * @param array $site The manifest's site block.
	 * @return array|null
	 */
	private static function facts( array $site ) {
		$got = Inite_Agents_Client::get( self::facts_path( $site ), '', null, false );
		if ( 200 !== $got['status'] ) {
			return null;
		}
		$body = json_decode( $got['body'], true );
		return is_array( $body ) && ! empty( $body['ok'] ) && isset( $body['facts'] ) && is_array( $body['facts'] ) ? $body['facts'] : null;
	}

	/**
	 * Record and return the outcome.
	 *
	 * @param bool   $ok      Whether the copy is good.
	 * @param string $code    Machine code.
	 * @param string $error   Sentence for the settings screen.
	 * @param bool   $changed Whether a new copy was written.
	 * @return array
	 */
	private static function done( $ok, $code, $error, $changed = false ) {
		Inite_Agents_Store::set_status(
			array(
				'checked_at' => time(),
				'ok'         => $ok,
				'code'       => $code,
				'error'      => $error,
			)
		);
		return array(
			'ok'      => $ok,
			'changed' => $changed,
			'code'    => $code,
			'error'   => $error,
		);
	}
}
