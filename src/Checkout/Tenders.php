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
		add_filter( 'woocommerce_payment_complete_order_status', array( __CLASS__, 'completed' ), 10, 3 );
	}

	/**
	 * A till sale is finished when it is paid: the customer has left with the
	 * goods. Left in processing it sat in the web shop's packing queue,
	 * already paid, where it could be packed and shipped a second time.
	 *
	 * @param mixed $status
	 * @param mixed $order_id
	 * @param mixed $order
	 * @return mixed
	 */
	public static function completed( $status, $order_id = 0, $order = null ) {
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );

		return \BeltoftPos\Support\Till::is_pos_sale( $order ) ? 'completed' : $status;
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
