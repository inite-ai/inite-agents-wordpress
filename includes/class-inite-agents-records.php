<?php
/**
 * Sends the site's own published content to INITE, for the groups the owner
 * switched on (Inite_Agents_Collect reads it): POST /api/site/v1/records.
 *
 * Incremental. Per group the plugin keeps a cursor, the last item sent (its
 * modified time and id), and sends what changed since, a page of PAGE items
 * at a time, at most PAGES pages a run, so a large shop arrives over a few
 * hours and a quiet one costs one query an hour. What went (trashed,
 * unpublished, deleted) is queued by a hook and sent as `removed`; once a day
 * the ids still published are sent as `present`, which removes anything
 * missed. Switching a group off sends `clear`: INITE deletes all of it.
 *
 * Runs from cron only, never while a visitor's page renders; each call waits
 * fifteen seconds at most. Nothing is sent before a key is saved and the
 * group's switch is on.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records sender.
 */
final class Inite_Agents_Records {

	const CRON = 'inite_agents_records';

	/** Per group: the cursor, the last answer, when `present` last went. Never autoloaded. */
	const OPTION = 'inite_agents_records';

	/** Ids that went, per group, waiting to be sent. */
	const REMOVED = 'inite_agents_records_removed';

	/** Items an upload carries, and uploads a group makes a run. */
	const PAGE  = 100;
	const PAGES = 5;

	/** Ids `present` may carry; a site with more relies on `removed` alone. */
	const PRESENT_MAX = 5000;

	/** Ids queued as removed per group at most. */
	const REMOVED_MAX = 500;

	const TIMEOUT = 15;

	/** Hook the removals, the clear on a switch turned off, and the clock. */
	public static function register() {
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		add_action( self::CRON . '_now', array( __CLASS__, 'run' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'deleted' ), 10, 1 );
		add_action( 'update_option_' . Inite_Agents_Settings::OPTION, array( __CLASS__, 'switched' ), 20, 2 );
	}

