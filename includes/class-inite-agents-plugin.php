<?php
/**
 * Wiring: each feature hooks in only while it is on, the two cron events,
 * activation and deactivation.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap.
 */
final class Inite_Agents_Plugin {

	/** Hook everything. */
	public static function boot() {
		Inite_Agents_Files::register();
		Inite_Agents_Robots::register();
		Inite_Agents_Head::register();
		Inite_Agents_Abilities::register();

		add_action( Inite_Agents_Sync::CRON, array( __CLASS__, 'cron_sync' ) );
		add_action( Inite_Agents_Ping::CRON, array( 'Inite_Agents_Ping', 'run' ) );
		add_action( 'update_option_' . Inite_Agents_Settings::OPTION, array( __CLASS__, 'saved' ), 10, 0 );
		add_action( 'add_option_' . Inite_Agents_Settings::OPTION, array( __CLASS__, 'saved' ), 10, 0 );

		Inite_Agents_Content::register();
		if ( is_admin() ) {
			Inite_Agents_Admin::register();
			Inite_Agents_Requests::register();
		}
		// Hooked everywhere: WordPress checks for updates from cron as well as from the admin.
		if ( class_exists( 'Inite_Agents_Updater' ) ) {
			Inite_Agents_Updater::register();
		}
	}

	/** The hourly sync. */
	public static function cron_sync() {
		$synced = Inite_Agents_Sync::run( false );
		Inite_Agents_Content::run();
		// The site's basics only to a service that took the key: a refused one has nothing to read them for.
		if ( ! empty( $synced['ok'] ) ) {
			Inite_Agents_Basics::run();
		}
	}

	/** Settings saved: sync at once and keep the clock running. */
	public static function saved() {
		Inite_Agents_Settings::flush();
		self::schedule();
		if ( '' !== Inite_Agents_Settings::get()['key'] ) {
			Inite_Agents_Sync::run( true );
			Inite_Agents_Content::run();
		} else {
			Inite_Agents_Store::clear();
		}
	}

	/** The two events, scheduled once. */
	public static function schedule() {
		if ( ! wp_next_scheduled( Inite_Agents_Sync::CRON ) ) {
			wp_schedule_event( time() + 60, 'hourly', Inite_Agents_Sync::CRON );
		}
		if ( ! wp_next_scheduled( Inite_Agents_Ping::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Inite_Agents_Ping::CRON );
		}
	}

	/** Activation: the clock, and a sync if a key is already saved. */
	public static function activate() {
		self::schedule();
		if ( '' !== Inite_Agents_Settings::get()['key'] ) {
			Inite_Agents_Sync::run( true );
		}
	}

	/** Deactivation: the clock stops. No route outlives the plugin, as none was written. */
	public static function deactivate() {
		wp_clear_scheduled_hook( Inite_Agents_Sync::CRON );
		wp_clear_scheduled_hook( Inite_Agents_Ping::CRON );
	}
}
