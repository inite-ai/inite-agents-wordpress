<?php
/**
 * Uninstall: the options, the update check's transient and the cron events go. The plugin wrote no
 * rewrite rules, files or tables, so nothing else is left behind.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'inite_agents_settings', 'inite_agents_store', 'inite_agents_status', 'inite_agents_visits' ) as $inite_agents_option ) {
	delete_option( $inite_agents_option );
}
delete_transient( 'inite_agents_update' );
wp_clear_scheduled_hook( 'inite_agents_sync' );
wp_clear_scheduled_hook( 'inite_agents_ping' );
