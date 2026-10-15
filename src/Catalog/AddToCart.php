<?php

namespace BeltoftPos\Catalog;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * A variation's add-to-cart link stays on the till.
 *
 * The till lists each variation as its own row (VariationRows), and the row's
 * control (LoopQuantity) adds it by AJAX. The link behind that control - used
 * before or without JavaScript - is WooCommerce's, which for a variation is
 * the parent's product page; here it adds on the till itself instead.
 *
 * On the POS host only.
 */
class AddToCart {

	public static function register(): void {
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'url' ), 20, 2 );
	}

	/**
	 * @param mixed $url
	 * @param mixed $product
	 * @return mixed
	 */
	public static function url( $url, $product = null ) {
		if ( ! self::applies( $product ) ) {
			return $url;
		}

		$page = Options::page_id();

		if ( ! $page ) {
			return $url;
		}

		return add_query_arg( 'add-to-cart', $product->get_id(), get_permalink( $page ) );
	}

	/** @param mixed $product */
	private static function applies( $product ): bool {
		return $product instanceof WC_Product && 'variation' === $product->get_type() && Host::is_pos_host();
	}
}
