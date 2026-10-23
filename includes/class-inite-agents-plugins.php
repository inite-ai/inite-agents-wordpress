<?php
/**
 * What the site's own booking and location plugins already publish, read
 * from their options and tables when they are active, for the groups the
 * owner switched on. Sent with the basics (Inite_Agents_Basics) and shown in
 * the INITE cabinet as suggestions to accept or ignore; nothing is published
 * by itself.
 *
 *   services   (send_services)  Bookly and Amelia: the services they show to
 *              customers, with the duration and the price in the plugin's
 *              currency. Hidden or private services are left out.
 *   locations  (send_locations) WP Store Locator, Yoast Local SEO and Rank
 *              Math's Local SEO: each address with its city, country and
 *              phone, and the week's opening hours where the plugin keeps them.
 *
 * Nothing about a person: no staff member, customer or appointment is read.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Readers of other plugins' public data.
 */
final class Inite_Agents_Plugins {

	/** As the facts cap them (lib/site-facts/schema.ts). */
	const SERVICES  = 60;
	const LOCATIONS = 20;
	const TEXT      = 300;

	const DAYS = array(
		'monday'    => 'mon',
		'tuesday'   => 'tue',
		'wednesday' => 'wed',
		'thursday'  => 'thu',
		'friday'    => 'fri',
		'saturday'  => 'sat',
		'sunday'    => 'sun',
	);

	/**
	 * The booking plugins found on this site, by the name INITE knows them.
	 *
	 * @return string[]
	 */
	public static function booking() {
		$out = array();
		if ( self::table( 'bookly_services' ) && ( class_exists( '\Bookly\Lib\Plugin' ) || defined( 'BOOKLY_PATH' ) || get_option( 'bookly_data_loaded' ) ) ) {
			$out[] = 'bookly';
		}
		if ( self::table( 'amelia_services' ) && ( defined( 'AMELIA_VERSION' ) || defined( 'AMELIA_PATH' ) || false !== get_option( 'amelia_settings', false ) ) ) {
			$out[] = 'amelia';
		}
		return $out;
	}

	/**
	 * The location plugins found on this site.
	 *
	 * @return string[]
	 */
	public static function places() {
		$out = array();
		if ( post_type_exists( 'wpsl_stores' ) ) {
			$out[] = 'wp-store-locator';
		}
		if ( defined( 'WPSEO_LOCAL_VERSION' ) || is_array( get_option( 'wpseo_local' ) ) ) {
			$out[] = 'yoast-local';
		}
		$modules = get_option( 'rank_math_modules', array() );
		if ( defined( 'RANK_MATH_VERSION' ) && is_array( $modules ) && in_array( 'local-seo', $modules, true ) ) {
			$out[] = 'rank-math-local';
		}
		return $out;
	}

