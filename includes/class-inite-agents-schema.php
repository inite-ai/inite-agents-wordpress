<?php
/**
 * JSON-LD from the confirmed facts, on the front page: the business
 * (Organization, or LocalBusiness when it has a place or hours), its FAQPage,
 * and a Product for each confirmed service sold per item with a price. Each
 * type is a fix the owner switches (Settings → INITE Agents → Fixes).
 *
 * Never a second copy of a type another plugin prints:
 *   - Yoast SEO and Rank Math: ours goes into their graph. Their business
 *     node keeps every value it has and gains only what it lacks (address,
 *     hours, phone, offers...); a FAQPage or a Product of the same name they
 *     already print is left out; one script on the page, theirs.
 *   - All in One SEO, and SEOPress with its knowledge graph on: the business
 *     node is left out, as before.
 *   - A page with an SEO plugin's FAQ block keeps its own FAQPage.
 * The business and FAQ nodes are the ones INITE built from the confirmed
 * version (the manifest's head.jsonLd); the products are built here from the
 * same version's public facts.
 *
 * Branches (0.9.0): with two places or more the business is the company and
 * each branch a LocalBusiness of its own (parentOrganization the company),
 * with its own hours and special days (specialOpeningHoursSpecification);
 * a food business's menus are Menu nodes. They go with the business fix:
 * into Yoast's or Rank Math's graph beside their business node, which gains
 * `department` and becomes their parent; never a second copy of an @id they
 * print.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * schema.org output.
 */
final class Inite_Agents_Schema {

	/** The FAQ blocks of SEO plugins that print their own FAQPage. */
	const FAQ_BLOCKS = array( 'yoast/faq-block', 'rank-math/faq-block', 'aioseo/faq' );

	/** The types that make a node "the business". */
	const BUSINESS_TYPES = array( 'Organization', 'LocalBusiness', 'Corporation', 'OnlineBusiness', 'OnlineStore', 'Store', 'ProfessionalService', 'MedicalBusiness', 'Dentist', 'Physician', 'Restaurant', 'LegalService', 'HomeAndConstructionBusiness', 'HealthAndBeautyBusiness', 'FoodEstablishment', 'CafeOrCoffeeShop', 'Bakery', 'BarOrPub', 'FastFoodRestaurant', 'IceCreamShop' );

	/**
	 * Set when an SEO plugin's graph took our nodes on this request.
	 *
	 * @var bool
	 */
	private static $merged = false;

