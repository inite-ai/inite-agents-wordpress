<?php
/**
 * "How did you hear about us?": one optional question with fixed answers,
 * AI assistants among them, so the owner sees what share of customers an
 * assistant sent. Analytics cannot: a link from an assistant app often
 * arrives with no referrer, and Alice's and Yandex's neural answers count as
 * search.
 *
 * Off until the owner switches it on. Where it is asked:
 *   - WooCommerce checkout, as an optional field (classic and block checkout);
 *   - Contact Form 7, with the form tag [inite_how_heard];
 *   - WPForms, in a dropdown or radio field given the CSS class
 *     `inite-how-heard` whose choices are the answers' words;
 *   - anywhere else, with the shortcode [inite_how_heard], a small form of
 *     its own.
 *
 * What is kept: a number per day and answer, nothing else. No name, no
 * address, no order, no cookie. The last DAYS days are kept on the site; the
 * daily report sends the last week of counts when both are on. The block
 * checkout keeps the answer on the order as WooCommerce keeps every field
 * it shows, for the shop to read.
 *
 * @package Inite_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The survey.
 */
final class Inite_Agents_Survey {

	const OPTION    = 'inite_agents_survey';
	const FIELD     = 'inite_how_heard';
	const BLOCK_ID  = 'inite/how-heard';
	const CSS       = 'inite-how-heard';
	const DAYS      = 30;
	const SENT_DAYS = 7;
	/** Answers counted a day at most: a form flooded by a script cannot fill the option. */
	const DAILY = 2000;

	/** The answers, in the order they are offered. The keys are what the service counts. */
	const ANSWERS = array( 'google', 'yandex', 'chatgpt', 'perplexity', 'gemini', 'claude', 'grok', 'alice', 'gigachat', 'ai_other', 'social', 'friends', 'other' );

	/** The answers that are an AI assistant. */
	const AI = array( 'chatgpt', 'perplexity', 'gemini', 'claude', 'grok', 'alice', 'gigachat', 'ai_other' );