	/**
	 * Whether a table of another plugin exists, by its name without the prefix.
	 *
	 * @param string $name Table name.
	 * @return bool
	 */
	private static function table( $name ) {
		global $wpdb;
		$table = $wpdb->prefix . $name;
		// A schema lookup by an exact name, once per hourly sync; nothing to cache across requests.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * One line of text at the facts' cap.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	private static function text( $v ) {
		$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $v ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $t, 0, self::TEXT ) : substr( $t, 0, self::TEXT );
	}

	/**
	 * A service in the facts' shape.
	 *
	 * @param string $name     Name.
	 * @param string $info     Description.
	 * @param int    $seconds  Duration.
	 * @param mixed  $price    Price.
	 * @param string $currency Currency.
	 * @return array|null
	 */
	private static function service( $name, $info, $seconds, $price, $currency ) {
		$name = self::text( $name );
		if ( '' === $name ) {
			return null;
		}
		$minutes = (int) round( (int) $seconds / 60 );
		$about   = trim( ( $minutes > 0 ? sprintf( '%d min. ', $minutes ) : '' ) . self::text( $info ) );
		$out     = array( 'name' => $name );
		if ( '' !== $about ) {
			$out['description'] = $about;
		}
		if ( is_numeric( $price ) && (float) $price > 0 && preg_match( '/^[A-Za-z]{3}$/', (string) $currency ) ) {
			$out['price'] = array(
				'amount'   => round( (float) $price, 2 ),
				'currency' => strtoupper( (string) $currency ),
				'unit'     => 'session',
			);
		}
		return $out;
	}

	/**
	 * The public services of the booking plugins.
	 *
	 * @return array{services:array,from:string[]}
	 */
	public static function services() {
		global $wpdb;
		$services = array();
		$from     = array();
		foreach ( self::booking() as $plugin ) {
			$rows     = array();
			$currency = '';
			if ( 'bookly' === $plugin ) {
				$currency = (string) get_option( 'bookly_pmt_currency', '' );
				// Bookly keeps its services in its own table; only those it shows to customers.
				$table = esc_sql( $wpdb->prefix . 'bookly_services' );
				// The table name is WordPress's own prefix and a fixed name, never input (the identifier placeholder needs WordPress 6.2).
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT title AS name, info, duration, price FROM {$table} WHERE visibility = %s ORDER BY id LIMIT %d", 'public', self::SERVICES ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$settings = get_option( 'amelia_settings', '' );
				$settings = is_array( $settings ) ? $settings : json_decode( (string) $settings, true );
				$currency = is_array( $settings ) && isset( $settings['payments']['currency'] ) ? (string) $settings['payments']['currency'] : '';
				// Amelia keeps its services in its own table; only the visible ones.
				$table = esc_sql( $wpdb->prefix . 'amelia_services' );
				// As above: a fixed table name under WordPress's prefix.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT name, description AS info, duration, price FROM {$table} WHERE status = %s ORDER BY id LIMIT %d", 'visible', self::SERVICES ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$before = count( $services );
			foreach ( is_array( $rows ) ? $rows : array() as $r ) {
				$s = self::service( isset( $r['name'] ) ? $r['name'] : '', isset( $r['info'] ) ? $r['info'] : '', isset( $r['duration'] ) ? $r['duration'] : 0, isset( $r['price'] ) ? $r['price'] : null, $currency );
				if ( $s && count( $services ) < self::SERVICES ) {
					$services[] = $s;
				}
			}
			if ( count( $services ) > $before ) {
				$from[] = $plugin;
			}
		}
		return array(
			'services' => $services,
			'from'     => $from,
		);
	}

	/**
	 * "09:00", from "9:00", "9:00 AM" or "17:30".
	 *
	 * @param string $t A time as a plugin writes it.
	 * @return string '' when it does not read.
	 */
	public static function hhmm( $t ) {
		$t = strtoupper( trim( (string) $t ) );
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})\s*(AM|PM)?$/', $t, $m ) ) {
			return '';
		}
		$h = (int) $m[1];
		if ( isset( $m[3] ) && 'PM' === $m[3] && $h < 12 ) {
			$h += 12;
		}
		if ( isset( $m[3] ) && 'AM' === $m[3] && 12 === $h ) {
			$h = 0;
		}
		return $h > 24 || (int) $m[2] > 59 ? '' : sprintf( '%02d:%s', $h, $m[2] );
	}

	/**
	 * Rows of the facts' hours from day => [[opens, closes], ...], days with the same times together.
	 *
	 * @param array $week Day (mon..sun) to pairs.
	 * @return array
	 */
	public static function hours( array $week ) {
		$by = array();
		foreach ( self::DAYS as $day ) {
			foreach ( isset( $week[ $day ] ) ? $week[ $day ] : array() as $pair ) {
				$o = self::hhmm( $pair[0] );
				$c = self::hhmm( $pair[1] );
				if ( '' !== $o && '' !== $c ) {
					$by[ $o . '-' . $c ][] = $day;
				}
			}
		}
		$out = array();
		foreach ( $by as $span => $days ) {
			list( $o, $c ) = explode( '-', $span );
			$out[]         = array(
				'days'   => $days,
				'opens'  => $o,
				'closes' => $c,
			);
		}
		return array_slice( $out, 0, 21 );
	}

	/**
	 * A place in the facts' shape, or null without an address.
	 *
	 * @param array $p street, city, country, phone.
	 * @return array|null
	 */
	private static function place( array $p ) {
		$address = self::text( isset( $p['street'] ) ? $p['street'] : '' );
		if ( '' === $address ) {
			return null;
		}
		$out = array( 'address' => $address );
		foreach ( array( 'city', 'country', 'phone' ) as $k ) {
			if ( ! empty( $p[ $k ] ) ) {
				$out[ $k ] = self::text( $p[ $k ] );
			}
		}
		return $out;
	}

	/**
	 * The locations and the week's hours the location plugins keep.
	 *
	 * @return array{locations:array,hours:array,from:string[]}
	 */
	public static function locations() {
		$locations = array();
		$hours     = array();
		$from      = array();
		foreach ( self::places() as $plugin ) {
			$found = 'wp-store-locator' === $plugin ? self::store_locator() : ( 'yoast-local' === $plugin ? self::yoast_local() : self::rank_math_local() );
			if ( $found['locations'] || $found['hours'] ) {
				$from[] = $plugin;
			}
			foreach ( $found['locations'] as $l ) {
				if ( count( $locations ) < self::LOCATIONS ) {
					$locations[] = $l;
				}
			}
			if ( ! $hours && $found['hours'] ) {
				$hours = $found['hours'];
			}
		}
		return array(
			'locations' => $locations,
			'hours'     => $hours,
			'from'      => $from,
		);
	}

	/**
	 * WP Store Locator: one published store post per place, its hours in `wpsl_hours`.
	 *
	 * @return array{locations:array,hours:array}
	 */
	private static function store_locator() {
		$stores = get_posts(
			array(
				'post_type'   => 'wpsl_stores',
				'post_status' => 'publish',
				'numberposts' => self::LOCATIONS,
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
			)
		);
		$out   = array();
		$hours = array();
		foreach ( $stores as $s ) {
			$m = function ( $k ) use ( $s ) {
				return (string) get_post_meta( $s->ID, 'wpsl_' . $k, true );
			};
			$p = self::place(
				array(
					'street'  => trim( $m( 'address' ) . ' ' . $m( 'address2' ) ),
					'city'    => $m( 'city' ),
					'country' => $m( 'country' ),
					'phone'   => $m( 'phone' ),
				)
			);
			if ( $p ) {
				$out[] = $p;
			}
			$raw = get_post_meta( $s->ID, 'wpsl_hours', true );
			if ( ! $hours && is_array( $raw ) ) {
				$week = array();
				foreach ( self::DAYS as $name => $day ) {
					foreach ( isset( $raw[ $name ] ) && is_array( $raw[ $name ] ) ? $raw[ $name ] : array() as $span ) {
						$pair = explode( ',', (string) $span );
						if ( 2 === count( $pair ) ) {
							$week[ $day ][] = $pair;
						}
					}
				}
				$hours = self::hours( $week );
			}
		}
		return array(
			'locations' => $out,
			'hours'     => $hours,
		);
	}

	/**
	 * Yoast Local SEO, one location: its address and hours in the `wpseo_local` option.
	 *
	 * @return array{locations:array,hours:array}
	 */
	private static function yoast_local() {
		$o = get_option( 'wpseo_local', array() );
		if ( ! is_array( $o ) ) {
			return array(
				'locations' => array(),
				'hours'     => array(),
			);
		}
		$g = function ( $k ) use ( $o ) {
			return isset( $o[ $k ] ) ? (string) $o[ $k ] : '';
		};
		$p     = self::place(
			array(
				'street'  => trim( $g( 'location_address' ) . ' ' . $g( 'location_address_2' ) ),
				'city'    => $g( 'location_city' ),
				'country' => $g( 'location_country' ),
				'phone'   => $g( 'location_phone' ),
			)
		);
		$week  = array();
		$shown = empty( $o['hide_opening_hours'] ) || 'off' === $o['hide_opening_hours'];
		foreach ( self::DAYS as $name => $day ) {
			$from = $g( 'opening_hours_' . $name . '_from' );
			$to   = $g( 'opening_hours_' . $name . '_to' );
			if ( $shown && '' !== $from && 'closed' !== $from && '' !== $to ) {
				$week[ $day ][] = array( $from, $to );
			}
		}
		return array(
			'locations' => $p ? array( $p ) : array(),
			'hours'     => self::hours( $week ),
		);
	}

	/**
	 * Rank Math's Local SEO: the address, phone and hours in its titles options.
	 *
	 * @return array{locations:array,hours:array}
	 */
	private static function rank_math_local() {
		$o = get_option( 'rank-math-options-titles', array() );
		if ( ! is_array( $o ) ) {
			return array(
				'locations' => array(),
				'hours'     => array(),
			);
		}
		$a     = isset( $o['local_address'] ) && is_array( $o['local_address'] ) ? $o['local_address'] : array();
		$phone = '';
		if ( ! empty( $o['phone_numbers'] ) && is_array( $o['phone_numbers'] ) ) {
			$first = reset( $o['phone_numbers'] );
			$phone = is_array( $first ) && isset( $first['number'] ) ? (string) $first['number'] : '';
		}
		$p    = self::place(
			array(
				'street'  => isset( $a['streetAddress'] ) ? $a['streetAddress'] : '',
				'city'    => isset( $a['addressLocality'] ) ? $a['addressLocality'] : '',
				'country' => isset( $a['addressCountry'] ) ? $a['addressCountry'] : '',
				'phone'   => $phone,
			)
		);
		$week = array();
		foreach ( isset( $o['opening_hours'] ) && is_array( $o['opening_hours'] ) ? $o['opening_hours'] : array() as $row ) {
			$day  = is_array( $row ) && isset( $row['day'] ) ? strtolower( (string) $row['day'] ) : '';
			$time = is_array( $row ) && isset( $row['time'] ) ? explode( '-', (string) $row['time'] ) : array();
			if ( isset( self::DAYS[ $day ] ) && 2 === count( $time ) ) {
				$week[ self::DAYS[ $day ] ][] = $time;
			}
		}
		return array(
			'locations' => $p ? array( $p ) : array(),
			'hours'     => self::hours( $week ),
		);
	}
}
