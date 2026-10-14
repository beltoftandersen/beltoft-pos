<?php

namespace BeltoftPos\Checkout;

use BeltoftPos\Support\Till;

defined( 'ABSPATH' ) || exit;

/**
 * The till offers its tenders, and nothing else offers them.
 *
 * Two duties, and the second is the one that would be forgotten: a tender is a
 * registered payment gateway, so without this it would appear on the retail
 * checkout as a way for a customer at home to declare they had paid cash.
 *
 * Runs late, and composes the till's list from the gateway registry rather
 * than filtering whatever was handed over, so the outcome does not depend on
 * what any other callback did first.
 *
 * Fails closed. If no tender is available the till is told there is no
 * payment method, which is visible and fixable; the alternative is quietly
 * letting a counter sale go through as a card payment on the website's real
 * gateway.
 */
class PaymentMethods {

	private const PRIORITY = 10000;

	public static function register(): void {
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter' ), self::PRIORITY );
	}

	/**
	 * @param mixed $available
	 * @return mixed
	 */
	public static function filter( $available ) {
		if ( ! is_array( $available ) ) {
			return $available;
		}

		if ( ! Till::is_session() ) {
			// Everywhere else: a tender is not a payment method.
			foreach ( array_keys( $available ) as $id ) {
				if ( Tenders::is_tender( (string) $id ) ) {
					unset( $available[ $id ] );
				}
			}

			return $available;
		}

		$gateways = function_exists( 'WC' ) && WC()->payment_gateways()
			? WC()->payment_gateways()->payment_gateways()
			: array();

		$tenders = array();

		foreach ( Tenders::ids() as $id ) {
			if ( isset( $gateways[ $id ] ) && $gateways[ $id ]->is_available() ) {
				$tenders[ $id ] = $gateways[ $id ];
			}
		}

		return $tenders;
	}
}
