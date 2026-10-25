<?php
/**
 * The Overview's sections about reaching the site and who it reached:
 *   - which robots may open the site: what INITE saw when it opened the key
 *     pages with each robot's name, and what robots.txt tells each of them
 *     (from INITE's summary, `bots`);
 *   - "How did you hear about us": the plugin's own counts, and INITE's
 *     thirty days (`survey`), with the share that named an AI assistant;
 *   - IndexNow: on or off, the last ping, the addresses sent today.
 *
 * A 403 or a captcha for our request with a robot's name is not proof the
 * real robot is turned away: firewalls check the robot's addresses, not only
 * its name. The page says "probably" and where to confirm.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overview: robots, the survey, IndexNow.
 */
final class Inite_Agents_Admin_Signals {

	/**
	 * A summary value, or a default.
	 *
	 * @param mixed  $s    Summary part.
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

	/**
	 * A heading.
	 *
	 * @param string $id    Anchor.
	 * @param string $title Words.
	 */
	private static function heading( $id, $title ) {
		echo '<h2 id="' . esc_attr( $id ) . '" class="title">' . esc_html( $title ) . '</h2>';
	}

	/**
	 * Why our request with a robot's name did not get the page.
	 *
	 * @param string $fetch The fetch outcome.
	 * @return string
	 */
	public static function reason( $fetch ) {
		$words = array(
			'refused'     => __( 'a refusal (403 or similar)', 'inite-agents' ),
			'captcha'     => __( 'a captcha or a "checking your browser" page', 'inite-agents' ),
			'empty'       => __( 'an empty page', 'inite-agents' ),
			'js_shell'    => __( 'a page whose text appears only after JavaScript runs', 'inite-agents' ),
			'unreachable' => __( 'no answer', 'inite-agents' ),
		);
		return isset( $words[ $fetch ] ) ? $words[ $fetch ] : (string) $fetch;
	}

