<?php

namespace BeltoftPos\Support;

use WC_Order;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Whether this is the till, and whether an order was sold at it.
 *
 * A till session is a request on the POS host by a signed-in till user. The
 * gate already keeps everyone else out; asking for the role here as well
 * means a hole in the gate cannot turn a customer's request into a till sale.
 *
 * Orders are judged by their marker, never by the session: stock is reduced
 * on payment, on an admin status change or from cron, when there is no till
 * session to ask.
 */
class Till {

	public const SALE_META = '_beltoft_pos_sale';

	public static function is_session(): bool {
		return Host::is_pos_host() && self::is_till_user( wp_get_current_user() );
	}

	/** @param mixed $user */
	public static function is_till_user( $user ): bool {
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		return in_array( Installer::ROLE, (array) $user->roles, true ) || user_can( $user, 'manage_woocommerce' );
	}

	/** @param mixed $order */
	public static function is_pos_sale( $order ): bool {
		return $order instanceof WC_Order && '1' === (string) $order->get_meta( self::SALE_META );
	}

	/** Sets the marker; the caller saves. */
	public static function mark_sale( WC_Order $order ): void {
		$order->update_meta_data( self::SALE_META, '1' );
	}
}