	/**
	 * The state of every group.
	 *
	 * @return array
	 */
	public static function state() {
		$v = get_option( self::OPTION, array() );
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Write the state.
	 *
	 * @param array $state State.
	 */
	private static function put( array $state ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $state, '', false );
		} else {
			update_option( self::OPTION, $state, false );
		}
	}

	/**
	 * A key's fingerprint: a new key is a new record at INITE, so everything is sent again.
	 *
	 * @param string $key Site key.
	 * @return string
	 */
	private static function key_print( $key ) {
		return substr( md5( (string) $key ), 0, 12 );
	}

	/**
	 * One run over the groups that are on.
	 *
	 * @return array Per group: what was sent and the last answer.
	 */
	public static function run() {
		$key = Inite_Agents_Settings::get()['key'];
		if ( '' === $key ) {
			return array();
		}
		$state = self::state();
		if ( ! isset( $state['key'] ) || self::key_print( $key ) !== $state['key'] ) {
			$state = array( 'key' => self::key_print( $key ) );
		}
		$out = array();
		foreach ( Inite_Agents_Collect::GROUPS as $group => $switch ) {
			$g = isset( $state[ $group ] ) && is_array( $state[ $group ] ) ? $state[ $group ] : array();
			if ( ! empty( $g['clear'] ) ) {
				$res = self::send( $key, array( 'clear' => true ), $group );
				if ( 200 === $res['status'] ) {
					$g = array( 'answer' => self::answer( $res ) );
				}
				$state[ $group ] = $g;
				$out[ $group ]   = array( 'cleared' => 200 === $res['status'] );
				continue;
			}
			if ( ! Inite_Agents_Settings::on( $switch ) || ! Inite_Agents_Collect::available( $group ) ) {
				continue;
			}
			$sent            = self::run_group( $key, $group, $g );
			$state[ $group ] = $g;
			$out[ $group ]   = $sent;
			if ( ! empty( $sent['refused'] ) ) {
				break;
			}
		}
		self::put( $state );
		return $out;
	}

	/**
	 * One group's run: the removals, the changed pages, and once a day what is still there.
	 *
	 * @param string $key   Site key.
	 * @param string $group Group.
	 * @param array  $g     The group's state, updated in place.
	 * @return array
	 */
	private static function run_group( $key, $group, array &$g ) {
		$sent    = 0;
		$removed = self::take_removed( $group );
		if ( $removed ) {
			$res = self::send( $key, array( 'removed' => $removed ), $group );
			if ( 200 !== $res['status'] ) {
				self::queue_removed( $group, $removed );
				return self::failed( $g, $res );
			}
		}
		$cursor = isset( $g['cursor'] ) && is_array( $g['cursor'] ) ? $g['cursor'] : array();
		for ( $i = 0; $i < self::PAGES; $i++ ) {
			$page = Inite_Agents_Collect::page( $group, $cursor, self::PAGE );
			if ( $page['items'] ) {
				$res = self::send( $key, array( 'items' => $page['items'] ), $group );
				if ( 200 !== $res['status'] ) {
					return self::failed( $g, $res );
				}
				$g['answer'] = self::answer( $res );
				$sent       += count( $page['items'] );
			}
			if ( $page['last'] ) {
				$next = $page['last'];
				// A full page that all shared the cursor's second: step past that second, or the next run reads it again.
				if ( $page['full'] && ! $page['items'] && isset( $cursor['modified'] ) && $next['modified'] === $cursor['modified'] ) {
					$next = array(
						'modified' => gmdate( 'Y-m-d H:i:s', strtotime( $next['modified'] . ' UTC' ) + 1 ),
						'id'       => 0,
					);
				}
				$cursor = $next;
			}
			if ( ! $page['full'] ) {
				break;
			}
		}
		$g['cursor'] = $cursor;
		if ( empty( $g['present_at'] ) || time() - (int) $g['present_at'] > DAY_IN_SECONDS ) {
			$present = Inite_Agents_Collect::present( $group, self::PRESENT_MAX );
			if ( null !== $present ) {
				$res = self::send( $key, array( 'present' => $present ), $group );
				if ( 200 === $res['status'] ) {
					$g['present_at'] = time();
					$g['answer']     = self::answer( $res );
				}
			}
		}
		$g['at']    = time();
		$g['error'] = '';
		return array( 'sent' => $sent );
	}

	/**
	 * Note a failed call on the group, and whether the service refused the key.
	 *
	 * @param array $g   The group's state.
	 * @param array $res The client's answer.
	 * @return array
	 */
	private static function failed( array &$g, array $res ) {
		$g['error'] = $res['status'] ? (string) $res['status'] : $res['error'];
		return array(
			'sent'    => 0,
			'refused' => in_array( $res['status'], array( 401, 403, 409 ), true ),
		);
	}

	/**
	 * What the service answered, reduced to the numbers the settings screen shows.
	 *
	 * @param array $res The client's answer.
	 * @return array
	 */
	private static function answer( array $res ) {
		$a = json_decode( $res['body'], true );
		if ( ! is_array( $a ) ) {
			return array();
		}
		$out = array( 'at' => time() );
		foreach ( array( 'held', 'cap', 'capped' ) as $k ) {
			if ( isset( $a[ $k ] ) && is_int( $a[ $k ] ) ) {
				$out[ $k ] = $a[ $k ];
			}
		}
		return $out;
	}

	/**
	 * One upload.
	 *
	 * @param string $key   Site key.
	 * @param array  $body  Body without the group.
	 * @param string $group Group.
	 * @return array The client's answer.
	 */
	private static function send( $key, array $body, $group ) {
		return Inite_Agents_Client::post( '/api/site/v1/records', $key, array( 'group' => $group ) + $body, self::TIMEOUT );
	}

	/**
	 * A post that leaves "published" (to a draft, the trash, private) is queued as removed.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function transition( $new_status, $old_status, $post ) {
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			self::gone( $post );
		}
	}

	/**
	 * A post deleted for good is queued as removed.
	 *
	 * @param int $post_id Post id.
	 */
	public static function deleted( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'publish' === $post->post_status ) {
			self::gone( $post );
		}
	}

	/**
	 * Queue a post that went, if its group is being sent.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function gone( $post ) {
		$group = Inite_Agents_Collect::group_of( $post->post_type );
		if ( '' !== $group && Inite_Agents_Settings::on( Inite_Agents_Collect::GROUPS[ $group ] ) ) {
			self::queue_removed( $group, array( (string) $post->ID ) );
		}
	}

	/**
	 * Add ids to a group's removals.
	 *
	 * @param string   $group Group.
	 * @param string[] $ids   Ids.
	 */
	private static function queue_removed( $group, array $ids ) {
		$all = get_option( self::REMOVED, array() );
		$all = is_array( $all ) ? $all : array();
		$had = isset( $all[ $group ] ) && is_array( $all[ $group ] ) ? $all[ $group ] : array();
		// Past the cap the oldest go; the daily `present` removes what they named anyway.
		$all[ $group ] = array_slice( array_values( array_unique( array_merge( $had, $ids ) ) ), -self::REMOVED_MAX );
		if ( false === get_option( self::REMOVED, false ) ) {
			add_option( self::REMOVED, $all, '', false );
		} else {
			update_option( self::REMOVED, $all, false );
		}
	}

	/**
	 * Take a group's removals off the queue.
	 *
	 * @param string $group Group.
	 * @return string[]
	 */
	private static function take_removed( $group ) {
		$all = get_option( self::REMOVED, array() );
		if ( ! is_array( $all ) || empty( $all[ $group ] ) ) {
			return array();
		}
		$ids = array_values( (array) $all[ $group ] );
		unset( $all[ $group ] );
		update_option( self::REMOVED, $all, false );
		return $ids;
	}

	/**
	 * The settings were saved: a group switched off is cleared at INITE on the next run,
	 * and the next run comes in a minute rather than an hour.
	 *
	 * @param mixed $old The settings before.
	 * @param mixed $new The settings now.
	 */
	public static function switched( $old, $new ) {
		$state = self::state();
		foreach ( Inite_Agents_Collect::GROUPS as $group => $switch ) {
			$was = is_array( $old ) && ! empty( $old['features'][ $switch ] );
			$is  = is_array( $new ) && ! empty( $new['features'][ $switch ] );
			if ( $was && ! $is ) {
				$state[ $group ] = array( 'clear' => true );
			} elseif ( ! $was && $is ) {
				// Switched on again: everything is sent again, from the start.
				$state[ $group ] = array();
			}
		}
		self::put( $state );
		if ( ! wp_next_scheduled( self::CRON . '_now' ) ) {
			wp_schedule_single_event( time() + 30, self::CRON . '_now' );
		}
	}
}
