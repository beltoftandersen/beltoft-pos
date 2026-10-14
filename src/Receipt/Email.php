<?php

namespace BeltoftPos\Receipt;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;
use BeltoftPos\Support\Till;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * "Enviar por email" on the receipt.
 *
 * The receipt screen carries a Bricks form (action: Custom) with two fields
 * named pos_receipt_order (hidden, {woo_order_id}) and pos_receipt_email.
 * Bricks checks the form's nonce before custom actions run; this checks the
 * rest: a till user, a till sale, a real address.
 *
 * The email is WooCommerce's own customer invoice, so it looks like every
 * other order email. It goes to the address typed. That address is kept on
 * the order only when the order had none of its own - the walk-in address -
 * so a customer who gave their email at checkout keeps it.
 */
class Email {

	public const ORDER_FIELD = 'pos_receipt_order';

	public const EMAIL_FIELD = 'pos_receipt_email';

	public static function register(): void {
		add_action( 'bricks/form/custom_action', array( __CLASS__, 'handle' ) );
	}

	/** @param mixed $form Bricks\Integrations\Form\Init */
	public static function handle( $form ): void {
		$order_id = self::field( $form, self::ORDER_FIELD );

		if ( '' === $order_id ) {
			return; // Not the receipt form.
		}

		$email  = self::field( $form, self::EMAIL_FIELD );
		$result = self::send( (int) $order_id, $email, wp_get_current_user() );

		$form->set_result( array(
			'action'  => 'beltoft_pos_receipt',
			'type'    => true === $result ? 'success' : 'error',
			'message' => esc_html(
				true === $result
					/* translators: %s: email address. */
					? sprintf( __( 'Receipt sent to %s.', 'beltoft-pos' ), sanitize_email( $email ) )
					: $result->get_error_message()
			),
		) );
	}

	/**
	 * @param mixed $user
	 * @return true|WP_Error
	 */
	public static function send( int $order_id, string $email, $user ) {
		// From the till only - the form exists nowhere else.
		if ( ! Host::is_pos_host() || ! Till::is_till_user( $user ) ) {
			return new WP_Error( 'beltoft_pos_forbidden', __( 'This account has no access to the till.', 'beltoft-pos' ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! Till::is_pos_sale( $order ) ) {
			return new WP_Error( 'beltoft_pos_not_a_sale', __( 'This order is not a till sale.', 'beltoft-pos' ) );
		}

		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'beltoft_pos_invalid_email', __( 'Enter a valid email address.', 'beltoft-pos' ) );
		}

		$current = strtolower( (string) $order->get_billing_email() );

		if ( '' === $current || strtolower( Options::walkin_email() ) === $current ) {
			$order->set_billing_email( $email );
		}

		/* translators: %s: email address. */
		$order->add_order_note( sprintf( __( 'Receipt emailed to %s.', 'beltoft-pos' ), $email ) );
		$order->save();

		$mails = WC()->mailer()->get_emails();

		if ( empty( $mails['WC_Email_Customer_Invoice'] ) ) {
			return new WP_Error( 'beltoft_pos_send_failed', __( 'The receipt could not be sent.', 'beltoft-pos' ) );
		}

		$to = static fn() => $email;

		add_filter( 'woocommerce_email_recipient_customer_invoice', $to, 999 );
		$mails['WC_Email_Customer_Invoice']->trigger( $order->get_id(), $order );
		remove_filter( 'woocommerce_email_recipient_customer_invoice', $to, 999 );

		return true;
	}

	/**
	 * A submitted value by the field's name.
	 *
	 * Bricks posts fields as form-field-{id} and only maps a custom name onto
	 * that key, so the name is looked up in the form's settings first.
	 *
	 * @param mixed $form
	 */
	public static function field( $form, string $name ): string {
		$values   = (array) $form->get_fields();
		$settings = (array) $form->get_settings();

		foreach ( (array) ( $settings['fields'] ?? array() ) as $field ) {
			if ( $name === ( $field['name'] ?? '' ) && isset( $values[ 'form-field-' . ( $field['id'] ?? '' ) ] ) ) {
				return trim( (string) $values[ 'form-field-' . $field['id'] ] );
			}
		}

		return isset( $values[ $name ] ) ? trim( (string) $values[ $name ] ) : '';
	}
}
