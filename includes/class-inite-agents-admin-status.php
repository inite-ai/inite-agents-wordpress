<?php
/**
 * What the settings screen says about each feature and the connection:
 * what is served now, and what stands in the way (a file on the server,
 * another plugin, an older WordPress). Returns escaped HTML.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status lines for the admin screen.
 */
final class Inite_Agents_Admin_Status {

	/**
	 * The status under one feature's checkbox.
	 *
	 * @param string $feature Feature.
	 * @return string HTML.
	 */
	public static function feature( $feature ) {
		$lines = call_user_func( array( __CLASS__, 'lines_' . $feature ) );
		if ( ! $lines ) {
			return '';
		}
		return '<ul class="description" style="margin-top:6px">' . implode( '', array_map( static function ( $l ) {
			return '<li>' . $l . '</li>';
		}, $lines ) ) . '</ul>';
	}

	/**
	 * Root files.
	 *
	 * @return string[]
	 */
	private static function lines_files() {
		if ( ! Inite_Agents_Conflicts::at_root() ) {
			return array( esc_html__( 'WordPress is installed in a subdirectory, so it never sees requests to the domain root. Upload the files to the root instead, or ask INITE for the agents subdomain.', 'inite-agents' ) );
		}
		$stored = Inite_Agents_Files::by_path();
		$lines  = array();
		foreach ( Inite_Agents_Sync::ROOT_PATHS as $path ) {
			$owner = Inite_Agents_Conflicts::owner( $path );
			$code  = '<code>' . esc_html( $path ) . '</code>';
			if ( '' !== $owner ) {
				/* translators: 1: path, 2: who serves it. */
				$lines[] = sprintf( esc_html__( '%1$s is left alone: %2$s already answers it.', 'inite-agents' ), $code, esc_html( $owner ) );
			} elseif ( ! isset( $stored[ $path ] ) ) {
				/* translators: %s: path. */
				$lines[] = sprintf( esc_html__( '%s: nothing stored yet.', 'inite-agents' ), $code );
			} elseif ( Inite_Agents_Settings::on( 'files' ) ) {
				/* translators: %s: path linked to the live file. */
				$lines[] = sprintf( esc_html__( '%s is served.', 'inite-agents' ), '<a href="' . esc_url( home_url( $path ) ) . '" target="_blank" rel="noopener">' . esc_html( $path ) . '</a>' );
			}
		}
		return $lines;
	}

	/**
	 * robots.txt.
	 *
	 * @return string[]
	 */
	private static function lines_robots() {
		$line = Inite_Agents_Robots::line();
		if ( '' === $line ) {
			return array( esc_html__( 'No Content-Signal line stored yet.', 'inite-agents' ) );
		}
		if ( Inite_Agents_Robots::physical() ) {
			return array( esc_html__( 'A robots.txt file exists on the server, so WordPress does not generate one and this plugin cannot add to it. Paste this line under "User-agent: *" in that file:', 'inite-agents' ) . ' <code>' . esc_html( $line ) . '</code>' );
		}
		if ( ! Inite_Agents_Settings::on( 'robots' ) ) {
			return array();
		}
		/* translators: %s: the robots.txt line. */
		return array( sprintf( esc_html__( 'Adds %s to the "User-agent: *" group.', 'inite-agents' ), '<code>' . esc_html( $line ) . '</code>' ) );
	}

	/**
	 * Head.
	 *
	 * @return string[]
	 */
	private static function lines_head() {
		$lines = array();
		$org   = Inite_Agents_Conflicts::organization_schema();
		if ( '' !== $org ) {
			/* translators: %s: SEO plugin name. */
			$lines[] = sprintf( esc_html__( 'The Organization schema is left out of the JSON-LD: %s already prints one.', 'inite-agents' ), esc_html( $org ) );
		}
		$lines[] = esc_html__( 'The JSON-LD goes on the front page only. FAQPage is left out of a page that has an SEO plugin\'s FAQ block.', 'inite-agents' );
		return $lines;
	}

