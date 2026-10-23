<?php
/**
 * Settings → INITE Agents, in three tabs: Overview (what INITE knows about
 * the site, Inite_Agents_Admin_Overview), Fixes (Inite_Agents_Admin_Fixes),
 * and Settings: the site key, the features, and what the site serves right
 * now, with whatever stands in the way of a feature.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen.
 */
final class Inite_Agents_Admin {

	const PAGE = 'inite-agents';

	/** Hook the screen. */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'admin_post_inite_agents_test', array( __CLASS__, 'test' ) );
		Inite_Agents_Admin_Overview::register();
		add_filter( 'plugin_action_links_' . plugin_basename( INITE_AGENTS_FILE ), array( __CLASS__, 'action_link' ) );
	}

	/** The menu entry. */
	public static function menu() {
		add_options_page( __( 'INITE Agents', 'inite-agents' ), __( 'INITE Agents', 'inite-agents' ), 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * A Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'inite-agents' ) . '</a>' );
		return $links;
	}

	/** The setting, with its sanitizer. */
	public static function settings() {
		register_setting(
			'inite_agents',
			Inite_Agents_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Inite_Agents_Settings', 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/** "Test connection": a full sync now, its outcome shown once. */
	public static function test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'inite-agents' ), 403 );
		}
		check_admin_referer( 'inite_agents_test' );
		$result = Inite_Agents_Sync::run( true );
		Inite_Agents_Plugin::schedule();
		set_transient( 'inite_agents_test_' . get_current_user_id(), $result, 120 );
		wp_safe_redirect( self::url( 'settings' ) . '&tested=1' );
		exit;
	}

	/**
	 * The cabinet's "For agents" tab for this site, in the admin's language when INITE speaks it.
	 *
	 * @return string
	 */
	private static function cabinet_url() {
		$lang = substr( get_user_locale(), 0, 2 );
		$lang = in_array( $lang, array( 'en', 'ru', 'es', 'pt' ), true ) ? $lang : 'en';
		$copy = Inite_Agents_Store::get();
		$slug = isset( $copy['manifest']['site']['slug'] ) ? $copy['manifest']['site']['slug'] : '';
		$base = Inite_Agents_Settings::api_base();
		return '' !== $slug ? $base . '/' . $lang . '/' . rawurlencode( $slug ) . '/profile?tab=agents' : $base . '/' . $lang . '/login';
	}

	/**
	 * A tab's address.
	 *
	 * @param string $tab overview, fixes or settings.
	 * @return string
	 */
	public static function url( $tab = 'overview' ) {
		return admin_url( 'options-general.php?page=' . self::PAGE . '&tab=' . rawurlencode( $tab ) );
	}

	/**
	 * The tab asked for: Overview once a key is saved, Settings before.
	 *
	 * @return string
	 */
	private static function tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which tab to show.
		if ( in_array( $tab, array( 'overview', 'fixes', 'settings' ), true ) ) {
			return $tab;
		}
		return '' === Inite_Agents_Settings::get()['key'] ? 'settings' : 'overview';
	}

	/** The screen: the tabs, then the one asked for. */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = self::tab();
		$tabs = array(
			'overview' => __( 'Overview', 'inite-agents' ),
			'fixes'    => __( 'Fixes', 'inite-agents' ),
			'settings' => __( 'Settings', 'inite-agents' ),
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'INITE Agents', 'inite-agents' ) . '</h1>';
		settings_errors( Inite_Agents_Settings::OPTION );
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'INITE Agents sections', 'inite-agents' ) . '">';
		foreach ( $tabs as $key => $label ) {
			echo '<a href="' . esc_url( self::url( $key ) ) . '" class="nav-tab' . ( $key === $tab ? ' nav-tab-active" aria-current="page' : '' ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( 'overview' === $tab ) {
			Inite_Agents_Admin_Overview::render();
		} elseif ( 'fixes' === $tab ) {
			Inite_Agents_Admin_Fixes::render();
		} else {
			self::render_settings();
		}
		echo '</div>';
	}

	/** The Settings tab. */
	private static function render_settings() {
		$s      = Inite_Agents_Settings::get();
		$copy   = Inite_Agents_Store::get();
		$status = Inite_Agents_Store::status();
		$name   = Inite_Agents_Settings::OPTION;
		$tested = isset( $_GET['tested'] ) ? get_transient( 'inite_agents_test_' . get_current_user_id() ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which notice to show.
		if ( is_array( $tested ) ) {
			$class = $tested['ok'] ? 'notice-success' : 'notice-error';
			$text  = $tested['ok'] ? __( 'Connected. The files below are the copy now stored.', 'inite-agents' ) : $tested['error'];
			echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Serves the facts your business confirmed in INITE to AI agents, from a copy stored on this site. Make the site key in the "For agents" tab of your INITE cabinet.', 'inite-agents' ) . ' <a href="' . esc_url( self::cabinet_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the INITE cabinet', 'inite-agents' ) . '</a></p>';

		echo '<form method="post" action="options.php">';
		settings_fields( 'inite_agents' );
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="inite-agents-key">' . esc_html__( 'Site key', 'inite-agents' ) . '</label></th><td>';
		printf(
			'<input type="password" id="inite-agents-key" name="%1$s[key]" class="regular-text code" autocomplete="off" placeholder="%2$s" value="">',
			esc_attr( $name ),
			esc_attr( '' !== $s['key'] ? substr( $s['key'], 0, 12 ) . '…' : 'isk_…' )
		);
		echo '<p class="description">' . esc_html( '' !== $s['key'] ? __( 'A key is saved. Leave the field empty to keep it.', 'inite-agents' ) : __( 'Paste the key the cabinet showed you once.', 'inite-agents' ) ) . '</p>';
		if ( '' !== $s['key'] ) {
			printf( '<label><input type="checkbox" name="%s[forget_key]" value="1"> %s</label>', esc_attr( $name ), esc_html__( 'Remove the key and stop serving', 'inite-agents' ) );
		}
		echo '</td></tr>';
		foreach ( self::features() as $feature => $label ) {
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
		printf(
			'<tr><th scope="row">%1$s</th><td><select name="%2$s[content_status]"><option value="draft" %3$s>%4$s</option><option value="publish" %5$s>%6$s</option></select></td></tr>',
			esc_html__( 'New articles', 'inite-agents' ),
			esc_attr( $name ),
			selected( $s['content_status'], 'draft', false ),
			esc_html__( 'Save as drafts: I press Publish in WordPress', 'inite-agents' ),
			selected( $s['content_status'], 'publish', false ),
			esc_html__( 'Publish at once', 'inite-agents' )
		);
		echo '</table>';
		Inite_Agents_Admin_Sending::render( $s, $name );
		submit_button();
		echo '</form>';

		echo '<h2>' . esc_html__( 'Connection', 'inite-agents' ) . '</h2>';
		echo wp_kses_post( Inite_Agents_Admin_Status::connection( $copy, $status ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="inite_agents_test">';
		wp_nonce_field( 'inite_agents_test' );
		submit_button( __( 'Test connection', 'inite-agents' ), 'secondary', 'submit', false, '' === $s['key'] ? array( 'disabled' => 'disabled' ) : null );
		echo '</form>';
	}

	/**
	 * The features, each with its label and what it does.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function features() {
		return array(
			'files'     => array( __( 'Root files', 'inite-agents' ), __( 'Serve /llms.txt, /llms-full.txt, /.well-known/agent-card.json, /.well-known/mcp.json and /.well-known/api-catalog', 'inite-agents' ) ),
			'robots'    => array( __( 'robots.txt', 'inite-agents' ), __( 'Add the Content-Signal line to robots.txt', 'inite-agents' ) ),
			'head'      => array( __( 'Page head', 'inite-agents' ), __( 'Add the WebMCP script and the links to the files', 'inite-agents' ) ),
			'abilities' => array( __( 'Abilities for the MCP Adapter', 'inite-agents' ), __( 'Register five read-only abilities agents can call through the WordPress MCP Adapter', 'inite-agents' ) ),
			'ping'      => array( __( 'Daily report', 'inite-agents' ), __( 'Count AI agent reads and visits from AI assistants, and send INITE the counts with the plugin version and the paths served (counts only: no address, no cookie)', 'inite-agents' ) ),
			'content'   => array( __( 'Articles', 'inite-agents' ), __( 'Create posts from the articles you approved in the INITE cabinet, and tell INITE their addresses', 'inite-agents' ) ),
		);
	}
}
