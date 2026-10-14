<?php

namespace BeltoftPos\Catalog;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * A variation's row adds that variation, in place.
 *
 * The till lists each variation as its own row, so the operator taps the
 * size or colour they are holding - there is no variation picker at a
 * counter. WooCommerce renders a variation's loop button as a plain link to
 * the product page, because it does not count variations as AJAX-addable;
 * its add-to-cart AJAX handler does accept a variation id, though, so the
 * button is marked for AJAX like a simple product's. The link behind it -
 * used without JavaScript - adds on the till itself rather than leaving it.
 *
 * On the POS host only.
 */
class AddToCart {

	public static function register(): void {
		add_filter( 'woocommerce_loop_add_to_cart_args', array( __CLASS__, 'loop_args' ), 20, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'url' ), 20, 2 );
	}

	/**
	 * @param mixed $args
	 * @param mixed $product
	 * @return mixed
	 */
	public static function loop_args( $args, $product = null ) {
		if ( ! is_array( $args ) || ! self::applies( $product ) ) {
			return $args;
		}

		$class = (string) ( $args['class'] ?? '' );

		if ( false === strpos( $class, 'ajax_add_to_cart' ) ) {
			$args['class'] = trim( $class . ' ajax_add_to_cart' );
		}

		return $args;
	}

	/**
	 * @param mixed $url
	 * @param mixed $product
	 * @return mixed
	 */
	public static function url( $url, $product = null ) {
		$page = Options::page_id();

		if ( ! $page || ! self::applies( $product ) ) {
			return $url;
		}

		return add_query_arg( 'add-to-cart', $product->get_id(), get_permalink( $page ) );
	}

	/** @param mixed $product */
	private static function applies( $product ): bool {
		return $product instanceof WC_Product && 'variation' === $product->get_type() && Host::is_pos_host();
	}
}
