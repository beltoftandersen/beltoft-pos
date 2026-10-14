<?php

namespace BeltoftPos\Catalog;

use BeltoftPos\Support\Host;

defined( 'ABSPATH' ) || exit;

/**
 * The line just added appears at the top of the side panel - as on the B2B list.
 *
 * WooCommerce appends to the cart, which is the right storage order and the
 * wrong reading order here: an appended line lands out of sight at the bottom
 * of the panel, and the operator gets no sign the tap did anything. Reversed
 * at the Bricks loop, not in the cart, so nothing else reading the cart sees
 * a different order.
 */
class CartNewestFirst {

	private const PRIORITY = 60;

	public static function register(): void {
		add_filter( 'bricks/query/result', array( __CLASS__, 'newest_first' ), self::PRIORITY, 2 );
	}

	/**
	 * @param mixed $result
	 * @param mixed $query
	 * @return mixed
	 */
	public static function newest_first( $result, $query = null ) {
		if ( ! Host::is_pos_host() || ! is_array( $result ) || ! $query || 'wooCart' !== ( $query->object_type ?? '' ) ) {
			return $result;
		}

		return array_reverse( $result, true );
	}
}