	/** Hook in, only while a JSON-LD fix is on. */
	public static function register() {
		if ( ! self::any_on() ) {
			return;
		}
		// All three hooked: which SEO plugin runs is known only once every plugin has loaded, and
		// this one loads before most. A filter of a plugin that is not active never fires, and the
		// standalone script stands down once one of them has taken our nodes (both print in
		// wp_head before priority 6). Their schema switched off, ours prints on its own.
		add_filter( 'wpseo_schema_graph', array( __CLASS__, 'merge_yoast' ), 20, 1 );
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'merge_rank_math' ), 99, 1 );
		add_action( 'wp_head', array( __CLASS__, 'print_standalone' ), 6 );
	}

	/** Whether any of the three JSON-LD fixes is on. */
	public static function any_on() {
		return Inite_Agents_Settings::fix( 'schema_business' ) || Inite_Agents_Settings::fix( 'schema_faq' ) || Inite_Agents_Settings::fix( 'schema_products' );
	}

	/**
	 * Which SEO plugin takes our nodes into its graph, or ''.
	 *
	 * @return string
	 */
	public static function merger() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}
		return '';
	}

	/**
	 * Our nodes, by fix, as switched on, before any other plugin is looked at.
	 *
	 * @param bool $all Every type, whether switched on or not (for the previews).
	 * @return array{business:?array,faq:?array,products:array,branches:array,menus:array}
	 */
	public static function nodes( $all = false ) {
		$copy  = Inite_Agents_Store::get();
		$graph = isset( $copy['manifest']['head']['jsonLd']['@graph'] ) && is_array( $copy['manifest']['head']['jsonLd']['@graph'] ) ? $copy['manifest']['head']['jsonLd']['@graph'] : array();
		$out   = array(
			'business' => null,
			'faq'      => null,
			'products' => array(),
			'branches' => array(),
			'menus'    => array(),
		);
		$business_on = $all || Inite_Agents_Settings::fix( 'schema_business' );
		foreach ( $graph as $node ) {
			if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
				continue;
			}
			if ( self::is_business( $node ) && $business_on ) {
				// A branch names its company; the company is the one node that does not.
				if ( isset( $node['parentOrganization'] ) ) {
					$out['branches'][] = $node;
				} elseif ( null === $out['business'] ) {
					$out['business'] = $node;
				}
			} elseif ( 'Menu' === $node['@type'] && $business_on ) {
				$out['menus'][] = $node;
			} elseif ( 'FAQPage' === $node['@type'] && ( $all || Inite_Agents_Settings::fix( 'schema_faq' ) ) ) {
				$out['faq'] = $node;
			}
		}
		if ( $all || Inite_Agents_Settings::fix( 'schema_products' ) ) {
			$out['products'] = self::products( isset( $copy['facts'] ) && is_array( $copy['facts'] ) ? $copy['facts'] : array(), $graph );
		}
		return $out;
	}

	/**
	 * A Product for each confirmed service sold per item with an amount and a currency.
	 *
	 * @param array $facts The public facts.
	 * @param array $graph The manifest's graph, for the business's @id.
	 * @return array[]
	 */
	public static function products( array $facts, array $graph ) {
		$brand = null;
		foreach ( $graph as $node ) {
			if ( is_array( $node ) && self::is_business( $node ) && ! empty( $node['@id'] ) ) {
				$brand = array( '@id' => (string) $node['@id'] );
			}
		}
		$out = array();
		foreach ( isset( $facts['services'] ) && is_array( $facts['services'] ) ? $facts['services'] : array() as $s ) {
			$price = isset( $s['price'] ) && is_array( $s['price'] ) ? $s['price'] : array();
			if ( empty( $s['name'] ) || ! isset( $price['amount'], $price['currency'], $price['unit'] ) || 'item' !== $price['unit'] || ! is_numeric( $price['amount'] ) ) {
				continue;
			}
			$node = array(
				'@type'  => 'Product',
				'name'   => (string) $s['name'],
				'offers' => array(
					'@type'         => 'Offer',
					'price'         => $price['amount'] + 0,
					'priceCurrency' => (string) $price['currency'],
					'url'           => ! empty( $s['url'] ) ? (string) $s['url'] : home_url( '/' ),
				),
			);
			if ( ! empty( $s['description'] ) ) {
				$node['description'] = (string) $s['description'];
			}
			if ( ! empty( $s['url'] ) ) {
				$node['url'] = (string) $s['url'];
			}
			if ( $brand ) {
				$node['brand'] = $brand;
			}
			$out[] = $node;
		}
		return $out;
	}

	/**
	 * Whether a node is a business: its type, or one of its types, is a business type.
	 *
	 * @param array $node A node.
	 * @return bool
	 */
	public static function is_business( array $node ) {
		$types = isset( $node['@type'] ) ? (array) $node['@type'] : array();
		return (bool) array_intersect( array_map( 'strval', $types ), self::BUSINESS_TYPES );
	}

	/**
	 * Our nodes merged into another plugin's: theirs keeps every value it
	 * has; a type they already print is not printed twice.
	 *
	 * @param array $graph Their nodes (a list).
	 * @param array $ours  Our nodes (from nodes()).
	 * @return array{graph:array,added:string[],filled:string[]} The graph, what was added whole, and which properties their business node gained.
	 */
	public static function merge( array $graph, array $ours ) {
		$added  = array();
		$filled = array();
		$our_id = isset( $ours['business']['@id'] ) ? (string) $ours['business']['@id'] : '';
		$their  = '';
		$found  = false;
		if ( $ours['business'] ) {
			foreach ( $graph as $i => $node ) {
				if ( is_array( $node ) && self::is_business( $node ) ) {
					foreach ( $ours['business'] as $prop => $value ) {
						if ( in_array( $prop, array( '@id', '@type', '@context' ), true ) || isset( $node[ $prop ] ) ) {
							continue;
						}
						$graph[ $i ][ $prop ] = $value;
						$filled[]             = $prop;
					}
					// A LocalBusiness's hours and map are not an Organization's: their node takes our type beside its own.
					$types = array_values( array_unique( array_merge( (array) $node['@type'], (array) $ours['business']['@type'] ) ) );
					if ( count( $types ) > count( (array) $node['@type'] ) ) {
						$graph[ $i ]['@type'] = $types;
						$filled[]             = '@type';
					}
					$their = isset( $node['@id'] ) ? (string) $node['@id'] : '';
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$graph[] = $ours['business'];
				$added[] = (string) ( is_array( $ours['business']['@type'] ) ? reset( $ours['business']['@type'] ) : $ours['business']['@type'] );
			}
		}
		$has = static function ( $type, $name = null ) use ( &$graph ) {
			foreach ( $graph as $node ) {
				if ( is_array( $node ) && in_array( $type, (array) ( isset( $node['@type'] ) ? $node['@type'] : array() ), true ) && ( null === $name || ( isset( $node['name'] ) && strtolower( (string) $node['name'] ) === strtolower( $name ) ) ) ) {
					return true;
				}
			}
			return false;
		};
		$remap = static function ( $node ) use ( $our_id, $their ) {
			if ( '' === $their || '' === $our_id ) {
				return $node;
			}
			foreach ( array( 'about', 'brand', 'parentOrganization' ) as $ref ) {
				if ( isset( $node[ $ref ]['@id'] ) && $our_id === $node[ $ref ]['@id'] ) {
					$node[ $ref ]['@id'] = $their;
				}
			}
			return $node;
		};
		$ids = array();
		foreach ( $graph as $node ) {
			if ( is_array( $node ) && ! empty( $node['@id'] ) ) {
				$ids[ (string) $node['@id'] ] = true;
			}
		}
		foreach ( array_merge( $ours['branches'], $ours['menus'] ) as $node ) {
			if ( empty( $node['@id'] ) || isset( $ids[ (string) $node['@id'] ] ) ) {
				continue;
			}
			$graph[] = $remap( $node );
			$added[] = 'Menu' === $node['@type'] ? 'Menu' : 'Branch';
		}
		if ( $ours['faq'] && ! $has( 'FAQPage' ) && '' === self::faq_block() ) {
			$graph[] = $remap( $ours['faq'] );
			$added[] = 'FAQPage';
		}
		foreach ( $ours['products'] as $p ) {
			if ( ! $has( 'Product', (string) $p['name'] ) ) {
				$graph[] = $remap( $p );
				$added[] = 'Product';
			}
		}
		return array(
			'graph'  => array_values( $graph ),
			'added'  => $added,
			'filled' => $filled,
		);
	}

	/**
	 * Yoast SEO's graph, with ours in it, on the front page.
	 *
	 * @param array $graph Yoast's graph pieces.
	 * @return array
	 */
	public static function merge_yoast( $graph ) {
		if ( ! is_array( $graph ) || ! self::here() ) {
			return $graph;
		}
		self::$merged = true;
		return self::merge( $graph, self::nodes() )['graph'];
	}

	/**
	 * Rank Math's entities, with ours in them, on the front page. Its keys are kept; ours are added under inite-*.
	 *
	 * @param array $data Rank Math's entities, keyed.
	 * @return array
	 */
	public static function merge_rank_math( $data ) {
		if ( ! is_array( $data ) || ! self::here() ) {
			return $data;
		}
		self::$merged = true;
		$keys         = array_keys( $data );
		$merged = self::merge( array_values( $data ), self::nodes() )['graph'];
		$out    = array();
		foreach ( $merged as $i => $node ) {
			$out[ isset( $keys[ $i ] ) ? $keys[ $i ] : 'inite-' . $i ] = $node;
		}
		return $out;
	}

	/**
	 * Whether this page carries the JSON-LD: the front page, or every page through the filter.
	 *
	 * @return bool
	 */
	public static function here() {
		return is_front_page() || (bool) apply_filters( 'inite_agents_jsonld_everywhere', false );
	}

	/**
	 * Our own graph, when no SEO plugin takes it: what the other plugins print left out.
	 *
	 * @return array|null
	 */
	public static function standalone() {
		$ours = self::nodes();
		$keep = array();
		$owner = Inite_Agents_Conflicts::organization_schema();
		// Yoast SEO or Rank Math that took nothing this request print no graph: their name is no reason to leave ours out.
		if ( in_array( $owner, array( 'Yoast SEO', 'Rank Math' ), true ) ) {
			$owner = '';
		}
		if ( $ours['business'] && '' === $owner ) {
			$keep[] = $ours['business'];
			// The branches and menus hang from our business node: without it they would name a company nobody prints.
			foreach ( array_merge( $ours['branches'], $ours['menus'] ) as $node ) {
				$keep[] = $node;
			}
		}
		if ( $ours['faq'] && '' === self::faq_block() ) {
			$keep[] = $ours['faq'];
		}
		foreach ( $ours['products'] as $p ) {
			$keep[] = $p;
		}
		return $keep ? array(
			'@context' => 'https://schema.org',
			'@graph'   => $keep,
		) : null;
	}

	/** Print our graph on the front page, when no SEO plugin takes it. */
	public static function print_standalone() {
		if ( ! self::here() || self::$merged ) {
			return;
		}
		$json = self::standalone();
		if ( null === $json ) {
			return;
		}
		$encoded = wp_json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $encoded, array( 'type' => 'application/ld+json' ) );
		} else {
			// JSON_HEX_TAG keeps "<" and ">" out of the body, so it cannot close the element.
			echo '<script type="application/ld+json">' . $encoded . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * The SEO plugin FAQ block on this page, or ''.
	 *
	 * @return string
	 */
	public static function faq_block() {
		if ( is_singular() ) {
			foreach ( self::FAQ_BLOCKS as $block ) {
				if ( has_block( $block ) ) {
					return $block;
				}
			}
		}
		return '';
	}
}
