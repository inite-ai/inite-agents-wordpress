<?php
/**
 * Requests from agents: the list of requests AI agents sent this business
 * through INITE (its MCP server, the WebMCP script), when the owner chose
 * "To the WordPress plugin" in the INITE cabinet. Read from inite.ai when
 * the screen opens, never stored on the site; marking a request answered or
 * spam is sent back to INITE. Nothing here runs while a visitor's page renders.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen: Requests from agents.
 */
final class Inite_Agents_Requests {

	const PAGE = 'inite-agents-requests';

	/** The statuses INITE keeps for a request. */
	const STATUSES = array( 'new', 'answered', 'spam' );

	/** Hook the screen and the mark action. */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_inite_agents_request_mark', array( __CLASS__, 'mark' ) );
	}

	/**
	 * Whether the owner sends requests to this plugin, as the last sync stored it.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$copy = Inite_Agents_Store::get();
		return ! empty( $copy['manifest']['features']['requests']['toPlugin'] );
	}

	/** The menu entry, only while requests come here. */
	public static function menu() {
		if ( ! self::enabled() ) {
			return;
		}
		add_menu_page(
			__( 'Requests from agents', 'inite-agents' ),
			__( 'Agent requests', 'inite-agents' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-format-chat',
			26
		);
	}

	/** "Mark answered" / "Mark spam" / "Mark new": sent to INITE, then back to the list. */
	public static function mark() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'inite-agents' ), 403 );
		}
		check_admin_referer( 'inite_agents_request_mark' );
		$id     = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$key    = Inite_Agents_Settings::get()['key'];
		$ok     = false;
		if ( '' !== $key && '' !== $id && in_array( $status, self::STATUSES, true ) && preg_match( '/^[a-z0-9]{8,40}$/', $id ) ) {
			$res = Inite_Agents_Client::post( '/api/site/v1/requests/' . rawurlencode( $id ), $key, array( 'status' => $status ) );
			$ok  = 200 === $res['status'];
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&marked=' . ( $ok ? '1' : '0' ) ) );
		exit;
	}

	/**
	 * One page of requests from INITE.
	 *
	 * @param string $status Status filter, or ''.
	 * @param int    $page   Page number.
	 * @return array{ok:bool,error:string,leads:array,total:int,counts:array}
	 */
	private static function fetch( $status, $page ) {
		$key = Inite_Agents_Settings::get()['key'];
		if ( '' === $key ) {
			return array( 'ok' => false, 'error' => __( 'No site key is saved.', 'inite-agents' ), 'leads' => array(), 'total' => 0, 'counts' => array() );
		}
		$query = '?page=' . max( 1, (int) $page ) . ( in_array( $status, self::STATUSES, true ) ? '&status=' . $status : '' );
		$res   = Inite_Agents_Client::get( '/api/site/v1/requests' . $query, $key );
		$body  = json_decode( $res['body'], true );
		if ( 200 !== $res['status'] || ! is_array( $body ) ) {
			$error = is_array( $body ) && isset( $body['message'] ) ? (string) $body['message'] : ( '' !== $res['error'] ? $res['error'] : __( 'INITE did not answer. Try again in a moment.', 'inite-agents' ) );
			return array( 'ok' => false, 'error' => $error, 'leads' => array(), 'total' => 0, 'counts' => array() );
		}
		return array(
			'ok'     => true,
			'error'  => '',
			'leads'  => isset( $body['leads'] ) && is_array( $body['leads'] ) ? $body['leads'] : array(),
			'total'  => isset( $body['total'] ) ? (int) $body['total'] : 0,
			'counts' => isset( $body['counts'] ) && is_array( $body['counts'] ) ? $body['counts'] : array(),
		);
	}

	/**
	 * A field of a request as text.
	 *
	 * @param array  $lead Request.
	 * @param string $key  Field.
	 * @return string
	 */
	private static function field( $lead, $key ) {
		return isset( $lead[ $key ] ) && is_scalar( $lead[ $key ] ) ? (string) $lead[ $key ] : '';
	}

	/**
	 * When the person would like it: the windows, then the words.
	 *
	 * @param array $lead Request.
	 * @return string
	 */
	private static function when( $lead ) {
		$parts = array();
		if ( isset( $lead['windows'] ) && is_array( $lead['windows'] ) ) {
			foreach ( $lead['windows'] as $w ) {
				if ( is_array( $w ) && isset( $w['date'] ) ) {
					$parts[] = trim( $w['date'] . ' ' . ( isset( $w['from'] ) ? $w['from'] : '' ) . ( isset( $w['to'] ) ? '-' . $w['to'] : '' ) );
				}
			}
		}
		$words = self::field( $lead, 'preferredTime' );
		if ( '' !== $words ) {
			$parts[] = $words;
		}
		return implode( '; ', $parts );
	}

	/** The screen. */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the filter and the page only pick what to show.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$marked = isset( $_GET['marked'] ) ? sanitize_key( wp_unslash( $_GET['marked'] ) ) : '';
		// phpcs:enable
		$got = self::fetch( $status, $page );

		echo '<div class="wrap"><h1>' . esc_html__( 'Requests from agents', 'inite-agents' ) . '</h1>';
		echo '<p>' . esc_html__( 'AI agents sent these requests to your business for the people they help, with their agreement. Reply to each person yourself: nothing was confirmed or booked for you.', 'inite-agents' ) . '</p>';
		if ( '1' === $marked ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved.', 'inite-agents' ) . '</p></div>';
		} elseif ( '0' === $marked ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'That did not go through. Try again.', 'inite-agents' ) . '</p></div>';
		}
		if ( ! $got['ok'] ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $got['error'] ) . '</p></div></div>';
			return;
		}

		$labels = array(
			''         => __( 'All', 'inite-agents' ),
			'new'      => __( 'New', 'inite-agents' ),
			'answered' => __( 'Answered', 'inite-agents' ),
			'spam'     => __( 'Spam', 'inite-agents' ),
		);
		echo '<ul class="subsubsub">';
		$links = array();
		foreach ( $labels as $key => $label ) {
			$count   = '' === $key ? '' : ' <span class="count">(' . (int) ( isset( $got['counts'][ $key ] ) ? $got['counts'][ $key ] : 0 ) . ')</span>';
			$url     = admin_url( 'admin.php?page=' . self::PAGE . ( '' !== $key ? '&status=' . $key : '' ) );
			$links[] = '<li><a href="' . esc_url( $url ) . '"' . ( $status === $key ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>' . $count . '</li>';
		}
		echo implode( ' | ', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.

		echo '<table class="widefat striped" style="clear:both"><thead><tr>';
		foreach ( array( __( 'Received', 'inite-agents' ), __( 'Person', 'inite-agents' ), __( 'Request', 'inite-agents' ), __( 'Status', 'inite-agents' ) ) as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $got['leads'] ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No requests yet.', 'inite-agents' ) . '</td></tr>';
		}
		foreach ( $got['leads'] as $lead ) {
			if ( ! is_array( $lead ) ) {
				continue;
			}
			$email = self::field( $lead, 'email' );
			$phone = self::field( $lead, 'phone' );
			$at    = strtotime( self::field( $lead, 'createdAt' ) );
			echo '<tr><td>' . esc_html( $at ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $at ) : '' ) . '</td>';
			echo '<td><strong>' . esc_html( self::field( $lead, 'name' ) ) . '</strong>';
			if ( '' !== $email ) {
				echo '<br><a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
			}
			if ( '' !== $phone ) {
				echo '<br><a href="' . esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
			}
			echo '</td><td>';
			echo '<strong>' . esc_html( 'booking' === self::field( $lead, 'kind' ) ? __( 'Booking request', 'inite-agents' ) : __( 'Request', 'inite-agents' ) ) . '</strong>';
			$service = self::field( $lead, 'service' );
			if ( '' !== $service ) {
				echo '<br>' . esc_html__( 'Service:', 'inite-agents' ) . ' ' . esc_html( $service );
			}
			$when = self::when( $lead );
			if ( '' !== $when ) {
				echo '<br>' . esc_html__( 'When:', 'inite-agents' ) . ' ' . esc_html( $when );
			}
			$party = self::field( $lead, 'partySize' );
			if ( '' !== $party ) {
				echo '<br>' . esc_html__( 'People:', 'inite-agents' ) . ' ' . esc_html( $party );
			}
			$message = self::field( $lead, 'message' );
			if ( '' !== $message ) {
				echo '<p style="white-space:pre-wrap">' . esc_html( $message ) . '</p>';
			}
			echo '<span class="description">' . esc_html( sprintf( /* translators: %s: the agent's product name */ __( 'Sent by %s', 'inite-agents' ), self::field( $lead, 'client' ) ) ) . '</span></td>';
			$current = self::field( $lead, 'status' );
			echo '<td>' . esc_html( isset( $labels[ $current ] ) ? $labels[ $current ] : $current );
			foreach ( self::STATUSES as $to ) {
				if ( $to === $current ) {
					continue;
				}
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:4px">';
				echo '<input type="hidden" name="action" value="inite_agents_request_mark">';
				echo '<input type="hidden" name="id" value="' . esc_attr( self::field( $lead, 'id' ) ) . '">';
				echo '<input type="hidden" name="status" value="' . esc_attr( $to ) . '">';
				wp_nonce_field( 'inite_agents_request_mark' );
				$button = array(
					'answered' => __( 'Mark answered', 'inite-agents' ),
					'spam'     => __( 'Mark spam', 'inite-agents' ),
					'new'      => __( 'Mark new', 'inite-agents' ),
				);
				submit_button( $button[ $to ], 'small', 'submit', false );
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		$pages = (int) ceil( $got['total'] / 25 );
		if ( $pages > 1 ) {
			echo '<p>';
			for ( $i = 1; $i <= $pages && $i <= 40; $i++ ) {
				$url = admin_url( 'admin.php?page=' . self::PAGE . '&paged=' . $i . ( '' !== $status ? '&status=' . $status : '' ) );
				echo $i === $page ? '<strong>' . (int) $i . '</strong> ' : '<a href="' . esc_url( $url ) . '">' . (int) $i . '</a> ';
			}
			echo '</p>';
		}
		echo '<p class="description">' . esc_html__( 'INITE keeps each request for 180 days, then deletes it. Choose where requests go in the "For agents" tab of your INITE cabinet.', 'inite-agents' ) . '</p></div>';
	}
}