	/**
	 * Abilities.
	 *
	 * @return string[]
	 */
	private static function lines_abilities() {
		if ( ! Inite_Agents_Abilities::available() ) {
			return array( esc_html__( 'Needs WordPress 6.9 or later, which has the Abilities API.', 'inite-agents' ) );
		}
		if ( ! defined( 'WP_MCP_VERSION' ) ) {
			return array( esc_html__( 'Install and activate the MCP Adapter plugin so agents can reach them over MCP.', 'inite-agents' ) );
		}
		/* translators: %s: MCP endpoint URL. */
		return array( sprintf( esc_html__( 'The MCP Adapter lists them on its default server: %s', 'inite-agents' ), '<code>' . esc_html( rest_url( 'mcp/mcp-adapter-default-server' ) ) . '</code>' ) );
	}

	/**
	 * Ping.
	 *
	 * @return string[]
	 */
	private static function lines_ping() {
		$total = 0;
		foreach ( Inite_Agents_Visitors::rows() as $row ) {
			$total += $row['hits'];
		}
		/* translators: %d: number of reads. */
		return array( esc_html( sprintf( _n( 'AI agents read the served paths %d time in the last 7 days.', 'AI agents read the served paths %d times in the last 7 days.', $total, 'inite-agents' ), $total ) ) );
	}

	/**
	 * The connection block.
	 *
	 * @param array $copy   Stored copy.
	 * @param array $status Last attempt.
	 * @return string HTML.
	 */
	public static function connection( array $copy, array $status ) {
		$rows = array();
		if ( ! empty( $status['checked_at'] ) ) {
			$rows[ __( 'Last check', 'inite-agents' ) ] = esc_html( self::when( $status['checked_at'] ) ) . ' · ' . ( ! empty( $status['ok'] ) ? esc_html__( 'OK', 'inite-agents' ) : '<strong>' . esc_html( isset( $status['error'] ) ? $status['error'] : '' ) . '</strong>' );
		}
		if ( ! empty( $copy['version'] ) ) {
			$site = isset( $copy['manifest']['site'] ) ? $copy['manifest']['site'] : array();

			$rows[ __( 'Site', 'inite-agents' ) ]          = esc_html( isset( $site['domain'] ) ? $site['domain'] : '' );
			$rows[ __( 'Facts version', 'inite-agents' ) ] = esc_html( (string) $copy['version'] ) . ( ! empty( $site['confirmedAt'] ) ? ' · ' . esc_html__( 'confirmed', 'inite-agents' ) . ' ' . esc_html( self::when( strtotime( $site['confirmedAt'] ) ) ) : '' );
			$rows[ __( 'Last sync', 'inite-agents' ) ]     = esc_html( self::when( isset( $copy['synced_at'] ) ? $copy['synced_at'] : 0 ) );
			$rows[ __( 'Files', 'inite-agents' ) ]         = implode( ', ', array_map( static function ( $n ) {
				return '<code>' . esc_html( $n ) . '</code>';
			}, array_keys( isset( $copy['files'] ) ? $copy['files'] : array() ) ) );
			if ( ! empty( $copy['manifest']['mcp'] ) ) {
				$rows[ __( 'MCP server', 'inite-agents' ) ] = '<code>' . esc_html( $copy['manifest']['mcp'] ) . '</code>';
			}
		} else {
			$rows[ __( 'Stored copy', 'inite-agents' ) ] = esc_html__( 'none: nothing is served', 'inite-agents' );
		}
		$out = '<table class="widefat striped" style="max-width:780px"><tbody>';
		foreach ( $rows as $k => $v ) {
			$out .= '<tr><th scope="row" style="width:160px">' . esc_html( $k ) . '</th><td>' . $v . '</td></tr>';
		}
		return $out . '</tbody></table><p></p>';
	}

	/**
	 * A time as the site shows times.
	 *
	 * @param int $ts Unix time.
	 * @return string
	 */
	private static function when( $ts ) {
		return $ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $ts ) : __( 'never', 'inite-agents' );
	}
}
