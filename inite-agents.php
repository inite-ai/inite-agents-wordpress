<?php
/**
 * Plugin Name:       INITE Agents
 * Plugin URI:        https://inite.ai/
 * Description:       Serves the facts your business confirmed in INITE to AI agents (llms.txt and llms-full.txt, the agent card and MCP files, robots.txt signals, head tags, JSON-LD, abilities for the MCP Adapter), shows in WordPress what agents and assistants do with your site, and sends INITE the pages, products and events you switch on.
 * Version:           0.10.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            INITE
 * Author URI:        https://inite.ai/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       inite-agents
 * Domain Path:       /languages
 * Update URI:        https://inite.ai/downloads/inite-agents
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INITE_AGENTS_VERSION', '0.10.0' );
define( 'INITE_AGENTS_FILE', __FILE__ );
define( 'INITE_AGENTS_DIR', plugin_dir_path( __FILE__ ) );

require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-settings.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-store.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-client.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-sync.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-visitors.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-conflicts.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-files.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-robots.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-head.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-schema.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-fixes.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-referrals.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-indexnow.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-survey.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-summary.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-abilities.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-ping.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-plugins.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-basics.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-collect.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-records.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-content.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-admin-status.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-admin-sending.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-admin-signals.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-admin-overview.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-admin-fixes.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-admin.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-requests.php';
require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-plugin.php';
// The copy from inite.ai updates itself from there; the WordPress.org build has no such file.
if ( file_exists( INITE_AGENTS_DIR . 'includes/class-inite-agents-updater.php' ) ) {
	require_once INITE_AGENTS_DIR . 'includes/class-inite-agents-updater.php';
}

register_activation_hook( __FILE__, array( 'Inite_Agents_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Inite_Agents_Plugin', 'deactivate' ) );

Inite_Agents_Plugin::boot();