	/**
	 * Which robots may open the site.
	 *
	 * @param array $s Summary.
	 */
	public static function bots( array $s ) {
		self::heading( 'inite-agents-bots', __( 'Can AI robots open your site?', 'inite-agents' ) );
		$b = self::at( $s, 'bots' );
		if ( ! is_array( $b ) ) {
			echo '<p>' . esc_html__( 'Not checked yet. Once a week INITE opens your key pages with the names of the robots behind Bing, ChatGPT, Claude, Perplexity and Yandex, and reads what robots.txt tells each of them; the result appears here.', 'inite-agents' ) . '</p>';
			return;
		}
		$at = strtotime( (string) self::at( $b, 'at', '' ) );
		if ( $at ) {
			/* translators: %s: date. */
			echo '<p>' . esc_html( sprintf( __( 'Checked %s.', 'inite-agents' ), wp_date( get_option( 'date_format' ), $at ) ) ) . '</p>';
		}
		if ( self::at( $b, 'cloudflareManaged' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'robots.txt has a "Cloudflare Managed content" block that may add rules for AI bots - check it in Cloudflare.', 'inite-agents' ) . '</p></div>';
		}
		$robots_txt = (string) self::at( $b, 'robotsTxt', 'ok' );
		if ( 'unreachable' === $robots_txt ) {
			echo '<p>' . esc_html__( 'robots.txt did not answer, so the rules below could not be read.', 'inite-agents' ) . '</p>';
		}
		$kinds = array(
			'search'   => __( 'Search', 'inite-agents' ),
			'user'     => __( 'Opens a page for a person', 'inite-agents' ),
			'training' => __( 'Training', 'inite-agents' ),
		);
		$robots_words = array(
			'allowed' => __( 'allowed', 'inite-agents' ),
			'partial' => __( 'some paths closed', 'inite-agents' ),
			'blocked' => __( 'blocked', 'inite-agents' ),
		);
		$rows    = (array) self::at( $b, 'rows', array() );
		$blocked = array();
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th scope="col">' . esc_html__( 'Robot', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'What it does', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'robots.txt', 'inite-agents' ) . '</th><th scope="col">' . esc_html__( 'Our request with its name', 'inite-agents' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$state = (string) self::at( $r, 'state', 'unknown' );
			$fetch = (string) self::at( $r, 'fetch', 'not_checked' );
			$kind  = (string) self::at( $r, 'kind', '' );
			$robot = (string) self::at( $r, 'robots', 'allowed' );
			if ( 'not_checked' === $fetch ) {
				$seen = 'training' === $kind ? __( 'not opened: robots.txt only', 'inite-agents' ) : __( 'not opened', 'inite-agents' );
			} elseif ( 'open' === $fetch ) {
				/* translators: %d: pages. */
				$seen = sprintf( _n( 'got %d page', 'got all %d pages', max( 1, (int) self::at( $r, 'pages', 1 ) ), 'inite-agents' ), (int) self::at( $r, 'pages', 1 ) );
			} else {
				/* translators: 1: pages that failed, 2: pages opened, 3: what came back. */
				$seen = sprintf( __( '%1$d of %2$d pages: %3$s', 'inite-agents' ), (int) self::at( $r, 'failedPages', 0 ), (int) self::at( $r, 'pages', 0 ), self::reason( $fetch ) );
			}
			if ( 'probably_blocked' === $state ) {
				$blocked[] = array( (string) self::at( $r, 'name', '' ), $fetch );
			}
			$color = 'open' === $state ? '' : ( 'unknown' === $state ? '#646970' : '#b32d2e' );
			echo '<tr><td><code>' . esc_html( (string) self::at( $r, 'name', '' ) ) . '</code></td><td>' . esc_html( isset( $kinds[ $kind ] ) ? $kinds[ $kind ] : $kind ) . '</td><td' . ( 'allowed' === $robot ? '' : ' style="color:#b32d2e"' ) . '>' . esc_html( isset( $robots_words[ $robot ] ) ? $robots_words[ $robot ] : $robot ) . '</td><td' . ( '' === $color ? '' : ' style="color:' . esc_attr( $color ) . '"' ) . '>' . esc_html( $seen ) . '</td></tr>';
		}
		echo '</tbody></table>';
		foreach ( $blocked as $one ) {
			if ( in_array( $one[1], array( 'js_shell', 'empty' ), true ) ) {
				/* translators: %s: what came back. */
				$line = sprintf( __( 'Probably unreadable: our request with this robot\'s name got %s. AI robots other than Google\'s do not run JavaScript, so they find no text to quote. Put the main text, prices and contacts in the HTML the server sends.', 'inite-agents' ), self::reason( $one[1] ) );
			} else {
				/* translators: %s: what came back. */
				$line = sprintf( __( 'Probably blocked: our request with this robot\'s name got %s. Your firewall may treat the real robot differently (it checks the robot\'s addresses) - confirm in Bing Webmaster Tools or your server logs.', 'inite-agents' ), self::reason( $one[1] ) );
			}
			echo '<p><strong>' . esc_html( $one[0] ) . '</strong>: ' . esc_html( $line ) . '</p>';
		}
		echo '<p class="description">' . wp_kses_post( sprintf( /* translators: %s: link to the Fixes tab. */ __( 'Training robots are best left open: assistants also answer from what they were trained on. What robots.txt says is set on the %s tab.', 'inite-agents' ), '<a href="' . esc_url( Inite_Agents_Admin::url( 'fixes' ) . '#inite-fix-robots_policy' ) . '">' . esc_html__( 'Fixes', 'inite-agents' ) . '</a>' ) ) . '</p>';
	}

	/**
	 * "How did you hear about us".
	 *
	 * @param array $s Summary.
	 */
	public static function survey( array $s ) {
		self::heading( 'inite-agents-survey', __( 'How customers heard about you', 'inite-agents' ) );
		if ( ! Inite_Agents_Settings::on( 'survey' ) ) {
			echo '<p>' . wp_kses_post( sprintf( /* translators: %s: link to the settings. */ __( 'Off. With the question on, customers can say where they heard about you, AI assistants among the answers: at the WooCommerce checkout, in Contact Form 7 and WPForms, or anywhere with the [inite_how_heard] shortcode. Only a count per day and answer is kept. %s', 'inite-agents' ), '<a href="' . esc_url( Inite_Agents_Admin::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'inite-agents' ) . '</a>' ) ) . '</p>';
			return;
		}
		$t      = Inite_Agents_Survey::totals();
		$labels = Inite_Agents_Survey::labels();
		if ( ! $t['total'] ) {
			echo '<p>' . esc_html__( 'No answers in the last 30 days yet.', 'inite-agents' ) . '</p>';
		} else {
			/* translators: 1: answers, 2: percent that named an AI assistant. */
			echo '<p>' . esc_html( sprintf( __( '%1$s answers in the last 30 days, counted on this site. An AI assistant: %2$s%%.', 'inite-agents' ), number_format_i18n( $t['total'] ), number_format_i18n( round( 100 * $t['ai'] / $t['total'] ) ) ) ) . '</p>';
			echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th scope="col">' . esc_html__( 'Answer', 'inite-agents' ) . '</th><th scope="col" style="width:120px;text-align:right">' . esc_html__( 'Customers', 'inite-agents' ) . '</th></tr></thead><tbody>';
			foreach ( array_slice( $t['by_answer'], 0, 8, true ) as $key => $n ) {
				echo '<tr><td>' . esc_html( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . '</td><td style="text-align:right">' . esc_html( number_format_i18n( $n ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		$total = (int) self::at( $s, 'survey.total', 0 );
		if ( $total > 0 ) {
			/* translators: 1: answers, 2: percent. */
			echo '<p>' . esc_html( sprintf( __( 'INITE, from the daily reports of the last 30 days: %1$s answers, %2$s%% an AI assistant.', 'inite-agents' ), number_format_i18n( $total ), number_format_i18n( round( 100 * (int) self::at( $s, 'survey.ai', 0 ) / $total ) ) ) ) . '</p>';
		} elseif ( ! Inite_Agents_Settings::on( 'ping' ) ) {
			echo '<p class="description">' . esc_html__( 'The counts reach INITE, and your monthly report, only while the daily report is on.', 'inite-agents' ) . '</p>';
		}
	}

	/** IndexNow: on or off, and what it last did. */
	public static function indexnow() {
		self::heading( 'inite-agents-indexnow', __( 'IndexNow', 'inite-agents' ) );
		if ( ! Inite_Agents_Settings::on( 'indexnow' ) ) {
			echo '<p>' . wp_kses_post( sprintf( /* translators: %s: link to the settings. */ __( 'Off. With IndexNow on, Bing, Yandex and the other engines that share it hear about a new or changed page at once. Google does not use it. %s', 'inite-agents' ), '<a href="' . esc_url( Inite_Agents_Admin::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'inite-agents' ) . '</a>' ) ) . '</p>';
			return;
		}
		$other = Inite_Agents_Indexnow::other();
		if ( '' !== $other ) {
			/* translators: %s: plugin name. */
			echo '<p>' . esc_html( sprintf( __( '%s already sends IndexNow for this site, so this plugin stays quiet.', 'inite-agents' ), $other ) ) . '</p>';
			return;
		}
		$st   = Inite_Agents_Indexnow::state();
		$last = $st['last'];
		if ( empty( $last['at'] ) ) {
			echo '<p>' . esc_html__( 'On. Nothing sent yet: the next page you publish or change is announced a minute later.', 'inite-agents' ) . '</p>';
		} else {
			$code = (int) $last['status'];
			$ok   = $code >= 200 && $code < 300;
			/* translators: 1: date and time, 2: addresses, 3: HTTP status or error. */
			echo '<p>' . esc_html( sprintf( __( 'Last sent %1$s: %2$s addresses, answer %3$s.', 'inite-agents' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['at'] ), number_format_i18n( (int) $last['count'] ), $code ? (string) $code : (string) $last['error'] ) ) . ( $ok ? '' : ' ' . esc_html__( 'Not accepted: the addresses wait for the next try.', 'inite-agents' ) ) . '</p>';
		}
		$today = gmdate( 'Y-m-d' ) === $st['day'] ? $st['sent_today'] : 0;
		/* translators: 1: sent today, 2: daily cap, 3: waiting. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Today: %1$s of %2$s addresses. Waiting: %3$s.', 'inite-agents' ), number_format_i18n( $today ), number_format_i18n( Inite_Agents_Indexnow::DAILY ), number_format_i18n( count( $st['queue'] ) ) ) ) . '</p>';
	}
}
