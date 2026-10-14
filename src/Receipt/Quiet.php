<?php

namespace BeltoftPos\Receipt;

use BeltoftPos\Support\Till;
use WC_Email;

defined( 'ABSPATH' ) || exit;

/**
 * No automatic customer emails for a till sale.
 *
 * The customer is standing at the counter; the receipt is printed or sent on
 * request ("Enviar por email", WooCommerce's customer invoice). Left alone,
 * WooCommerce sent its order emails for every sale - to the walk-in address,
 * the shop's own inbox, when nobody gave one, hundreds a day - and a
 * customer who did give an address got the order email as well as the
 * receipt they asked for.
 *
 * Every customer email is switched off for till sales except the ones a
 * person sends on purpose. Retail and B2B orders are untouched: the switch
 * reads the order's till marker.
 */
class Quiet {

	/**
	 * Left alone: sent by hand, not about an order, or the goods themselves -
	 * a gift card sold at the counter still has to reach its recipient.
	 */
	private const KEEP = array( 'customer_invoice', 'customer_note', 'customer_reset_password', 'customer_new_account', 'bgcw_gift_card_delivery' );

	public static function register(): void {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'hush' ), 999 );
	}

	/**
	 * @param mixed $emails
	 * @return mixed
	 */
	public static function hush( $emails ) {
		foreach ( (array) $emails as $email ) {
			if ( $email instanceof WC_Email && $email->is_customer_email() && ! in_array( $email->id, self::KEEP, true ) ) {
				add_filter( 'woocommerce_email_enabled_' . $email->id, array( __CLASS__, 'enabled' ), 999, 2 );
			}
		}

		return $emails;
	}

	/**
	 * @param mixed $enabled
	 * @param mixed $object The email's order, for order emails.
	 * @return mixed
	 */
	public static function enabled( $enabled, $object = null ) {
		return Till::is_pos_sale( $object ) ? false : $enabled;
	}
}