	/** Hook in, only while the feature is on. */
	public static function register() {
		if ( ! Inite_Agents_Settings::on( 'survey' ) ) {
			return;
		}
		add_shortcode( self::FIELD, array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_nopriv_inite_agents_survey', array( __CLASS__, 'submit' ) );
		add_action( 'admin_post_inite_agents_survey', array( __CLASS__, 'submit' ) );
		// WooCommerce, classic checkout.
		add_action( 'woocommerce_after_order_notes', array( __CLASS__, 'woo_field' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'woo_classic' ), 10, 0 );
		// WooCommerce, block checkout.
		add_action( 'woocommerce_init', array( __CLASS__, 'woo_block_field' ) );
		add_action( 'woocommerce_set_additional_field_value', array( __CLASS__, 'woo_block' ), 10, 2 );
		// Contact Form 7.
		add_action( 'wpcf7_init', array( __CLASS__, 'cf7_tag' ) );
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'cf7_sent' ) );
		// WPForms.
		add_action( 'wpforms_process_complete', array( __CLASS__, 'wpforms' ), 10, 3 );
	}

	/**
	 * The answers' words, in the site's language.
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			'google'     => __( 'Google search', 'inite-agents' ),
			'yandex'     => __( 'Yandex search', 'inite-agents' ),
			'chatgpt'    => __( 'ChatGPT', 'inite-agents' ),
			'perplexity' => __( 'Perplexity', 'inite-agents' ),
			'gemini'     => __( 'Gemini', 'inite-agents' ),
			'claude'     => __( 'Claude', 'inite-agents' ),
			'grok'       => __( 'Grok', 'inite-agents' ),
			'alice'      => __( 'Alice (Yandex)', 'inite-agents' ),
			'gigachat'   => __( 'GigaChat', 'inite-agents' ),
			'ai_other'   => __( 'Another AI assistant', 'inite-agents' ),
			'social'     => __( 'Social media', 'inite-agents' ),
			'friends'    => __( 'Friends or colleagues', 'inite-agents' ),
			'other'      => __( 'Other', 'inite-agents' ),
		);
	}

	/**
	 * The question, in the site's language.
	 *
	 * @return string
	 */
	public static function question() {
		return __( 'How did you hear about us?', 'inite-agents' );
	}

	/**
	 * An answer key from what a form sent: the key itself, or the answer's words.
	 *
	 * @param mixed $value Submitted value.
	 * @return string|null
	 */
	public static function answer( $value ) {
		$value = strtolower( trim( sanitize_text_field( (string) $value ) ) );
		if ( '' === $value ) {
			return null;
		}
		if ( in_array( $value, self::ANSWERS, true ) ) {
			return $value;
		}
		foreach ( self::labels() as $key => $label ) {
			if ( strtolower( $label ) === $value ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Count one answer for today.
	 *
	 * @param string $answer One of ANSWERS.
	 * @return bool Whether it was counted.
	 */
	public static function add( $answer ) {
		if ( ! in_array( $answer, self::ANSWERS, true ) ) {
			return false;
		}
		$day = gmdate( 'Y-m-d' );
		$all = self::all();
		if ( isset( $all[ $day ] ) && array_sum( $all[ $day ] ) >= self::DAILY ) {
			return false;
		}
		$all[ $day ][ $answer ] = ( isset( $all[ $day ][ $answer ] ) ? (int) $all[ $day ][ $answer ] : 0 ) + 1;
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $all, '', false );
		} else {
			update_option( self::OPTION, $all, false );
		}
		return true;
	}

	/**
	 * The kept counts, day => answer => n, the last DAYS days.
	 *
	 * @return array<string,array<string,int>>
	 */
	public static function all() {
		$all    = get_option( self::OPTION, array() );
		$all    = is_array( $all ) ? $all : array();
		$oldest = gmdate( 'Y-m-d', time() - ( self::DAYS - 1 ) * DAY_IN_SECONDS );
		foreach ( array_keys( $all ) as $day ) {
			if ( ! is_string( $day ) || $day < $oldest || ! is_array( $all[ $day ] ) ) {
				unset( $all[ $day ] );
			}
		}
		return $all;
	}

	/**
	 * The ping's rows: the last week, each the day's total so far.
	 *
	 * @return array<int,array{day:string,answer:string,hits:int}>
	 */
	public static function rows() {
		$oldest = gmdate( 'Y-m-d', time() - ( self::SENT_DAYS - 1 ) * DAY_IN_SECONDS );
		$rows   = array();
		foreach ( self::all() as $day => $answers ) {
			if ( $day < $oldest ) {
				continue;
			}
			foreach ( $answers as $answer => $hits ) {
				if ( in_array( $answer, self::ANSWERS, true ) ) {
					$rows[] = array(
						'day'    => (string) $day,
						'answer' => (string) $answer,
						'hits'   => (int) $hits,
					);
				}
			}
		}
		return array_slice( $rows, -60 );
	}

	/**
	 * Totals of the kept days: all answers, the AI ones, and by answer.
	 *
	 * @return array{total:int,ai:int,by_answer:array<string,int>}
	 */
	public static function totals() {
		$by    = array();
		$total = 0;
		$ai    = 0;
		foreach ( self::all() as $answers ) {
			foreach ( $answers as $answer => $hits ) {
				$total        += (int) $hits;
				$ai           += in_array( $answer, self::AI, true ) ? (int) $hits : 0;
				$by[ $answer ] = ( isset( $by[ $answer ] ) ? $by[ $answer ] : 0 ) + (int) $hits;
			}
		}
		arsort( $by );
		return array(
			'total'     => $total,
			'ai'        => $ai,
			'by_answer' => $by,
		);
	}

	/**
	 * The select, for a form of the owner's.
	 *
	 * @param string $id Element id.
	 * @return string HTML.
	 */
	public static function select( $id = 'inite-how-heard' ) {
		$out = '<label for="' . esc_attr( $id ) . '">' . esc_html( self::question() ) . ' <span class="optional">' . esc_html__( '(optional)', 'inite-agents' ) . '</span></label> <select id="' . esc_attr( $id ) . '" name="' . esc_attr( self::FIELD ) . '"><option value="">' . esc_html__( 'Choose an answer', 'inite-agents' ) . '</option>';
		foreach ( self::labels() as $key => $label ) {
			$out .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		return $out . '</select>';
	}

	/**
	 * [inite_how_heard]: a small form of its own, which thanks and returns to the page.
	 *
	 * @return string HTML.
	 */
	public static function shortcode() {
		if ( isset( $_GET['inite_heard'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks the thank-you line.
			return '<p class="inite-how-heard-thanks">' . esc_html__( 'Thank you!', 'inite-agents' ) . '</p>';
		}
		$back = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return '<form class="' . esc_attr( self::CSS ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="inite_agents_survey"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">' . wp_nonce_field( 'inite_agents_survey', '_inite_nonce', false, false ) . '<p>' . self::select( 'inite-how-heard-' . wp_rand( 1000, 9999 ) ) . ' <button type="submit">' . esc_html__( 'Send', 'inite-agents' ) . '</button></p></form>';
	}

	/** The shortcode's form sent: count it and return to the page. */
	public static function submit() {
		$ok   = isset( $_POST['_inite_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inite_nonce'] ) ), 'inite_agents_survey' );
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : '';
		if ( $ok && isset( $_POST[ self::FIELD ] ) ) {
			$answer = self::answer( wp_unslash( $_POST[ self::FIELD ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- answer() sanitizes and keeps only a known key.
			if ( $answer ) {
				self::add( $answer );
			}
		}
		$to = wp_validate_redirect( '' !== $back ? home_url( $back ) : home_url( '/' ), home_url( '/' ) );
		wp_safe_redirect( add_query_arg( 'inite_heard', '1', $to ) );
		exit;
	}

	/** WooCommerce classic checkout: the select after the order notes. */
	public static function woo_field() {
		echo '<p class="form-row form-row-wide ' . esc_attr( self::CSS ) . '">' . self::select() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- select() escapes every part.
	}

	/** WooCommerce classic checkout placed an order: count its answer (the checkout's own nonce was checked by WooCommerce). */
	public static function woo_classic() {
		if ( isset( $_POST[ self::FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce before this action.
			$answer = self::answer( wp_unslash( $_POST[ self::FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- as above; answer() sanitizes.
			if ( $answer ) {
				self::add( $answer );
			}
		}
	}

	/** WooCommerce block checkout: an optional field in the order step. */
	public static function woo_block_field() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		$options = array();
		foreach ( self::labels() as $key => $label ) {
			$options[] = array(
				'value' => $key,
				'label' => $label,
			);
		}
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => self::BLOCK_ID,
				'label'    => self::question(),
				'location' => 'order',
				'type'     => 'select',
				'required' => false,
				'options'  => $options,
			)
		);
	}

	/**
	 * WooCommerce saved the block checkout's field: count the answer.
	 *
	 * @param string $key   Field id.
	 * @param mixed  $value Value.
	 */
	public static function woo_block( $key, $value ) {
		if ( self::BLOCK_ID === $key ) {
			$answer = self::answer( $value );
			if ( $answer ) {
				self::add( $answer );
			}
		}
	}

	/** Contact Form 7: the [inite_how_heard] form tag. */
	public static function cf7_tag() {
		if ( function_exists( 'wpcf7_add_form_tag' ) ) {
			wpcf7_add_form_tag( self::FIELD, array( __CLASS__, 'cf7_render' ) );
		}
	}

	/**
	 * The form tag's markup.
	 *
	 * @return string HTML.
	 */
	public static function cf7_render() {
		return '<span class="wpcf7-form-control-wrap ' . esc_attr( self::CSS ) . '">' . self::select( 'inite-how-heard-cf7' ) . '</span>';
	}

	/**
	 * Contact Form 7 sent the mail: count the answer its submission carried.
	 *
	 * @param object $form The form.
	 */
	public static function cf7_sent( $form ) {
		unset( $form );
		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}
		$submission = WPCF7_Submission::get_instance();
		$data       = $submission ? $submission->get_posted_data() : array();
		if ( isset( $data[ self::FIELD ] ) ) {
			$answer = self::answer( is_array( $data[ self::FIELD ] ) ? reset( $data[ self::FIELD ] ) : $data[ self::FIELD ] );
			if ( $answer ) {
				self::add( $answer );
			}
		}
	}

	/**
	 * WPForms saved an entry: count the answer of the field with our CSS class.
	 *
	 * @param array $fields    The entry's fields.
	 * @param array $entry     The entry.
	 * @param array $form_data The form's settings.
	 */
	public static function wpforms( $fields, $entry, $form_data ) {
		unset( $entry );
		foreach ( isset( $form_data['fields'] ) && is_array( $form_data['fields'] ) ? $form_data['fields'] : array() as $id => $field ) {
			if ( isset( $field['css'] ) && false !== strpos( (string) $field['css'], self::CSS ) && isset( $fields[ $id ]['value'] ) ) {
				$answer = self::answer( $fields[ $id ]['value'] );
				if ( $answer ) {
					self::add( $answer );
				}
				return;
			}
		}
	}
}
