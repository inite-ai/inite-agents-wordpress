<?php
/**
 * The Fixes tab: one switch per fix, what stands in its way, and a preview
 * of exactly what it adds to the page or to robots.txt.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fixes tab.
 */
final class Inite_Agents_Admin_Fixes {

	/**
	 * A preview block: closed until opened.
	 *
	 * @param string $text What will be added.
	 * @return string HTML.
	 */
	private static function preview( $text ) {
		return '<details style="margin-top:6px"><summary>' . esc_html__( 'Preview', 'inite-agents' ) . '</summary><pre style="max-width:780px;max-height:320px;overflow:auto;background:#f6f7f7;border:1px solid #dcdcde;padding:8px 12px;white-space:pre-wrap">' . esc_html( $text ) . '</pre></details>';
	}

	/**
	 * One switch row.
	 *
	 * @param string $fix   Fix key.
	 * @param string $title Row heading.
	 * @param string $label Checkbox label.
	 * @param string $body  Escaped HTML under it.
	 */
	private static function row( $fix, $title, $label, $body ) {
		printf(
			'<tr id="inite-fix-%1$s"><th scope="row">%2$s</th><td><label><input type="checkbox" name="%3$s[fixes][%1$s]" value="1" %4$s> %5$s</label>%6$s</td></tr>',
			esc_attr( $fix ),
			esc_html( $title ),
			esc_attr( Inite_Agents_Settings::OPTION ),
			checked( ! empty( Inite_Agents_Settings::get()['fixes'][ $fix ] ), true, false ),
			esc_html( $label ),
			$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts here.
		);
	}

	/**
	 * A line under a switch.
	 *
	 * @param string $html Escaped HTML.
	 * @return string
	 */
	private static function note( $html ) {
		return '<p class="description">' . $html . '</p>';
	}

	/** The tab. */
	public static function render() {
		$s = Inite_Agents_Settings::get();
		if ( '' === $s['key'] ) {
			echo '<p>' . esc_html__( 'The fixes use the facts your business confirmed in INITE. Add the site key first.', 'inite-agents' ) . ' <a href="' . esc_url( Inite_Agents_Admin::url( 'settings' ) ) . '">' . esc_html__( 'Add the site key', 'inite-agents' ) . '</a></p>';
			return;
		}
		echo '<p>' . esc_html__( 'Each fix adds one thing the analysis looks for, built from your confirmed facts. Open "Preview" to see exactly what it adds. Nothing is added while a fix is off, and nothing another plugin already prints is printed twice.', 'inite-agents' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'inite_agents' );
		echo '<input type="hidden" name="' . esc_attr( Inite_Agents_Settings::OPTION ) . '[_tab]" value="fixes">';
		echo '<h2 class="title">' . esc_html__( 'Structured data (JSON-LD) on the front page', 'inite-agents' ) . '</h2>';
		$merger = Inite_Agents_Schema::merger();
		if ( '' !== $merger ) {
			/* translators: %s: SEO plugin name. */
			echo '<p>' . esc_html( sprintf( __( '%s is active: what you switch on here goes into its schema graph. A value it already has is kept; only what it lacks is added, and a type it already prints is not added twice.', 'inite-agents' ), $merger ) ) . '</p>';
		}
		echo '<table class="form-table" role="presentation">';
		self::schema_rows();
		echo '</table>';
		echo '<h2 class="title">' . esc_html__( 'robots.txt', 'inite-agents' ) . '</h2><table class="form-table" role="presentation">';
		self::robots_rows( $s );
		echo '</table>';
		echo '<h2 class="title">' . esc_html__( 'Front page', 'inite-agents' ) . '</h2><table class="form-table" role="presentation">';
		self::meta_row();
		echo '</table>';
		submit_button( __( 'Save fixes', 'inite-agents' ) );
		echo '</form>';
	}

	/** The three JSON-LD switches. */
	private static function schema_rows() {
		$nodes  = Inite_Agents_Schema::nodes( true );
		$merger = Inite_Agents_Schema::merger();
		$org    = Inite_Agents_Conflicts::organization_schema();
		$json   = static function ( $v ) {
			return (string) wp_json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		};
		$where = '';

		$body = $where;
		if ( ! $nodes['business'] ) {
			$body .= self::note( esc_html__( 'Nothing to add yet: the stored copy has no business node. Test the connection in Settings.', 'inite-agents' ) );
		} else {
			if ( '' === $merger && '' !== $org ) {
				/* translators: %s: SEO plugin name. */
				$body .= self::note( esc_html( sprintf( __( 'Left out: %s already prints an Organization.', 'inite-agents' ), $org ) ) );
			}
			$body .= self::preview( $json( $nodes['business'] ) );
		}
		self::row( 'schema_business', __( 'Business', 'inite-agents' ), __( 'Organization or LocalBusiness: name, description, address, opening hours, phone, email and services', 'inite-agents' ), $body );

		$body = $where;
		if ( ! $nodes['faq'] ) {
			$body .= self::note( esc_html__( 'Nothing to add: your confirmed facts have no questions and answers.', 'inite-agents' ) );
		} else {
			$body .= self::note( esc_html__( 'Left out of a page that has an SEO plugin\'s FAQ block, which prints its own.', 'inite-agents' ) ) . self::preview( $json( $nodes['faq'] ) );
		}
		self::row( 'schema_faq', __( 'Questions', 'inite-agents' ), __( 'FAQPage from the questions and answers you confirmed', 'inite-agents' ), $body );

		$body = $where;
		if ( ! $nodes['products'] ) {
			$body .= self::note( esc_html__( 'Nothing to add: no confirmed service is sold per item with a price. Services stay in the business node as offers.', 'inite-agents' ) );
		} else {
			$body .= self::preview( $json( $nodes['products'] ) );
		}
		self::row( 'schema_products', __( 'Products', 'inite-agents' ), __( 'A Product with its price for each confirmed service sold per item', 'inite-agents' ), $body );
	}

