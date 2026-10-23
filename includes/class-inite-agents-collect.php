<?php
/**
 * What the site's own published content says, read through WordPress's own
 * APIs for the groups the owner switched on (Inite_Agents_Records sends it):
 *
 *   content   published pages and posts without a password, and not marked
 *             noindex in Yoast SEO or Rank Math: title, address, excerpt (or
 *             the first 300 characters of the text), when it changed, its
 *             type and language. The shop's cart, checkout and account
 *             pages are left out.
 *   products  WooCommerce products a visitor can see in the catalogue: name,
 *             address, price, regular and sale price, currency, stock
 *             status, SKU, short description, categories, image address.
 *   events    The Events Calendar's events that have not ended: title,
 *             address, excerpt, start, end, venue.
 *
 * Nothing about a person: no author, no comment, no customer, no order.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collectors.
 */
final class Inite_Agents_Collect {

	/** Characters of text kept as a summary. */
	const SUMMARY = 300;

	/** The groups, each with the switch that sends it. */
	const GROUPS = array(
		'content'  => 'send_content',
		'products' => 'send_products',
		'events'   => 'send_events',
	);

	/**
	 * Whether a group can be read on this site at all.
	 *
	 * @param string $group Group.
	 * @return bool
	 */
	public static function available( $group ) {
		if ( 'products' === $group ) {
			return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
		}
		if ( 'events' === $group ) {
			return post_type_exists( 'tribe_events' );
		}
		return true;
	}

	/**
	 * The post types a group reads.
	 *
	 * @param string $group Group.
	 * @return string[]
	 */
	public static function post_types( $group ) {
		if ( 'products' === $group ) {
			return array( 'product' );
		}
		if ( 'events' === $group ) {
			return array( 'tribe_events' );
		}
		return array( 'page', 'post' );
	}

