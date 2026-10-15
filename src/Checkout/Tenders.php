<?php

namespace BeltoftPos\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * The tenders the till can record.
 *
 * Order matters: this is the order they appear in at the counter, commonest
 * first, because the operator is picking one per sale with a queue waiting.
 */
class Tenders {

	public const PREFIX = 'pos_';

	public static function all(): array {
		return (array) apply_filters( 'beltoft_pos_tenders', array(
			self::PREFIX . 'mbway' => __( 'MB WAY', 'beltoft-pos' ),
			self::PREFIX . 'card'  => __( 'Card (Teya)', 'beltoft-pos' ),
			self::PREFIX . 'cash'  => __( 'Cash', 'beltoft-pos' ),
			self::PREFIX . 'bank'  => __( 'Bank transfer', 'beltoft-pos' ),
		) );
	}

	public static function ids(): array {
		return array_keys( self::all() );
	}

	public static function is_tender( string $id ): bool {
		return array_key_exists( $id, self::all() );
	}

	public static function register(): void {
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add' ) );
	}

	/**
	 * @param mixed $gateways
	 * @return mixed
	 */
	public static function add( $gateways ) {
		if ( ! is_array( $gateways ) ) {
			return $gateways;
		}

		foreach ( self::all() as $id => $title ) {
			// Instances, not class names - see the note in Tender.
			$gateways[] = new Tender( $id, $title );
		}

		return $gateways;
	}
}
