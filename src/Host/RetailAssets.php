<?php

namespace BeltoftPos\Host;

use BeltoftPos\Support\Host;

defined( 'ABSPATH' ) || exit;

/**
 * The shop's header scripts stay off the till - as on the B2B host.
 *
 * The till renders its own header (Bricks\Chrome), so scripts written for the
 * shop's header have nothing to attach to. The theme's mega-menu script threw
 * "Cannot read properties of null" on every till page; the wishlist (removed
 * on the B2B host for the same reason) has no buttons here either.
 */
class RetailAssets {

	private const HANDLES = array(
		'custom-menu-scripts',
		'woosw-frontend',
		'woosw-icons',
		'notiny',
		'perfect-scrollbar',
		'perfect-scrollbar-wpc',
	);

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'drop' ), 99 );
	}

	public static function drop(): void {
		if ( ! Host::is_pos_host() ) {
			return;
		}

		foreach ( self::HANDLES as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
	}
}
