<?php
/**
 * Settings → INITE Agents, "What this site sends to INITE": one switch per
 * kind of data, each saying exactly which fields leave the site and what
 * INITE does with them, and what was sent last. Every switch is off until
 * the owner turns it on; a kind switched off is deleted at INITE at the next
 * run (Inite_Agents_Records).
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The sending section of the settings screen.
 */
final class Inite_Agents_Admin_Sending {

	/**
	 * Each switch: its label, the fields it sends and what INITE does with them.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function sends() {
		return array(
			'send_basics'    => array( __( 'Site basics', 'inite-agents' ), __( 'Send the site title and tagline (Settings → General), the site language and address, and with WooCommerce the number of published products. INITE suggests them as your business name and description in the cabinet; nothing is published until you accept and confirm it.', 'inite-agents' ) ),
			'send_content'   => array( __( 'Pages and posts', 'inite-agents' ), __( 'Send each published page and post that has no password and is not marked noindex: its title, address, excerpt or first 300 characters, type, language and when it changed. INITE lists them in llms.txt and llms-full.txt. Up to 2,000.', 'inite-agents' ) ),
			'send_products'  => array( __( 'Products', 'inite-agents' ), __( 'Send each WooCommerce product shown in the catalogue: name, address, price, regular and sale price, currency, stock status, SKU, short description, categories and image address. Nothing is published until you turn on "Publish my catalogue" in the INITE cabinet; from then on agents get your products as your store holds them, through llms.txt and the MCP server. Up to 2,000.', 'inite-agents' ) ),
			'send_events'    => array( __( 'Events', 'inite-agents' ), __( 'Send each upcoming event of The Events Calendar: title, address, excerpt, start, end and venue. INITE lists them in llms.txt and llms-full.txt. Up to 200.', 'inite-agents' ) ),
			'send_services'  => array( __( 'Services from booking plugins', 'inite-agents' ), __( 'Send the services Bookly or Amelia show to customers: name, duration, description, price and currency. INITE shows them in the cabinet as suggestions for your services; nothing is published until you accept and confirm.', 'inite-agents' ) ),
			'send_locations' => array( __( 'Locations and hours', 'inite-agents' ), __( 'Send the addresses, phone numbers and opening hours that WP Store Locator, Yoast Local SEO or Rank Math Local SEO keep. INITE shows them in the cabinet as suggestions; nothing is published until you accept and confirm.', 'inite-agents' ) ),
		);
	}

	/**
	 * The section: a heading, what it promises, and a row per switch.
	 *
	 * @param array  $s    The settings.
	 * @param string $name The option's name.
	 */
	public static function render( array $s, $name ) {
		echo '<h2>' . esc_html__( 'What this site sends to INITE', 'inite-agents' ) . '</h2>';
		echo '<p>' . esc_html__( 'Nothing below leaves this site until you switch it on. Each is content your site already shows to anyone, and nothing about a person: no user, customer, order or visitor. Switch one off and INITE deletes what it received of that kind at the next run.', 'inite-agents' ) . '</p>';
		echo '<table class="form-table" role="presentation">';
		foreach ( self::sends() as $feature => $label ) {
			printf(
				'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="%2$s[features][%3$s]" value="1" %4$s> %5$s</label>%6$s</td></tr>',
				esc_html( $label[0] ),
				esc_attr( $name ),
				esc_attr( $feature ),
				checked( ! empty( $s['features'][ $feature ] ), true, false ),
				esc_html( $label[1] ),
				wp_kses_post( Inite_Agents_Admin_Status::feature( $feature ) )
			);
		}
		echo '</table>';
	}

	/**
	 * A date as the site writes dates.
	 *
	 * @param int $at Unix time.
	 * @return string
	 */
	private static function when( $at ) {
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $at ) );
	}

	/**
	 * The lines under one switch: whether there is anything to send here, and what was sent last.
	 *
	 * @param string $feature One of Inite_Agents_Settings::SENDS.
	 * @return string[] Escaped HTML.
	 */
	public static function lines( $feature ) {
		$group = array_search( $feature, Inite_Agents_Collect::GROUPS, true );
		if ( false !== $group ) {
			return self::group_lines( (string) $group, $feature );
		}
		$lines = array();
		if ( 'send_services' === $feature ) {
			$found = Inite_Agents_Plugins::booking();
			$lines[] = $found ? sprintf(
				/* translators: %s: plugin names. */
				esc_html__( 'Found: %s.', 'inite-agents' ),
				esc_html( implode( ', ', $found ) )
			) : esc_html__( 'Neither Bookly nor Amelia is active, so there is nothing to send.', 'inite-agents' );
		}
		if ( 'send_locations' === $feature ) {
			$found = Inite_Agents_Plugins::places();
			$lines[] = $found ? sprintf(
				/* translators: %s: plugin names. */
				esc_html__( 'Found: %s.', 'inite-agents' ),
				esc_html( implode( ', ', $found ) )
			) : esc_html__( 'None of WP Store Locator, Yoast Local SEO or Rank Math Local SEO is active, so there is nothing to send.', 'inite-agents' );
		}
		$sent = get_option( Inite_Agents_Basics::OPTION, array() );
		if ( Inite_Agents_Settings::on( $feature ) && is_array( $sent ) && ! empty( $sent['at'] ) ) {
			/* translators: %s: date and time. */
			$lines[] = sprintf( esc_html__( 'Last sent with the site basics: %s.', 'inite-agents' ), self::when( $sent['at'] ) );
		}
		return $lines;
	}

	/**
	 * The lines of a group the records sender sends.
	 *
	 * @param string $group   Group.
	 * @param string $feature Its switch.
	 * @return string[]
	 */
	private static function group_lines( $group, $feature ) {
		if ( ! Inite_Agents_Collect::available( $group ) ) {
			return array( 'products' === $group ? esc_html__( 'WooCommerce is not active, so there is nothing to send.', 'inite-agents' ) : esc_html__( 'The Events Calendar is not active, so there is nothing to send.', 'inite-agents' ) );
		}
		$state = Inite_Agents_Records::state();
		$g     = isset( $state[ $group ] ) && is_array( $state[ $group ] ) ? $state[ $group ] : array();
		$lines = array();
		if ( ! empty( $g['clear'] ) ) {
			$lines[] = esc_html__( 'Switched off: INITE is told to delete what it received, at the next run.', 'inite-agents' );
			return $lines;
		}
		if ( ! Inite_Agents_Settings::on( $feature ) ) {
			return $lines;
		}
		if ( ! empty( $g['error'] ) ) {
			/* translators: %s: HTTP status or network error. */
			$lines[] = sprintf( esc_html__( 'The last send failed (%s). It is tried again within the hour.', 'inite-agents' ), esc_html( $g['error'] ) );
		}
		if ( ! empty( $g['answer']['at'] ) && isset( $g['answer']['held'], $g['answer']['cap'] ) ) {
			$lines[] = sprintf(
				/* translators: 1: date and time, 2: items INITE holds, 3: the most it holds. */
				esc_html__( 'Last sent %1$s. INITE holds %2$s of at most %3$s.', 'inite-agents' ),
				self::when( $g['answer']['at'] ),
				esc_html( number_format_i18n( (int) $g['answer']['held'] ) ),
				esc_html( number_format_i18n( (int) $g['answer']['cap'] ) )
			);
		} elseif ( empty( $g['error'] ) ) {
			$lines[] = esc_html__( 'Not sent yet: the first send runs within a few minutes, then every hour.', 'inite-agents' );
		}
		return $lines;
	}
}
