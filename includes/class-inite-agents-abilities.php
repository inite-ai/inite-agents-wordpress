<?php
/**
 * Read-only abilities (WordPress 6.9+ Abilities API) answering from the
 * confirmed facts the last sync stored, so the official MCP Adapter lists
 * them on its default server as tools any connected agent can call.
 *
 * They are the same five the business's own MCP server at
 * <slug>.inite.ai answers: business facts, services, hours, locations and
 * FAQ. Reading them needs no capability: they are what the business
 * published. They change nothing on the site. Without the Abilities API,
 * with the feature off, or before a first sync, nothing is registered.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abilities.
 */
final class Inite_Agents_Abilities {

	const CATEGORY = 'inite-business';

	/** Hook in, only while the feature is on and the API exists. */
	public static function register() {
		if ( ! Inite_Agents_Settings::on( 'abilities' ) || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'abilities' ) );
	}

	/**
	 * Whether the API exists on this WordPress.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * The stored public facts, or null before a first sync.
	 *
	 * @return array|null
	 */
	private static function facts() {
		$copy = Inite_Agents_Store::get();
		return isset( $copy['facts'] ) && is_array( $copy['facts'] ) ? $copy['facts'] : null;
	}

	/** The category the five abilities belong to. */
	public static function category() {
		if ( null === self::facts() || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Business facts', 'inite-agents' ),
				'description' => __( 'Facts this business confirmed in INITE for AI agents: what it is, its services and prices, hours, locations and answers to common questions.', 'inite-agents' ),
			)
		);
	}

	/** The five abilities. */
	public static function abilities() {
		if ( null === self::facts() || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$confirmed = __( 'Only what the business confirmed is returned; nothing is estimated.', 'inite-agents' );
		$query     = array(
			'type'                 => 'object',
			'properties'           => array(
				'query' => array(
					'type'        => 'string',
					'maxLength'   => 120,
					'description' => __( 'Optional words to narrow the list; omitted, everything is returned.', 'inite-agents' ),
				),
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
		$none      = array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
			'default'              => array(),
		);
		$list      = static function ( $key, array $item ) {
			return array(
				'type'       => 'object',
				'properties' => array(
					$key            => array(
						'type'  => 'array',
						'items' => $item,
					),
					'facts_version' => array( 'type' => 'integer' ),
					'confirmed_at'  => array( 'type' => 'string' ),
				),
				'required'   => array( $key, 'facts_version' ),
			);
		};
		$defs      = array(
			'business-facts' => array(
				__( 'Business facts', 'inite-agents' ),
				__( 'What this business is: its name, what it does in one line, its kind, the languages it serves in, and how to reach it (email, phone, booking and contact pages).', 'inite-agents' ),
				$none,
				array(
					'type'       => 'object',
					'properties' => array(
						'name'          => array( 'type' => 'string' ),
						'line'          => array( 'type' => 'string' ),
						'kind'          => array( 'type' => 'string' ),
						'languages'     => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'website'       => array( 'type' => 'string' ),
						'contacts'      => array( 'type' => 'object' ),
						'facts_version' => array( 'type' => 'integer' ),
						'confirmed_at'  => array( 'type' => 'string' ),
					),
					'required'   => array( 'name', 'facts_version' ),
				),
			),
			'services'       => array(
				__( 'Services and prices', 'inite-agents' ),
				__( 'The services this business offers, each with its price exactly as the business confirmed it (amount, currency and unit), "on request" where the business said so, or no price where none was confirmed. Never an estimate.', 'inite-agents' ),
				$query,
				$list( 'services', array( 'type' => 'object' ) ),
			),
			'hours'          => array(
				__( 'Opening hours', 'inite-agents' ),
				__( 'The opening hours in the business\'s own time zone, and whether it is open right now by that clock.', 'inite-agents' ),
				$none,
				array(
					'type'       => 'object',
					'properties' => array(
						'timezone'      => array( 'type' => 'string' ),
						'hours'         => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'open_now'      => array( 'type' => array( 'boolean', 'null' ) ),
						'facts_version' => array( 'type' => 'integer' ),
						'confirmed_at'  => array( 'type' => 'string' ),
					),
					'required'   => array( 'hours', 'facts_version' ),
				),
			),
			'locations'      => array(
				__( 'Locations', 'inite-agents' ),
				__( 'Where the business is: each address with its city, country, phone and map link.', 'inite-agents' ),
				$none,
				$list( 'locations', array( 'type' => 'object' ) ),
			),
			'faq'            => array(
				__( 'Questions and answers', 'inite-agents' ),
				__( 'The business\'s own answers to common questions.', 'inite-agents' ),
				$query,
				$list( 'faq', array( 'type' => 'object' ) ),
			),
		);
		foreach ( $defs as $name => $def ) {
			wp_register_ability(
				'inite/' . $name,
				array(
					'label'               => $def[0],
					'description'         => $def[1] . ' ' . $confirmed,
					'category'            => self::CATEGORY,
					'input_schema'        => $def[2],
					'output_schema'       => $def[3],
					'execute_callback'    => static function ( $input = array() ) use ( $name ) {
						return Inite_Agents_Abilities::answer( $name, is_array( $input ) ? $input : array() );
					},
					'permission_callback' => '__return_true',
					'meta'                => array(
						'annotations'  => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
						'public'       => true,
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
							'type'   => 'tool',
						),
					),
				)
			);
		}
	}

	/**
	 * One ability's answer from the stored facts.
	 *
	 * @param string $name  Ability name without the namespace.
	 * @param array  $input Validated input.
	 * @param int    $now   Unix time, for open_now.
	 * @return array|WP_Error
	 */
	public static function answer( $name, array $input, $now = null ) {
		$f = self::facts();
		if ( null === $f ) {
			return new WP_Error( 'inite_no_facts', __( 'No confirmed facts are stored yet.', 'inite-agents' ) );
		}
		$stamp = array(
			'facts_version' => isset( $f['version'] ) ? (int) $f['version'] : 0,
			'confirmed_at'  => isset( $f['confirmedAt'] ) ? (string) $f['confirmedAt'] : '',
		);
		$q     = isset( $input['query'] ) ? (string) $input['query'] : '';
		switch ( $name ) {
			case 'business-facts':
				$b   = isset( $f['business'] ) && is_array( $f['business'] ) ? $f['business'] : array();
				$out = array(
					'name'      => isset( $b['name'] ) ? (string) $b['name'] : '',
					'line'      => isset( $b['line'] ) ? (string) $b['line'] : '',
					'languages' => isset( $b['languages'] ) && is_array( $b['languages'] ) ? array_values( $b['languages'] ) : array(),
					'website'   => ! empty( $f['domain'] ) ? 'https://' . $f['domain'] : '',
					'contacts'  => isset( $f['contacts'] ) && is_array( $f['contacts'] ) ? (object) $f['contacts'] : (object) array(),
				);
				if ( ! empty( $b['kind'] ) ) {
					$out['kind'] = (string) $b['kind'];
				}
				return $out + $stamp;
			case 'services':
				return array( 'services' => self::narrow( self::rows( $f, 'services' ), $q, array( 'name', 'description' ) ) ) + $stamp;
			case 'hours':
				$hours = self::rows( $f, 'hours' );
				$tz    = isset( $f['timezone'] ) ? (string) $f['timezone'] : '';
				return array(
					'timezone' => $tz,
					'hours'    => $hours,
					'open_now' => self::open_now( $hours, $tz, null === $now ? time() : (int) $now ),
				) + $stamp;
			case 'locations':
				return array( 'locations' => self::rows( $f, 'locations' ) ) + $stamp;
			case 'faq':
				return array( 'faq' => self::narrow( self::rows( $f, 'faq' ), $q, array( 'q', 'a' ) ) ) + $stamp;
		}
		return new WP_Error( 'inite_unknown', __( 'Unknown ability.', 'inite-agents' ) );
	}

	/**
	 * A list of the facts as objects.
	 *
	 * @param array  $f   Facts.
	 * @param string $key List key.
	 * @return array
	 */
	private static function rows( array $f, $key ) {
		$out = array();
		foreach ( isset( $f[ $key ] ) && is_array( $f[ $key ] ) ? $f[ $key ] : array() as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Rows whose fields contain every word of the query.
	 *
	 * @param array  $rows   Rows.
	 * @param string $query  Words.
	 * @param array  $fields Fields searched.
	 * @return array
	 */
	private static function narrow( array $rows, $query, array $fields ) {
		$words = array_filter( preg_split( '/\s+/u', trim( $query ) ) );
		if ( ! $words ) {
			return $rows;
		}
		$out = array();
		foreach ( $rows as $row ) {
			$text = '';
			foreach ( $fields as $field ) {
				$text .= ' ' . ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) ? $row[ $field ] : '' );
			}
			$all = true;
			foreach ( $words as $w ) {
				$found = function_exists( 'mb_stripos' ) ? mb_stripos( $text, $w ) : stripos( $text, $w );
				if ( false === $found ) {
					$all = false;
					break;
				}
			}
			if ( $all ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Whether the business is open at a moment by its own clock, or null when
	 * there are no hours or no time zone.
	 *
	 * @param array  $hours Rows {days, opens, closes}.
	 * @param string $tz    IANA zone.
	 * @param int    $now   Unix time.
	 * @return bool|null
	 */
	public static function open_now( array $hours, $tz, $now ) {
		if ( ! $hours || '' === $tz ) {
			return null;
		}
		try {
			$at = new DateTimeImmutable( '@' . $now );
			$at = $at->setTimezone( new DateTimeZone( $tz ) );
		} catch ( Exception $e ) {
			return null;
		}
		$day  = strtolower( $at->format( 'D' ) );
		$prev = strtolower( $at->modify( '-1 day' )->format( 'D' ) );
		$time = $at->format( 'H:i' );
		foreach ( $hours as $h ) {
			$days   = isset( $h['days'] ) && is_array( $h['days'] ) ? $h['days'] : array();
			$opens  = isset( $h['opens'] ) ? (string) $h['opens'] : '';
			$closes = isset( $h['closes'] ) ? (string) $h['closes'] : '';
			if ( '' === $opens || '' === $closes ) {
				continue;
			}
			if ( $closes > $opens ) {
				if ( in_array( $day, $days, true ) && $time >= $opens && $time < $closes ) {
					return true;
				}
			} elseif ( ( in_array( $day, $days, true ) && $time >= $opens ) || ( in_array( $prev, $days, true ) && $time < $closes ) ) {
				return true;
			}
		}
		return false;
	}
}
