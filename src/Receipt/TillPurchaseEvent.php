<?php

namespace BeltoftPos\Receipt;

use BeltoftPos\Support\Till;

defined( 'ABSPATH' ) || exit;

/**
 * A till sale reaches analytics as its own event, never as a web purchase.
 *
 * The receipt is WooCommerce's order-received page, where GTM4WP pushes a
 * `purchase` event for every paid order - so each counter sale was reported to
 * GA4 and Google Ads as an online conversion, inflating web revenue and
 * training ad bidding on people who walked in.
 *
 * Renamed rather than dropped: the same ecommerce payload is pushed as
 * `pos_purchase`, tagged affiliation "POS" and sales_channel "pos". The
 * container's existing purchase triggers do not match it, so nothing
 * changes in GA4 or Ads until someone adds a tag for it in GTM - and then
 * shop sales can be reported, or filtered, on their own. Customer data for
 * enhanced conversions is left out: a walk-in is not an ad click.
 *
 * GTM4WP's duplicate guard keys on the order number, not the event name, so a
 * reprinted receipt does not push it twice. Reached through GTM4WP's own
 * filter only; nothing happens without it.
 */
class TillPurchaseEvent {

	public const EVENT = 'pos_purchase';

	public const AFFILIATION = 'POS';

	public static function register(): void {
		add_filter( 'gtm4wp_purchase_datalayer', array( __CLASS__, 'tag' ), 10, 2 );
	}

	/**
	 * @param mixed $data_layer
	 * @param mixed $order
	 * @return mixed
	 */
	public static function tag( $data_layer, $order = null ) {
		if ( ! is_array( $data_layer ) || ! Till::is_pos_sale( $order ) || empty( $data_layer['event'] ) ) {
			return $data_layer;
		}

		$data_layer['event']         = self::EVENT;
		$data_layer['sales_channel'] = 'pos';

		if ( isset( $data_layer['ecommerce'] ) && is_array( $data_layer['ecommerce'] ) ) {
			$data_layer['ecommerce']['affiliation'] = self::AFFILIATION;
		}

		unset( $data_layer['user_data'] );

		return $data_layer;
	}
}
