<?php

namespace BeltoftPos\Catalog;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * A quantity field next to add-to-cart in the till's product list, as on the
 * B2B list.
 *
 * WHY THIS IS NOT A BRICKS SETTING. Bricks' product-add-to-cart element asks
 * whether it is inside a query loop and renders accordingly
 * (`includes/woocommerce/elements/product-add-to-cart.php:1482`): looping calls
 * woocommerce_template_loop_add_to_cart(), which is a link carrying
 * data-quantity="1", and only the non-looping branch calls
 * woocommerce_template_single_add_to_cart(), which is the form with a quantity
 * input. There is no control to switch that, so the field has to come from the
 * WooCommerce side. Everything else on the ordering page is a native Bricks
 * element.
 *
 * WHY IT IS GATED ON THE HOST. Retail renders the same loop elements, and
 * must not change. Gating on the host keeps this off every retail render
 * rather than relying on a page check that a future template could bypass.
 *
 * A variable product never reaches a row (VariationRows lists its variations
 * instead); a variation's row gets its own control that adds that variation.
 */
class LoopQuantity {

	public static function register(): void {
		add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'prepend_quantity' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	private static function applies(): bool {
		return Host::is_pos_host();
	}

	/**
	 * @param string      $html    The add-to-cart markup WooCommerce built.
	 * @param \WC_Product $product The looped product.
	 * @return string
	 */
	public static function prepend_quantity( $html, $product = null ): string {
		if ( ! self::applies() || ! $product instanceof \WC_Product ) {
			return $html;
		}

		/*
		 * Out of stock still gets the full control, just inert. Collapsing the
		 * row to a bare link made every sold-out line a different shape, so the
		 * column stopped scanning as a column and the eye caught on the gap
		 * rather than on the word that matters.
		 */
		if ( ! $product->is_in_stock() ) {
			return self::sold_out( $product );
		}

		/*
		 * A variable product's loop button is a link to the product page,
		 * because WooCommerce cannot know which variation is wanted. For a
		 * reorder list that is a dead end: the buyer leaves the list, chooses,
		 * comes back and finds their place again, twice per line. Instead the
		 * variations are offered inline and the chosen one is added directly.
		 *
		 * CHECKED BEFORE is_purchasable(), and that order is the whole reason
		 * this works. WC_Product_Variable::is_purchasable() asks about the
		 * PARENT, which under group pricing has no price of its own, so every
		 * variable product answers false and would be skipped here. Whether a
		 * variable product can be bought is a question about its children -
		 * which is exactly what CatalogAudience::can_purchase() does too, and
		 * what variable_controls() re-checks per variation.
		 */
		/*
		 * A variable PARENT only still has a row when Frontend\VariationRows
		 * found nothing in it this buyer may order - it could not expand the
		 * parent into variation rows. Woo's own "choose an option" link is the
		 * honest control there, so leave it alone.
		 */
		if ( $product->is_type( 'variable' ) ) {
			return $html;
		}

		if ( ! $product->is_purchasable() ) {
			return $html;
		}

		/*
		 * A variation row is a product row like any other, except that Woo's
		 * loop link for a variation points at the parent page. Swap in the same
		 * stepper plus a button that posts the VARIATION id, which is what
		 * WC_AJAX::add_to_cart actually wants (class-wc-ajax.php:525).
		 */
		if ( $product->is_type( 'variation' ) ) {
			return self::line_control( $product );
		}

		if ( ! $product->is_type( 'simple' ) ) {
			return $html;
		}

		$min  = 1;
		$step = 1;
		$max  = $product->managing_stock() && ! $product->backorders_allowed()
			? (int) $product->get_stock_quantity()
			: '';

		/*
		 * A stepper, not a bare number field. The operator adjusts a line
		 * two or three times while counting what is on the counter; a
		 * spin control the browser draws at four pixels wide is not a target
		 * anyone hits on the first try, and on touch it does not exist at all.
		 * The input stays a real number input, so typing 24 is still faster
		 * than pressing + twenty-four times.
		 */
		$field = sprintf(
			'<div class="beltoft-loop-qty">' .
			'<label class="screen-reader-text" for="beltoft-qty-%1$d">%2$s</label>' .
			'<button type="button" class="beltoft-loop-qty__step" data-step="-1" aria-label="%6$s" tabindex="-1">&minus;</button>' .
			'<input type="number" id="beltoft-qty-%1$d" class="beltoft-loop-qty__input" value="%3$d" min="%3$d" step="%4$d"%5$s inputmode="numeric">' .
			'<button type="button" class="beltoft-loop-qty__step" data-step="1" aria-label="%7$s" tabindex="-1">+</button>' .
			'</div>',
			(int) $product->get_id(),
			esc_attr__( 'Quantity', 'beltoft-pos' ),
			$min,
			$step,
			'' !== $max ? ' max="' . (int) $max . '"' : '',
			esc_attr__( 'Decrease quantity', 'beltoft-pos' ),
			esc_attr__( 'Increase quantity', 'beltoft-pos' )
		);

		return $field . $html;
	}

	/**
	 * Select a variation, set a quantity, add it - without leaving the row.
	 *
	 * ONLY VARIATIONS THE VIEWER CAN ACTUALLY BUY are listed. `is_purchasable()`
	 * on a variation runs the same fail-closed group-price rule as everything
	 * else, so a variation priced for retail and not for this buyer's group
	 * never appears in their dropdown - the list cannot offer something the
	 * cart would then refuse.
	 *
	 * A single purchasable variation renders no dropdown at all: choosing from
	 * a list of one is a question with no information in it.
	 */
	/**
	 * The same shape as an orderable line, with nothing to operate.
	 *
	 * `aria-disabled` and `tabindex="-1"` rather than a `disabled` attribute:
	 * it is an anchor, so `disabled` means nothing to it, and a screen reader
	 * should still be able to read what the line says.
	 */
	private static function sold_out( \WC_Product $product ): string {
		return sprintf(
			'<div class="beltoft-loop-variable beltoft-loop-variable--sold-out">' .
			'<div class="beltoft-loop-qty" aria-hidden="true">' .
			'<span class="beltoft-loop-qty__step" aria-hidden="true">&minus;</span>' .
			'<input type="number" class="beltoft-loop-qty__input" value="1" disabled>' .
			'<span class="beltoft-loop-qty__step" aria-hidden="true">+</span>' .
			'</div>' .
			'<span class="button beltoft-loop-variable__add beltoft-loop-variable__add--sold-out" aria-disabled="true" tabindex="-1">%s</span>' .
			'</div>',
			esc_html__( 'Sold out', 'beltoft-pos' )
		);
	}

	/**
	 * Stepper plus add button for one orderable product, posting its own id.
	 *
	 * An <a> carrying data-product_id, not a <button>. Bricks styles loop
	 * add-to-cart buttons through `a.button[data-product_id]`, so a <button>
	 * matched none of it and variation rows rendered an unstyled control next
	 * to properly themed ones. The classes Woo's and Bricks' own scripts bind
	 * to - add_to_cart_button, ajax_add_to_cart, brx_ajax_add_to_cart - are
	 * deliberately NOT used: this control is driven by the handler in
	 * print_script(), and carrying them would have two scripts adding the same
	 * line twice.
	 */
	private static function line_control( \WC_Product $product ): string {
		return sprintf(
			'<div class="beltoft-loop-variable" data-product="%1$d">' .
			'<input type="hidden" class="beltoft-loop-variation" value="%1$d">' .
			'%2$s<a href="%3$s" class="button beltoft-loop-variable__add" data-product_id="%1$d" data-quantity="1" rel="nofollow">%4$s</a></div>',
			(int) $product->get_id(),
			self::quantity_field( $product ),
			esc_url( $product->add_to_cart_url() ),
			// Woo's own label, so a variation row and a simple row do not
			// offer the same action under two different words.
			esc_html( $product->add_to_cart_text() )
		);
	}

	/**
	 * The list's ordering control for one product outside the list - the quick
	 * view - as the {beltoft_buy} dynamic tag.
	 *
	 * Bricks' add-to-cart element renders the single-product form there, which
	 * is a different design and, for a variation, nothing at all (Woo has no
	 * `woocommerce_variation_add_to_cart` template). The quick view is opened
	 * from a list row, so it offers exactly the control that row has: the same
	 * stepper, the same button, the same stock cap and the same handler, which
	 * works for a simple product as well as a variation because
	 * WC_AJAX::add_to_cart takes either id. A variable parent gets nothing - it
	 * has no single thing to add.
	 */
	public static function buy_control( \WC_Product $product ): string {
		if ( ! self::applies() || $product->is_type( 'variable' ) ) {
			return '';
		}

		if ( ! $product->is_in_stock() ) {
			return self::sold_out( $product );
		}

		if ( ! $product->is_purchasable() || ! $product->is_type( array( 'simple', 'variation' ) ) ) {
			return '';
		}

		return self::line_control( $product );
	}

	/**
	 * The stepper markup, shared by the simple and variation paths so the two
	 * cannot drift into looking like different controls.
	 */
	private static function quantity_field( \WC_Product $product ): string {
		// Stock is a property of the VARIATION, not the parent, so a variable
		// product's per-line cap has to come from the line's own product.
		$max = $product->managing_stock() && ! $product->backorders_allowed()
			? (int) $product->get_stock_quantity()
			: '';

		return sprintf(
			'<div class="beltoft-loop-qty">' .
			'<label class="screen-reader-text" for="beltoft-qty-%1$d">%2$s</label>' .
			'<button type="button" class="beltoft-loop-qty__step" data-step="-1" aria-label="%4$s" tabindex="-1">&minus;</button>' .
			'<input type="number" id="beltoft-qty-%1$d" class="beltoft-loop-qty__input" value="1" min="1" step="1"%3$s inputmode="numeric">' .
			'<button type="button" class="beltoft-loop-qty__step" data-step="1" aria-label="%5$s" tabindex="-1">+</button>' .
			'</div>',
			(int) $product->get_id(),
			esc_attr__( 'Quantity', 'beltoft-pos' ),
			'' !== $max ? ' max="' . (int) $max . '"' : '',
			esc_attr__( 'Decrease quantity', 'beltoft-pos' ),
			esc_attr__( 'Increase quantity', 'beltoft-pos' )
		);
	}

	/**
	 * Carry the typed quantity into the button WooCommerce already wired up.
	 *
	 * WooCommerce's AJAX add-to-cart reads data-quantity off the button, so the
	 * whole job is to keep that attribute in step with the input. Doing it on
	 * input rather than on click means the value is already correct whichever
	 * way the button is activated - mouse, keyboard or an Enter press that
	 * follows the browser's own form handling.
	 */

	/**
	 * The control's styles and script, on the till page only. Kept as files
	 * rather than printed inline: the till's front end is Bricks, and these
	 * style markup that Bricks cannot produce (a quantity inside a loop).
	 */
	public static function enqueue(): void {
		$page = Options::page_id();

		if ( ! $page || ! self::applies() || ! is_page( $page ) ) {
			return;
		}

		wp_enqueue_style( 'beltoft-pos-list', plugins_url( 'assets/css/list.css', BELTOFT_POS_FILE ), array(), BELTOFT_POS_VERSION );
		wp_enqueue_script( 'beltoft-pos-list', plugins_url( 'assets/js/list.js', BELTOFT_POS_FILE ), array(), BELTOFT_POS_VERSION, true );
		wp_localize_script(
			'beltoft-pos-list',
			'beltoftPosList',
			array(
				'i18n' => array(
					/* translators: %d: units in stock. */
					'onlyInStock'    => __( 'Only %d in stock.', 'beltoft-pos' ),
					/* translators: %d: units in stock. */
					'couldNotAddCap' => __( 'Could not add. Only %d in stock.', 'beltoft-pos' ),
					'couldNotAdd'    => __( 'Could not add this line.', 'beltoft-pos' ),
				),
			)
		);
	}
}
