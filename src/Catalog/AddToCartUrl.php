<?php

namespace BeltoftPos\Catalog;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Repair the add-to-cart link on rows that were rendered inside an AJAX or
 * REST request - as on the B2B list.
 *
 * WC_Product_Simple::add_to_cart_url() builds its link with add_query_arg()
 * and no base, which means "the URL of the current request". The till's list
 * is re-rendered server side inside Bricks' REST endpoint on every filter,
 * search and page change, so every button in the returned rows pointed at
 * /wp-json/bricks/v1/query_result?add-to-cart=... - a JSON 404. WooCommerce's
 * script normally intercepts the click, but a tap before it has loaded follows
 * the href, and that path has to work on its own.
 *
 * The page the rows are shown on is the one Bricks reports as baseUrl; failing
 * that, the till page.
 */
class AddToCartUrl {

	private static ?string $base = null;

	public static function register(): void {
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'repair' ), 10, 2 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'capture' ), 10, 3 );
	}

	/**
	 * @param mixed $result
	 * @param mixed $server
	 * @param mixed $request
	 * @return mixed
	 */
	public static function capture( $result, $server = null, $request = null ) {
		if ( $request instanceof WP_REST_Request ) {
			$raw        = $request->get_param( 'baseUrl' );
			self::$base = is_string( $raw ) && '' !== $raw ? $raw : null;
		}

		return $result;
	}

	/**
	 * @param mixed $url
	 * @param mixed $product
	 * @return mixed
	 */
	public static function repair( $url, $product = null ) {
		if ( ! is_string( $url ) || '' === $url || ! self::applies() ) {
			return $url;
		}

		return self::rebase( $url, self::display_page() );
	}

	/** The cart parameters of $url, on $page instead. Pure, for tests. */
	public static function rebase( string $url, string $page ): string {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$args  = array();
		wp_parse_str( $query, $args );

		if ( ! isset( $args['add-to-cart'] ) ) {
			return $url;
		}

		$keep = array_intersect_key( $args, array_flip( array( 'add-to-cart', 'quantity', 'variation_id' ) ) );

		return add_query_arg( $keep, $page );
	}

	private static function display_page(): string {
		$path = null === self::$base ? '' : (string) wp_parse_url( self::$base, PHP_URL_PATH );

		if ( '' === $path || '/' !== $path[0] || false !== strpos( $path, '..' ) || 0 === strpos( ltrim( $path, '/' ), rest_get_url_prefix() . '/' ) ) {
			$page = Options::page_id();

			return $page ? (string) get_permalink( $page ) : home_url( '/' );
		}

		return home_url( $path );
	}

	private static function applies(): bool {
		return Host::is_pos_host()
			&& ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() );
	}
}