	/**
	 * The group a post type belongs to, or ''.
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	public static function group_of( $type ) {
		foreach ( array_keys( self::GROUPS ) as $group ) {
			if ( in_array( $type, self::post_types( $group ), true ) ) {
				return $group;
			}
		}
		return '';
	}

	/**
	 * The query of a group's published posts, changed at or after a moment.
	 *
	 * @param string $group  Group.
	 * @param string $after  post_modified ('Y-m-d H:i:s'), or '' for all.
	 * @param int    $limit  Posts at most.
	 * @param bool   $ids    Only the ids.
	 * @return array WP_Query arguments.
	 */
	private static function query_args( $group, $after, $limit, $ids = false ) {
		$args = array(
			'post_type'           => self::post_types( $group ),
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => $limit,
			'orderby'             => array(
				'modified' => 'ASC',
				'ID'       => 'ASC',
			),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'fields'              => $ids ? 'ids' : 'all',
		);
		if ( '' !== $after ) {
			$args['date_query'] = array(
				array(
					'column'    => 'post_modified',
					'after'     => $after,
					'inclusive' => true,
				),
			);
		}
		if ( 'events' === $group ) {
			// Events that have not ended; a past one is not news to an agent.
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed meta key, at most a page of events an hour.
				array(
					'key'     => '_EventEndDateUTC',
					'value'   => gmdate( 'Y-m-d H:i:s' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			);
		}
		return $args;
	}

	/**
	 * A page of a group's items changed at or after the cursor, oldest first.
	 *
	 * @param string $group  Group.
	 * @param array  $cursor { modified: string, id: int } of the last item sent.
	 * @param int    $limit  Items at most.
	 * @return array{items:array,last:array|null,full:bool} `full`: the page was full, so more may follow.
	 */
	public static function page( $group, array $cursor, $limit ) {
		$after = isset( $cursor['modified'] ) ? (string) $cursor['modified'] : '';
		$after_id = isset( $cursor['id'] ) ? (int) $cursor['id'] : 0;
		$query = new WP_Query( self::query_args( $group, $after, $limit ) );
		$items = array();
		$last  = null;
		foreach ( $query->posts as $post ) {
			$last = array(
				'modified' => $post->post_modified,
				'id'       => (int) $post->ID,
			);
			// The cursor's own second again: what was sent then is not sent twice.
			if ( $post->post_modified === $after && (int) $post->ID <= $after_id ) {
				continue;
			}
			$item = self::item( $group, $post );
			if ( $item ) {
				$items[] = $item;
			}
		}
		return array(
			'items' => $items,
			'last'  => $last,
			'full'  => count( $query->posts ) >= $limit,
		);
	}

	/**
	 * Every id of a group still published, or null when there are more than `$max`.
	 *
	 * @param string $group Group.
	 * @param int    $max   Ids at most.
	 * @return string[]|null
	 */
	public static function present( $group, $max ) {
		$query = new WP_Query( self::query_args( $group, '', $max + 1, true ) );
		if ( count( $query->posts ) > $max ) {
			return null;
		}
		$out = array();
		foreach ( $query->posts as $id ) {
			if ( self::included( $group, (int) $id ) ) {
				$out[] = (string) $id;
			}
		}
		return $out;
	}

	/**
	 * Whether a published post of a group is one agents may be told about.
	 *
	 * @param string $group Group.
	 * @param int    $id    Post id.
	 * @return bool
	 */
	private static function included( $group, $id ) {
		if ( 'content' === $group ) {
			// Marked noindex by the owner in an SEO plugin: not for agents either.
			if ( '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
				return false;
			}
			$robots = get_post_meta( $id, 'rank_math_robots', true );
			if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
				return false;
			}
			if ( function_exists( 'wc_get_page_id' ) ) {
				foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
					if ( (int) wc_get_page_id( $page ) === $id ) {
						return false;
					}
				}
			}
		}
		if ( 'products' === $group && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $id );
			return $product && 'hidden' !== $product->get_catalog_visibility();
		}
		return true;
	}

	/**
	 * One post as the service reads it, or null when it is left out.
	 *
	 * @param string  $group Group.
	 * @param WP_Post $post  Post.
	 * @return array|null
	 */
	private static function item( $group, $post ) {
		if ( ! self::included( $group, (int) $post->ID ) ) {
			return null;
		}
		$base = array(
			'id'       => (string) $post->ID,
			'url'      => (string) get_permalink( $post ),
			'title'    => wp_strip_all_tags( get_the_title( $post ) ),
			'excerpt'  => self::summary( $post->post_excerpt, $post->post_content ),
			'modified' => (string) get_post_modified_time( 'c', true, $post ),
			'language' => self::language( (int) $post->ID ),
		);
		if ( 'content' === $group ) {
			return array( 'type' => $post->post_type ) + $base;
		}
		if ( 'events' === $group ) {
			return self::event( $post, $base );
		}
		return self::product( (int) $post->ID, $base );
	}

	/**
	 * The excerpt, or the start of the text: no markup, no shortcodes, one line.
	 *
	 * @param string $excerpt The post's excerpt.
	 * @param string $content The post's content.
	 * @return string
	 */
	public static function summary( $excerpt, $content ) {
		$text = '' !== trim( (string) $excerpt ) ? (string) $excerpt : (string) $content;
		$text = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $text ) ), true );
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > self::SUMMARY ) {
			$text = rtrim( mb_substr( $text, 0, self::SUMMARY - 1 ) ) . '…';
		}
		return $text;
	}

	/**
	 * The post's language: Polylang's or WPML's when one runs, else the site's.
	 *
	 * @param int $id Post id.
	 * @return string
	 */
	private static function language( $id ) {
		if ( function_exists( 'pll_get_post_language' ) ) {
			$l = pll_get_post_language( $id, 'locale' );
			if ( is_string( $l ) && '' !== $l ) {
				return str_replace( '_', '-', $l );
			}
		}
		$wpml = apply_filters( 'wpml_post_language_details', null, $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter, read only.
		if ( is_array( $wpml ) && ! empty( $wpml['locale'] ) ) {
			return str_replace( '_', '-', (string) $wpml['locale'] );
		}
		return (string) get_bloginfo( 'language' );
	}

	/**
	 * A WooCommerce product's public fields.
	 *
	 * @param int   $id   Product id.
	 * @param array $base The fields every item has.
	 * @return array|null
	 */
	private static function product( $id, array $base ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return null;
		}
		$short = $product->get_short_description();
		$image = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_single' ) : '';
		$cats  = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
		return array(
			'name'         => wp_strip_all_tags( $product->get_name() ),
			'excerpt'      => self::summary( $short, $product->get_description() ),
			'price'        => (string) $product->get_price(),
			'regularPrice' => (string) $product->get_regular_price(),
			'salePrice'    => $product->is_on_sale() ? (string) $product->get_sale_price() : '',
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'stock'        => (string) $product->get_stock_status(),
			'sku'          => (string) $product->get_sku(),
			'categories'   => is_array( $cats ) ? array_slice( array_map( 'wp_strip_all_tags', $cats ), 0, 5 ) : array(),
			'image'        => $image ? (string) $image : '',
		) + $base;
	}

	/**
	 * An event's start, end and venue (The Events Calendar keeps them in UTC meta).
	 *
	 * @param WP_Post $post The event.
	 * @param array   $base The fields every item has.
	 * @return array|null
	 */
	private static function event( $post, array $base ) {
		$start = (string) get_post_meta( $post->ID, '_EventStartDateUTC', true );
		$end   = (string) get_post_meta( $post->ID, '_EventEndDateUTC', true );
		if ( '' === $start ) {
			return null;
		}
		$venue = '';
		if ( function_exists( 'tribe_get_venue' ) ) {
			$venue = (string) tribe_get_venue( $post->ID );
		} else {
			$venue_id = (int) get_post_meta( $post->ID, '_EventVenueID', true );
			$venue    = $venue_id ? get_the_title( $venue_id ) : '';
		}
		return array(
			'starts' => str_replace( ' ', 'T', $start ) . 'Z',
			'ends'   => '' !== $end ? str_replace( ' ', 'T', $end ) . 'Z' : '',
			'venue'  => wp_strip_all_tags( $venue ),
		) + $base;
	}
}
