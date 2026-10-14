<?php

namespace BeltoftPos\Stock;

defined( 'ABSPATH' ) || exit;

/**
 * Where somebody types what is actually on the till's shelf.
 *
 * One field on the Inventory tab beside WooCommerce's own quantity, and the
 * same on each variation. Read and written as raw meta, never through the
 * product getters: an administrator on the till host would otherwise see,
 * and save, the shelf count in WooCommerce's own quantity box.
 *
 * EMPTY IS NOT ZERO. Empty means "never counted"; the till still sells it and
 * never invents a count for it. A number is a count, and may go below zero.
 */
class AdminField {

	public const FIELD = 'beltoft_stock_pos';

	/*
	 * Its own name, not FIELD[n]: once the Variations tab has been opened its
	 * fields are posted with the product form too, and FIELD[n] beside FIELD
	 * turned the parent's value into an array.
	 */
	public const VARIATION_FIELD = 'beltoft_stock_pos_variation';

	public static function register(): void {
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'simple' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_simple' ) );
		add_action( 'woocommerce_variation_options_inventory', array( __CLASS__, 'variation' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );
	}

	public static function simple(): void {
		global $post;

		woocommerce_wp_text_input( array(
			'id'                => self::FIELD,
			'value'             => (string) get_post_meta( (int) $post->ID, Pool::META, true ),
			'label'             => __( 'Till stock', 'beltoft-pos' ),
			'desc_tip'          => true,
			'description'       => self::help(),
			'type'              => 'number',
			'wrapper_class'     => 'show_if_simple',
			'custom_attributes' => array( 'step' => '1' ),
		) );
	}

	/** @param mixed $product */
	public static function save_simple( $product ): void {
		// A variable parent is never sold itself; its variations carry the counts.
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variable' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product save.
		self::store( (int) $product->get_id(), $_POST[ self::FIELD ] ?? null );
	}

	/**
	 * @param int   $loop
	 * @param array $data
	 * @param mixed $variation
	 */
	public static function variation( $loop, $data, $variation ): void {
		woocommerce_wp_text_input( array(
			'id'                => self::VARIATION_FIELD . '[' . (int) $loop . ']',
			'name'              => self::VARIATION_FIELD . '[' . (int) $loop . ']',
			'value'             => (string) get_post_meta( (int) $variation->ID, Pool::META, true ),
			'label'             => __( 'Till stock', 'beltoft-pos' ),
			'desc_tip'          => true,
			'description'       => self::help(),
			'type'              => 'number',
			'wrapper_class'     => 'form-row form-row-full',
			'custom_attributes' => array( 'step' => '1' ),
		) );
	}

	/**
	 * @param int $variation_id
	 * @param int $loop
	 */
	public static function save_variation( $variation_id, $loop ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the variation save.
		self::store( (int) $variation_id, $_POST[ self::VARIATION_FIELD ][ $loop ] ?? null );
	}

	private static function help(): string {
		return __( 'Counted separately from the shop stock above. Leave empty if it has never been counted. The till never refuses a sale over this count, so it can go below zero.', 'beltoft-pos' );
	}

	/** @param mixed $raw */
	private static function store( int $product_id, $raw ): void {
		// Not something the form sends: leave the count as it is.
		if ( null !== $raw && ! is_scalar( $raw ) ) {
			return;
		}

		if ( null === $raw || '' === trim( (string) $raw ) ) {
			delete_post_meta( $product_id, Pool::META );

			return;
		}

		update_post_meta( $product_id, Pool::META, (int) $raw );
	}
}
