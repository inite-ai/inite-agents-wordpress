<?php
/**
 * Articles the owner approved in the INITE cabinet, written into WordPress.
 *
 * Off by default. On each sync it asks for the site's queue
 * (GET /api/site/v1/content/queue) and makes one post per article. A post is
 * found again by the article id in its meta, so a second sync never makes a
 * second post. The post is a draft for the owner to publish in WordPress, or
 * published at once, as the owner chose. The address goes back to INITE when
 * the post is published: at once, or when the owner presses Publish.
 *
 * @package InitE_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pulls the queue, writes posts, reports the addresses.
 */
class Inite_Agents_Content {

	/** Post meta that holds the INITE article id. */
	const META_ID = '_inite_article_id';

	/** Post meta set once the address was reported. */
	const META_REPORTED = '_inite_article_reported';

	/** Hook the report of a draft the owner publishes later. */
	public static function register() {
		add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 10, 3 );
	}

	/**
	 * One pass over the queue.
	 *
	 * @return array{made:int,reported:int,failed:int}
	 */
	public static function run() {
		$out = array(
			'made'     => 0,
			'reported' => 0,
			'failed'   => 0,
		);
		if ( ! Inite_Agents_Settings::on( 'content' ) ) {
			return $out;
		}
		$key = Inite_Agents_Settings::get()['key'];
		$res = Inite_Agents_Client::get( '/api/site/v1/content/queue', $key );
		if ( 200 !== $res['status'] ) {
			return $out;
		}
		$body = json_decode( $res['body'], true );
		if ( ! is_array( $body ) || empty( $body['items'] ) || ! is_array( $body['items'] ) ) {
			return $out;
		}
		foreach ( $body['items'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || ! is_string( $item['id'] ) ) {
				continue;
			}
			$id      = sanitize_text_field( $item['id'] );
			$post_id = self::post_for( $id );
			if ( ! $post_id ) {
				$post_id = self::make( $id, $item );
				if ( is_wp_error( $post_id ) ) {
					Inite_Agents_Client::post( '/api/site/v1/content/' . rawurlencode( $id ) . '/failed', $key, array( 'reason' => $post_id->get_error_message() ) );
					++$out['failed'];
					continue;
				}
				++$out['made'];
			}
			if ( 'publish' === get_post_status( $post_id ) && self::report( $post_id ) ) {
				++$out['reported'];
			}
		}
		return $out;
	}

	/**
	 * The post already made for an article, or 0.
	 *
	 * @param string $id INITE article id.
	 * @return int
	 */
	private static function post_for( $id ) {
		$found = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'any',
				'meta_key'         => self::META_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one indexed lookup per queued article, a handful a month.
				'meta_value'       => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
				'fields'           => 'ids',
				'numberposts'      => 1,
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * A new post from a queue item.
	 *
	 * @param string $id   INITE article id.
	 * @param array  $item The queue item.
	 * @return int|WP_Error
	 */
	private static function make( $id, array $item ) {
		$status  = 'publish' === Inite_Agents_Settings::get()['content_status'] ? 'publish' : 'draft';
		$excerpt = isset( $item['metaDescription'] ) ? sanitize_text_field( $item['metaDescription'] ) : '';
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => $status,
				'post_title'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
				'post_name'    => isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '',
				'post_excerpt' => $excerpt,
				'post_content' => isset( $item['html'] ) ? wp_kses_post( $item['html'] ) : '',
				'meta_input'   => array( self::META_ID => $id ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		// The search snippet, where an SEO plugin keeps its own.
		if ( '' !== $excerpt && defined( 'WPSEO_VERSION' ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $excerpt );
		}
		if ( '' !== $excerpt && class_exists( 'RankMath' ) ) {
			update_post_meta( $post_id, 'rank_math_description', $excerpt );
		}
		return (int) $post_id;
	}

	/**
	 * Tell INITE the address of a published post, once.
	 *
	 * @param int $post_id Post id.
	 * @return bool Whether INITE took it.
	 */
	private static function report( $post_id ) {
		if ( get_post_meta( $post_id, self::META_REPORTED, true ) ) {
			return false;
		}
		$id = (string) get_post_meta( $post_id, self::META_ID, true );
		if ( '' === $id ) {
			return false;
		}
		$key = Inite_Agents_Settings::get()['key'];
		$res = Inite_Agents_Client::post( '/api/site/v1/content/' . rawurlencode( $id ) . '/published', $key, array( 'url' => get_permalink( $post_id ) ) );
		if ( 200 !== $res['status'] ) {
			return false;
		}
		update_post_meta( $post_id, self::META_REPORTED, time() );
		return true;
	}

	/**
	 * A draft the owner publishes later is reported then.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       The post.
	 */
	public static function transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! Inite_Agents_Settings::on( 'content' ) ) {
			return;
		}
		if ( get_post_meta( $post->ID, self::META_ID, true ) ) {
			self::report( $post->ID );
		}
	}
}
