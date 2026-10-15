<?php

namespace BeltoftPos\Checkout;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * On the POS host, the POS page is WooCommerce's checkout, and '/' leads to it.
 *
 * NOT THE FRONT PAGE. WooCommerce builds its endpoint rewrite rules from the
 * stored settings - retail's - so they exist under pages only; with the till
 * as the front page the receipt (/encomenda-recebida/<id>/) would 404. The
 * till stays at its own path and the root redirects there.
 *
 * WHY THE PAGE ID IS FILTERED rather than the URL. `wc_get_page_id()` runs its
 * option through `woocommerce_get_{page}_page_id`, and everything downstream
 * asks that one function: `is_checkout()`, `is_cart()`, `wc_get_checkout_url()`,
 * the order-received endpoint, notices, and Bricks' own checkout and cart
 * detection. Swapping the id makes the POS page a REAL checkout to all of them,
 * so Bricks' native cart and checkout elements work on it unchanged.
 *
 * NOT THE CART PAGE, although the counter shows the cart. The retail cart
 * page is redirected away on every non-B2B host (mu-plugin
 * beltoft-offcanvas-cart), so the till would bounce off itself. Instead every
 * cart link on the POS host points at the till, and WooCommerce's redirect of
 * an empty checkout to the cart is switched off there - an empty till is
 * where a sale starts.
 */
class Page {

	public static function register(): void {
		add_filter( 'woocommerce_get_checkout_page_id', array( __CLASS__, 'page_id' ) );
		add_filter( 'woocommerce_get_cart_url', array( __CLASS__, 'cart_url' ) );
		add_filter( 'woocommerce_checkout_redirect_empty_cart', array( __CLASS__, 'keep_empty_checkout' ) );
		add_filter( 'woocommerce_checkout_update_order_review_expired', array( __CLASS__, 'keep_empty_review' ) );
		add_action( 'template_redirect', array( __CLASS__, 'not_configured' ), 2 );
		add_action( 'template_redirect', array( __CLASS__, 'to_the_till' ), 3 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	/**
	 * @param mixed $id
	 * @return mixed
	 */
	public static function page_id( $id ) {
		if ( ! Host::is_pos_host() ) {
			return $id;
		}

		return Options::page_id() ?: $id;
	}

	/**
	 * @param mixed $url
	 * @return mixed
	 */
	public static function cart_url( $url ) {
		if ( ! Host::is_pos_host() ) {
			return $url;
		}

		$page = Options::page_id();

		return $page ? get_permalink( $page ) : $url;
	}

	/**
	 * @param mixed $redirect
	 * @return mixed
	 */
	public static function keep_empty_checkout( $redirect ) {
		return Host::is_pos_host() ? false : $redirect;
	}

	/**
	 * An order review on an empty cart is not an expired session at the till.
	 * WooCommerce would replace the whole checkout form - the side panel -
	 * with "your session has expired" on page load, before anything is added.
	 *
	 * @param mixed $expired
	 * @return mixed
	 */
	public static function keep_empty_review( $expired ) {
		return Host::is_pos_host() ? false : $expired;
	}

	/** Where a request for this path goes instead: the till, for the POS host's root. */
	public static function front_target( string $path ): ?string {
		if ( ! Host::is_pos_host() ) {
			return null;
		}

		$page = Options::page_id();

		if ( ! $page || '' !== trim( $path, '/' ) ) {
			return null;
		}

		return get_permalink( $page );
	}

	public static function to_the_till(): void {
		$target = self::front_target( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ) );

		if ( null !== $target ) {
			wp_safe_redirect( $target, 302 );
			exit;
		}
	}

	/** Signed in on the till with no till page: say so, plainly. */
	public static function not_configured(): void {
		if ( ! Host::is_pos_host() || Options::page_id() || ! is_user_logged_in() ) {
			return;
		}

		wp_die( esc_html__( 'Till not set up.', 'beltoft-pos' ), '', array( 'response' => 503 ) );
	}

	public static function admin_notice(): void {
		// Cheap checks first: this runs on every admin screen.
		if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) || Options::page_id() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'The till has no page yet. Publish the POS page and set its ID in the option beltoft_pos_page_id.', 'beltoft-pos' )
		);
	}
}