	/**
	 * The robots.txt switches.
	 *
	 * @param array $s Settings.
	 */
	private static function robots_rows( array $s ) {
		$now   = Inite_Agents_Fixes::robots_now();
		$name  = esc_attr( Inite_Agents_Settings::OPTION );
		$paste = $now['physical'] ? self::note( esc_html__( 'A robots.txt file exists on the server, so WordPress does not generate one and this fix cannot add to it. Paste the preview into that file instead.', 'inite-agents' ) ) : '';

		$groups = array(
			'training' => array( __( 'Block crawlers that collect pages to train AI models', 'inite-agents' ), '' ),
			'search'   => array( __( 'Block AI search crawlers', 'inite-agents' ), __( 'Assistants then cannot find and cite your pages.', 'inite-agents' ) ),
			'answer'   => array( __( 'Block assistants opening a page for a person who asked', 'inite-agents' ), __( 'Assistants then cannot read your page when someone asks about you.', 'inite-agents' ) ),
		);
		$sub = '<fieldset style="margin-top:8px"><legend class="screen-reader-text">' . esc_html__( 'Which AI crawlers to block', 'inite-agents' ) . '</legend>';
		foreach ( $groups as $g => $words ) {
			$sub .= '<label style="display:block;margin:4px 0 4px 24px"><input type="checkbox" name="' . $name . '[robots_block][' . esc_attr( $g ) . ']" value="1" ' . checked( ! empty( $s['robots_block'][ $g ] ), true, false ) . '> ' . esc_html( $words[0] ) . ' <span class="description">(' . esc_html( implode( ', ', Inite_Agents_Fixes::BOTS[ $g ] ) ) . ')</span>' . ( $words[1] ? ' <em>' . esc_html( $words[1] ) . '</em>' : '' ) . '</label>';
		}
		$sub  .= '</fieldset>';
		$lines = Inite_Agents_Fixes::policy_lines( $now['text'], $s['robots_block'] );
		$sub  .= self::note( esc_html__( 'Recommended: block training only, and let search and answers in. An agent another plugin already names in robots.txt is left to it.', 'inite-agents' ) );
		$sub  .= $paste . ( '' !== $lines ? self::preview( $lines ) : self::note( esc_html__( 'Nothing to add with these choices.', 'inite-agents' ) ) );
		self::row( 'robots_policy', __( 'AI crawlers', 'inite-agents' ), __( 'Tell AI crawlers in robots.txt what they may do', 'inite-agents' ), $sub );

		$url  = Inite_Agents_Fixes::sitemap_url();
		$line = Inite_Agents_Fixes::sitemap_line( $now['text'] );
		if ( '' === $url ) {
			$body = self::note( esc_html__( 'No sitemap found: WordPress\'s own sitemap is switched off and no SEO plugin provides one.', 'inite-agents' ) );
		} elseif ( '' === $line ) {
			/* translators: %s: sitemap address. */
			$body = self::note( wp_kses_post( sprintf( __( 'Check passed: robots.txt already names a sitemap. Yours is %s.', 'inite-agents' ), '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>' ) ) );
		} else {
			$body = self::note( esc_html__( 'Check failed: robots.txt does not name your sitemap, so a crawler has to guess where it is.', 'inite-agents' ) ) . $paste . self::preview( $line );
		}
		self::row( 'sitemap_line', __( 'Sitemap link', 'inite-agents' ), __( 'Name the sitemap in robots.txt', 'inite-agents' ), $body );
	}

	/** The meta description switch. */
	private static function meta_row() {
		$seo  = Inite_Agents_Fixes::seo_plugin();
		$text = Inite_Agents_Fixes::meta_text();
		if ( '' !== $seo ) {
			/* translators: %s: SEO plugin name. */
			$body = self::note( esc_html( sprintf( __( 'Not needed: %s writes the meta description. Set it there.', 'inite-agents' ), $seo ) ) );
		} elseif ( '' === $text ) {
			$body = self::note( esc_html__( 'Nothing to add: there is no confirmed description and no tagline.', 'inite-agents' ) );
		} else {
			$body = self::note( esc_html__( 'From the one-line description you confirmed in INITE. If your theme already prints a meta description, leave this off.', 'inite-agents' ) ) . self::preview( '<meta name="description" content="' . $text . '">' );
		}
		self::row( 'meta_description', __( 'Meta description', 'inite-agents' ), __( 'Add a meta description to the front page', 'inite-agents' ), $body );
	}
}
