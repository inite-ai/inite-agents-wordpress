<?php
/**
 * What the site gets back from INITE, inside WordPress: the dashboard widget
 * and the Overview tab of Settings → INITE Agents.
 *
 * From INITE's summary (Inite_Agents_Summary): the AI visibility score and
 * the failed checks of the latest analysis, what assistants say against the
 * confirmed facts, thirty days of agent reads and visitors from assistants,
 * the suggestions and articles waiting. From the plugin's own counts: which
 * agents read which paths this week, and which assistants sent people to
 * which pages. Each item links to the INITE cabinet where it is acted on, or
 * to the fix in the Fixes tab.
 *
 * Breakdowns INITE keeps for Atlas Premium arrive as totals without it; the
 * page says so once, in one plain line, and asks nothing.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overview and widget.
 */
final class Inite_Agents_Admin_Overview {

	/** Hook the widget. */
	public static function register() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget_setup' ) );
		add_action( 'admin_post_inite_agents_refresh', array( __CLASS__, 'refresh' ) );
	}

	/** The widget, for those who manage the site. */
	public static function widget_setup() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'inite_agents', __( 'INITE Agents', 'inite-agents' ), array( __CLASS__, 'widget' ) );
		}
	}

	/** "Refresh": fetch the summary now. */
	public static function refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'inite-agents' ), 403 );
		}
		check_admin_referer( 'inite_agents_refresh' );
		Inite_Agents_Summary::get( true );
		wp_safe_redirect( Inite_Agents_Admin::url( 'overview' ) );
		exit;
	}

	/**
	 * Which fix answers a failed check: the tab and switch, or null when it is done in the cabinet.
	 *
	 * @param string $id The check's id (agents:…, schema:…, meta:…).
	 * @return array{tab:string,key:string,on:bool}|null
	 */
	public static function fix_for( $id ) {
		$fixes = array(
			'schema:Organization'      => array( 'fixes', 'schema_business' ),
			'schema:LocalBusiness'     => array( 'fixes', 'schema_business' ),
			'schema:FAQPage'           => array( 'fixes', 'schema_faq' ),
			'schema:Product'           => array( 'fixes', 'schema_products' ),
			'meta:description'         => array( 'fixes', 'meta_description' ),
			'agents:agent_card'        => array( 'settings', 'files' ),
			'agents:mcp_server_card'   => array( 'settings', 'files' ),
			'agents:api_catalog'       => array( 'settings', 'files' ),
			'agents:content_signals'   => array( 'settings', 'robots' ),
		);
		if ( ! isset( $fixes[ $id ] ) ) {
			return null;
		}
		list( $tab, $key ) = $fixes[ $id ];
		return array(
			'tab' => $tab,
			'key' => $key,
			'on'  => 'fixes' === $tab ? Inite_Agents_Settings::fix( $key ) : Inite_Agents_Settings::on( $key ),
		);
	}

	/**
	 * A check's name in the admin's language, for the ids the plugin knows; the report's English label otherwise.
	 *
	 * @param array $check The check.
	 * @return string
	 */
	public static function check_label( array $check ) {
		$names = array(
			'schema:Organization'    => __( 'Organization schema on the front page', 'inite-agents' ),
			'schema:LocalBusiness'   => __( 'LocalBusiness schema on the front page', 'inite-agents' ),
			'schema:FAQPage'         => __( 'FAQPage schema', 'inite-agents' ),
			'schema:Product'         => __( 'Product schema', 'inite-agents' ),
			'meta:description'       => __( 'Meta description on the front page', 'inite-agents' ),
			'agents:agent_card'      => __( 'A2A agent card at /.well-known/agent-card.json', 'inite-agents' ),
			'agents:mcp_server_card' => __( 'MCP server card at /.well-known/mcp.json', 'inite-agents' ),
			'agents:api_catalog'     => __( 'API catalog at /.well-known/api-catalog', 'inite-agents' ),
			'agents:content_signals' => __( 'Content Signals in robots.txt', 'inite-agents' ),
		);
		$id = isset( $check['id'] ) ? (string) $check['id'] : '';
		return isset( $names[ $id ] ) ? $names[ $id ] : ( isset( $check['label'] ) ? (string) $check['label'] : $id );
	}

	/**
	 * A number as the site writes numbers.
	 *
	 * @param mixed $n Number.
	 * @return string
	 */
	private static function n( $n ) {
		return number_format_i18n( (int) $n );
	}

	/**
	 * A summary value, or a default.
	 *
	 * @param array  $s    Summary.
	 * @param string $path Dotted path.
	 * @param mixed  $def  Default.
	 * @return mixed
	 */
	private static function at( $s, $path, $def = null ) {
		foreach ( explode( '.', $path ) as $k ) {
			if ( ! is_array( $s ) || ! array_key_exists( $k, $s ) ) {
				return $def;
			}
			$s = $s[ $k ];
		}
		return $s;
	}

	/** The dashboard widget: four lines, each to where it is acted on. */
	public static function widget() {
		if ( '' === Inite_Agents_Settings::get()['key'] ) {
			echo '<p>' . esc_html__( 'Connect the site with its INITE site key to see what AI agents and assistants do with it.', 'inite-agents' ) . ' <a href="' . esc_url( Inite_Agents_Admin::url( 'settings' ) ) . '">' . esc_html__( 'Add the site key', 'inite-agents' ) . '</a></p>';
			return;
		}
		$held = Inite_Agents_Summary::held();
		$s    = $held['summary'];
		if ( ! $s ) {
			echo '<p>' . esc_html( '' !== $held['error'] ? $held['error'] : __( 'The summary arrives with the next hourly sync.', 'inite-agents' ) ) . ' <a href="' . esc_url( Inite_Agents_Admin::url( 'overview' ) ) . '">' . esc_html__( 'Open the overview', 'inite-agents' ) . '</a></p>';
			return;
		}
		$cabinet = Inite_Agents_Summary::link( self::at( $s, 'links.cabinet', '' ) );
		$items   = array();
		$score   = self::at( $s, 'analysis.score' );
		if ( null !== $score ) {
			$failed  = (array) self::at( $s, 'analysis.failed', array() );
			$fixable = count( array_filter( $failed, static function ( $c ) {
				$f = self::fix_for( isset( $c['id'] ) ? (string) $c['id'] : '' );
				return $f && ! $f['on'] && ! ( 'meta_description' === $f['key'] && '' !== Inite_Agents_Fixes::seo_plugin() );
			} ) );
			/* translators: %d: score out of 100. */
			$line = sprintf( __( 'AI visibility: %d of 100.', 'inite-agents' ), (int) $score );
			if ( $fixable > 0 ) {
				/* translators: %d: number of checks. */
				$line .= ' ' . sprintf( _n( '%d failed check can be fixed here.', '%d failed checks can be fixed here.', $fixable, 'inite-agents' ), $fixable );
			}
			$items[] = array( $line, $fixable > 0 ? Inite_Agents_Admin::url( 'fixes' ) : Inite_Agents_Summary::link( (string) self::at( $s, 'links.report', '' ) ) );
		} else {
			$items[] = array( __( 'No analysis of this domain yet.', 'inite-agents' ), Inite_Agents_Summary::link( '/{lang}/analyze' ) );
		}
		$stuck = array();
		foreach ( (array) self::at( $s, 'bots.rows', array() ) as $r ) {
			if ( is_array( $r ) && in_array( self::at( $r, 'state' ), array( 'probably_blocked', 'robots_blocked' ), true ) && 'training' !== self::at( $r, 'kind' ) ) {
				$stuck[] = (string) self::at( $r, 'name', '' );
			}
		}
		if ( $stuck ) {
			/* translators: %s: robot names. */
			$items[] = array( sprintf( __( 'AI robots that may not reach your pages: %s.', 'inite-agents' ), implode( ', ', $stuck ) ), Inite_Agents_Admin::url( 'overview' ) . '#inite-agents-bots' );
		}
		/* translators: %s: number of reads. */
		$items[] = array( sprintf( __( 'AI agents read your agent files %s times in 30 days.', 'inite-agents' ), self::n( self::at( $s, 'visits.total', 0 ) ) ), Inite_Agents_Admin::url( 'overview' ) . '#inite-agents-reads' );
		/* translators: %s: number of visits. */
		$items[] = array( sprintf( __( 'People from AI assistants: %s in 30 days.', 'inite-agents' ), self::n( max( (int) self::at( $s, 'referrals.total', 0 ), Inite_Agents_Referrals::totals()['total'] ) ) ), Inite_Agents_Admin::url( 'overview' ) . '#inite-agents-referrals' );
		$open     = (int) self::at( $s, 'suggestions.open', 0 );
		$awaiting = (int) self::at( $s, 'articles.awaiting', 0 );
		if ( $open ) {
			/* translators: %s: number of suggestions. */
			$items[] = array( sprintf( _n( '%s suggestion from your site waits in INITE.', '%s suggestions from your site wait in INITE.', $open, 'inite-agents' ), self::n( $open ) ), $cabinet );
		}
		if ( $awaiting ) {
			/* translators: %s: number of articles. */
			$items[] = array( sprintf( _n( '%s article waits for your approval in INITE.', '%s articles wait for your approval in INITE.', $awaiting, 'inite-agents' ), self::n( $awaiting ) ), $cabinet );
		}
		echo '<ul>';
		foreach ( $items as $item ) {
			echo '<li>' . ( $item[1] ? '<a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a>' : esc_html( $item[0] ) ) . '</li>';
		}
		echo '</ul><p class="community-events-footer" style="margin:0;padding-top:8px;border-top:1px solid #f0f0f1"><a href="' . esc_url( Inite_Agents_Admin::url( 'overview' ) ) . '">' . esc_html__( 'Overview', 'inite-agents' ) . '</a> | <a href="' . esc_url( $cabinet ) . '" target="_blank" rel="noopener">' . esc_html__( 'INITE cabinet', 'inite-agents' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'inite-agents' ) . '</span></a></p>';
	}

	/** The Overview tab. */
	public static function render() {
		if ( '' === Inite_Agents_Settings::get()['key'] ) {
			echo '<p>' . esc_html__( 'Connect the site with its INITE site key to see what AI agents and assistants do with it.', 'inite-agents' ) . ' <a href="' . esc_url( Inite_Agents_Admin::url( 'settings' ) ) . '">' . esc_html__( 'Add the site key', 'inite-agents' ) . '</a></p>';
			return;
		}
		$held = Inite_Agents_Summary::get();
		$s    = is_array( $held['summary'] ) ? $held['summary'] : array();
		if ( '' !== $held['error'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $held['error'] ) . ( $s ? ' ' . esc_html__( 'Showing the last summary received.', 'inite-agents' ) : '' ) . '</p></div>';
		}
		echo '<p>';
		if ( $held['fetched_at'] ) {
			/* translators: %s: date and time. */
			echo esc_html( sprintf( __( 'From INITE, %s.', 'inite-agents' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $held['fetched_at'] ) ) ) . ' ';
		}
		echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:-8px 0 8px"><input type="hidden" name="action" value="inite_agents_refresh">';
		wp_nonce_field( 'inite_agents_refresh' );
		submit_button( __( 'Refresh', 'inite-agents' ), 'secondary small', 'submit', false );
		echo '</form>';

		self::analysis( $s );
		self::assistants( $s );
		Inite_Agents_Admin_Signals::bots( $s );
		self::reads( $s );
		self::referrals( $s );
		Inite_Agents_Admin_Signals::survey( $s );
		Inite_Agents_Admin_Signals::indexnow();
		self::waiting( $s );
		if ( $s && false === self::at( $s, 'premium' ) ) {
			echo '<p class="description">' . esc_html__( 'INITE shows its thirty-day breakdowns by agent, page and assistant with Atlas Premium; here they are totals. The counts this plugin keeps itself are shown in full.', 'inite-agents' ) . '</p>';
		}
	}

	/**
	 * A section heading with its cabinet link.
	 *
	 * @param string $id    Anchor.
	 * @param string $title Heading.
	 * @param string $link  Where it is acted on, or ''.
	 * @param string $label The link's words.
	 */
	private static function heading( $id, $title, $link = '', $label = '' ) {
		echo '<h2 id="' . esc_attr( $id ) . '" class="title">' . esc_html( $title ) . '</h2>';
		if ( $link ) {
			echo '<p><a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'inite-agents' ) . '</span></a></p>';
		}
	}

	/**
	 * A two-column table of counts.
	 *
	 * @param array  $rows  name => count.
	 * @param string $head1 First heading.
	 * @param string $head2 Second heading.
	 */
	private static function counts( array $rows, $head1, $head2 ) {
		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th scope="col">' . esc_html( $head1 ) . '</th><th scope="col" style="width:120px;text-align:right">' . esc_html( $head2 ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $name => $count ) {
			echo '<tr><td><code>' . esc_html( '' === (string) $name ? __( 'others', 'inite-agents' ) : (string) $name ) . '</code></td><td style="text-align:right">' . esc_html( self::n( $count ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Counts from the service ([{name,count}]) as name => count.
	 *
	 * @param mixed $list The list.
	 * @return array
	 */
	private static function pairs( $list ) {
		$out = array();
		foreach ( is_array( $list ) ? $list : array() as $r ) {
			if ( is_array( $r ) && isset( $r['count'] ) ) {
				$out[ isset( $r['name'] ) ? (string) $r['name'] : '' ] = (int) $r['count'];
			}
		}
		return $out;
	}

	/**
	 * The AI visibility score and the failed checks.
	 *
	 * @param array $s Summary.
	 */
	private static function analysis( array $s ) {
		$a = self::at( $s, 'analysis' );
		self::heading( 'inite-agents-analysis', __( 'AI visibility', 'inite-agents' ), $a ? Inite_Agents_Summary::link( (string) self::at( $s, 'links.report', '' ) ) : Inite_Agents_Summary::link( '/{lang}/analyze' ), $a ? __( 'Open the full report in INITE', 'inite-agents' ) : __( 'Analyze the site in INITE', 'inite-agents' ) );
		if ( ! $a ) {
			echo '<p>' . esc_html__( 'No analysis of this domain yet. Once one runs, its score and the checks it failed appear here.', 'inite-agents' ) . '</p>';
			return;
		}
		$score = self::at( $a, 'score' );
		/* translators: 1: score out of 100, 2: date. */
		echo '<p style="font-size:14px">' . wp_kses_post( sprintf( __( '%1$s of 100, analysis of %2$s.', 'inite-agents' ), '<strong style="font-size:20px">' . esc_html( null === $score ? '–' : (string) (int) $score ) . '</strong>', esc_html( wp_date( get_option( 'date_format' ), strtotime( (string) self::at( $a, 'at', '' ) ) ) ) ) ) . '</p>';
		$failed = (array) self::at( $a, 'failed', array() );
		if ( ! $failed ) {
			echo '<p>' . esc_html__( 'The analysis found nothing here that the site can fix by itself.', 'inite-agents' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:780px"><thead><tr><th scope="col">' . esc_html__( 'Failed check', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'What to do', 'inite-agents' ) . '</th></tr></thead><tbody>';
		foreach ( $failed as $check ) {
			if ( ! is_array( $check ) ) {
				continue;
			}
			$fix = self::fix_for( isset( $check['id'] ) ? (string) $check['id'] : '' );
			$seo = 'meta_description' === ( $fix ? $fix['key'] : '' ) ? Inite_Agents_Fixes::seo_plugin() : '';
			if ( '' !== $seo ) {
				/* translators: %s: SEO plugin name. */
				$do = esc_html( sprintf( __( '%s writes it: set it there.', 'inite-agents' ), $seo ) );
			} elseif ( ! $fix ) {
				$do = esc_html__( 'Done in the INITE cabinet.', 'inite-agents' );
			} elseif ( $fix['on'] ) {
				$do = esc_html__( 'On here. The next analysis will see it.', 'inite-agents' );
			} else {
				$do = '<a href="' . esc_url( Inite_Agents_Admin::url( $fix['tab'] ) . '#inite-fix-' . $fix['key'] ) . '">' . esc_html( 'fixes' === $fix['tab'] ? __( 'Switch on the fix', 'inite-agents' ) : __( 'Switch on the feature', 'inite-agents' ) ) . '</a>';
			}
			echo '<tr><td>' . esc_html( self::check_label( $check ) ) . '</td><td>' . wp_kses_post( $do ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * What assistants say against the confirmed facts.
	 *
	 * @param array $s Summary.
	 */
	private static function assistants( array $s ) {
		$a = self::at( $s, 'assistants' );
		self::heading( 'inite-agents-assistants', __( 'What assistants say about you', 'inite-agents' ), Inite_Agents_Summary::link( (string) self::at( $s, 'links.cabinet', '' ) ), __( 'Compare in the INITE cabinet', 'inite-agents' ) );
		if ( ! is_array( $a ) ) {
			echo '<p>' . esc_html__( 'Not checked yet. INITE asks AI assistants about your business and compares the answers with your confirmed facts; the result appears here.', 'inite-agents' ) . '</p>';
			return;
		}
		/* translators: 1: answers that match, 2: answers that differ. */
		echo '<p>' . esc_html( sprintf( __( '%1$s answers match your facts, %2$s differ.', 'inite-agents' ), self::n( self::at( $a, 'match', 0 ) ), self::n( self::at( $a, 'differs', 0 ) ) ) ) . '</p>';
		self::shares( $a );
		$diffs = (array) self::at( $a, 'differences', array() );
		if ( $diffs ) {
			$facts = array(
				'does'    => __( 'What you do', 'inite-agents' ),
				'opens'   => __( 'Weekday opening', 'inite-agents' ),
				'sunday'  => __( 'Sunday', 'inite-agents' ),
				'price'   => __( 'Price', 'inite-agents' ),
				'address' => __( 'Address', 'inite-agents' ),
				'phone'   => __( 'Phone', 'inite-agents' ),
			);
			echo '<table class="widefat striped" style="max-width:780px"><thead><tr><th scope="col">' . esc_html__( 'Assistant', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'Fact', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'Your facts', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'It said', 'inite-agents' ) . '</th></tr></thead><tbody>';
			foreach ( $diffs as $d ) {
				$key  = (string) self::at( $d, 'key', '' );
				$said = self::at( $d, 'said' );
				echo '<tr><td>' . esc_html( (string) self::at( $d, 'source', '' ) ) . '</td><td>' . esc_html( isset( $facts[ $key ] ) ? $facts[ $key ] : $key ) . '</td><td>' . esc_html( (string) self::at( $d, 'expected', '' ) ) . '</td><td>' . esc_html( is_string( $said ) && '' !== $said ? $said : __( 'something else (open the cabinet for the details)', 'inite-agents' ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Each question is asked more than once, and answers vary: how often the
	 * business was named, and how often a page of the site was cited, as shares.
	 * Summaries before 0.10.0's service carry neither, and say nothing here.
	 *
	 * @param array $a The summary's assistants part.
	 */
	private static function shares( array $a ) {
		$share = static function ( $v ) {
			return is_array( $v ) && isset( $v['k'], $v['n'] ) && (int) $v['n'] > 0 ? array( (int) $v['k'], (int) $v['n'] ) : null;
		};
		$named = $share( self::at( $a, 'named' ) );
		$cited = $share( self::at( $a, 'cited' ) );
		$runs  = (int) self::at( $a, 'runs', 0 );
		$parts = array();
		if ( $runs > 1 ) {
			/* translators: %d: how many times each question was asked. */
			$parts[] = sprintf( __( 'Each question was asked %d times.', 'inite-agents' ), $runs );
		}
		if ( $named ) {
			/* translators: 1: answers naming the business, 2: answers. */
			$parts[] = sprintf( __( 'Named in %1$s of %2$s answers.', 'inite-agents' ), self::n( $named[0] ), self::n( $named[1] ) );
		}
		if ( $cited ) {
			/* translators: 1: answers citing the site, 2: answers. */
			$parts[] = sprintf( __( 'Cited with a link to your site in %1$s of %2$s.', 'inite-agents' ), self::n( $cited[0] ), self::n( $cited[1] ) );
		}
		if ( $parts ) {
			echo '<p>' . esc_html( implode( ' ', $parts ) ) . '</p>';
		}
		$engines = (array) self::at( $a, 'engines', array() );
		if ( $engines ) {
			echo '<table class="widefat striped" style="max-width:640px;margin-bottom:12px"><thead><tr><th scope="col">' . esc_html__( 'Assistant', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'Named', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'Cited', 'inite-agents' ) . '</th></tr></thead><tbody>';
			foreach ( $engines as $e ) {
				$en = $share( self::at( $e, 'named' ) );
				$ec = $share( self::at( $e, 'cited' ) );
				echo '<tr><td>' . esc_html( (string) self::at( $e, 'source', '' ) ) . '</td><td>' . esc_html( $en ? self::n( $en[0] ) . ' / ' . self::n( $en[1] ) : '-' ) . '</td><td>' . esc_html( $ec ? self::n( $ec[0] ) . ' / ' . self::n( $ec[1] ) : __( 'no sources', 'inite-agents' ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Which agents read which paths: this week from the plugin, thirty days from INITE.
	 *
	 * @param array $s Summary.
	 */
	private static function reads( array $s ) {
		self::heading( 'inite-agents-reads', __( 'AI agents reading your site', 'inite-agents' ) );
		if ( ! Inite_Agents_Settings::on( 'ping' ) ) {
			echo '<p>' . wp_kses_post( sprintf( /* translators: %s: link to the settings. */ __( 'The plugin counts agent reads while the daily report is on. %s', 'inite-agents' ), '<a href="' . esc_url( Inite_Agents_Admin::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'inite-agents' ) . '</a>' ) ) . '</p>';
		}
		$local = array();
		foreach ( Inite_Agents_Visitors::rows() as $r ) {
			$k           = $r['agent'] . '  ' . $r['path'];
			$local[ $k ] = ( isset( $local[ $k ] ) ? $local[ $k ] : 0 ) + $r['hits'];
		}
		arsort( $local );
		if ( $local ) {
			echo '<p>' . esc_html__( 'Counted on this site in the last 7 days:', 'inite-agents' ) . '</p>';
			self::counts( array_slice( $local, 0, 15, true ), __( 'Agent and path', 'inite-agents' ), __( 'Reads', 'inite-agents' ) );
		}
		/* translators: 1: reads, 2: MCP calls. */
		echo '<p>' . esc_html( sprintf( __( 'INITE, last 30 days: %1$s reads of your agent files, %2$s calls to your MCP server.', 'inite-agents' ), self::n( self::at( $s, 'visits.total', 0 ) ), self::n( self::at( $s, 'mcp.total', 0 ) ) ) ) . '</p>';
		$by_agent = self::pairs( self::at( $s, 'visits.byAgent' ) );
		if ( $by_agent ) {
			self::counts( $by_agent, __( 'Agent', 'inite-agents' ), __( 'Reads', 'inite-agents' ) );
		}
	}

	/**
	 * People who came from AI assistants.
	 *
	 * @param array $s Summary.
	 */
	private static function referrals( array $s ) {
		self::heading( 'inite-agents-referrals', __( 'People from AI assistants', 'inite-agents' ) );
		if ( ! Inite_Agents_Settings::on( 'ping' ) ) {
			echo '<p>' . esc_html__( 'Off. With the daily report on, the plugin counts visits that come from ChatGPT, Perplexity, Gemini, Copilot, Claude and other assistants: per day and page, no address and no cookie.', 'inite-agents' ) . '</p>';
			return;
		}
		$t = Inite_Agents_Referrals::totals();
		/* translators: %s: number of visits. */
		echo '<p>' . esc_html( sprintf( __( '%s visits in the last 30 days, counted on this site. Pages served from a full-page cache are not counted, so this is a floor.', 'inite-agents' ), self::n( $t['total'] ) ) ) . '</p>';
		if ( $t['total'] ) {
			echo '<div style="display:flex;flex-wrap:wrap;gap:16px">';
			echo '<div style="flex:1 1 280px">';
			self::counts( $t['by_source'], __( 'Assistant', 'inite-agents' ), __( 'Visits', 'inite-agents' ) );
			echo '</div><div style="flex:1 1 280px">';
			self::counts( $t['by_path'], __( 'Page', 'inite-agents' ), __( 'Visits', 'inite-agents' ) );
			echo '</div></div>';
		}
	}

	/**
	 * What waits for the owner in the cabinet.
	 *
	 * @param array $s Summary.
	 */
	private static function waiting( array $s ) {
		$cabinet = Inite_Agents_Summary::link( (string) self::at( $s, 'links.cabinet', '' ) );
		self::heading( 'inite-agents-waiting', __( 'Waiting for you', 'inite-agents' ) );
		$open     = (int) self::at( $s, 'suggestions.open', 0 );
		$awaiting = (int) self::at( $s, 'articles.awaiting', 0 );
		echo '<ul class="ul-disc">';
		/* translators: %s: number. */
		echo '<li>' . esc_html( sprintf( _n( '%s suggestion read from your site: a value your facts do not say yet.', '%s suggestions read from your site: values your facts do not say yet.', $open, 'inite-agents' ), self::n( $open ) ) ) . ( $open && $cabinet ? ' <a href="' . esc_url( $cabinet ) . '" target="_blank" rel="noopener">' . esc_html__( 'Review in INITE', 'inite-agents' ) . '</a>' : '' ) . '</li>';
		/* translators: %s: number. */
		echo '<li>' . esc_html( sprintf( _n( '%s article drafted and waiting for your approval.', '%s articles drafted and waiting for your approval.', $awaiting, 'inite-agents' ), self::n( $awaiting ) ) ) . ( $awaiting && $cabinet ? ' <a href="' . esc_url( $cabinet ) . '" target="_blank" rel="noopener">' . esc_html__( 'Read and approve in INITE', 'inite-agents' ) . '</a>' : '' ) . '</li>';
		echo '</ul>';
	}
}
