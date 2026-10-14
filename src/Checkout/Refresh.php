<?php

namespace BeltoftPos\Checkout;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the side-panel checkout in step with the cart.
 *
 * WooCommerce refreshes the order review when a checkout field changes, not
 * when the cart does - and on the till the cart changes beside the checkout
 * all the time: an add-to-cart in the list, a quantity in the panel, a scan.
 * The script asks WooCommerce to refresh it after each. Behaviour only; no
 * markup and no styling.
 */
class Refresh {

	public const HANDLE = 'beltoft-pos-refresh';

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue(): void {
		$page = Options::page_id();

		if ( ! $page || ! Host::is_pos_host() || ! is_page( $page ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'assets/js/refresh.js', BELTOFT_POS_FILE ),
			array( 'jquery' ),
			BELTOFT_POS_VERSION,
			true
		);
	}
}
