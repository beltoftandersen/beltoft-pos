<?php

use BeltoftPos\Stock\Pool;
use BeltoftPos\Support\Host;
use BeltoftPos\Support\Till;

class PosPoolTest extends WP_UnitTestCase {

	private int $operator;
	private int $product;

	public function setUp(): void {
		parent::setUp();
		Pool::register();

		$this->operator = self::factory()->user->create( array( 'role' => 'pos_operator' ) );

		$p = new WC_Product_Simple();
		$p->set_name( 'Shelf item' );
		$p->set_regular_price( '10' );
		$p->set_manage_stock( true );
		$p->set_stock_quantity( 40 ); // the warehouse
		$this->product = $p->save();

		update_post_meta( $this->product, Pool::META, 3 ); // the shelf
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	private function fresh(): WC_Product {
		return wc_get_product( $this->product );
	}

	private function shelf(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
			$this->product,
			Pool::META
		) );
	}

	private function sale( int $qty, bool $marked = true ): WC_Order {
		$order = new WC_Order();
		$order->add_product( $this->fresh(), $qty );

		if ( $marked ) {
			Till::mark_sale( $order );
		}

		$order->save();

		return $order;
	}

	public function test_the_till_sees_the_shelf_and_everyone_else_the_warehouse() {
		$_SERVER['HTTP_HOST'] = Host::name();
		wp_set_current_user( $this->operator );
		$this->assertSame( 3, $this->fresh()->get_stock_quantity() );

		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( 40, $this->fresh()->get_stock_quantity(), 'Same operator, other host.' );

		wp_set_current_user( 0 );
		$this->assertSame( 40, $this->fresh()->get_stock_quantity() );
	}

	/** The count is a record, not a gate. */
	public function test_the_till_never_refuses_goods_on_the_table() {
		$p = $this->fresh();
		$p->set_backorders( 'no' );
		$p->save();
		update_post_meta( $this->product, Pool::META, -2 );

		$_SERVER['HTTP_HOST'] = Host::name();
		wp_set_current_user( $this->operator );

		$this->assertSame( -2, $this->fresh()->get_stock_quantity() );
		$this->assertTrue( $this->fresh()->is_in_stock() );
		$this->assertSame( 'instock', $this->fresh()->get_stock_status() );
		$this->assertSame( 'yes', $this->fresh()->get_backorders() );
	}

	public function test_an_uncounted_product_sells_and_reads_as_none() {
		delete_post_meta( $this->product, Pool::META );
		$_SERVER['HTTP_HOST'] = Host::name();
		wp_set_current_user( $this->operator );

		$this->assertTrue( $this->fresh()->managing_stock() );
		$this->assertSame( 0, $this->fresh()->get_stock_quantity() );
		$this->assertTrue( $this->fresh()->is_in_stock() );
	}

	/** Saving a product during a till request must not write the till's count into retail stock status. */
	public function test_a_product_saved_at_the_till_keeps_its_retail_stock_status() {
		$p = $this->fresh();
		$p->set_stock_quantity( 0 );
		$p->set_backorders( 'no' );
		$p->save();
		$this->assertSame( 'outofstock', get_post_meta( $this->product, '_stock_status', true ) );

		$_SERVER['HTTP_HOST'] = Host::name();
		wp_set_current_user( $this->operator );
		wc_get_product( $this->product )->save();

		$this->assertSame( 'outofstock', get_post_meta( $this->product, '_stock_status', true ), 'Not "instock" from the shelf of 3.' );
		$this->assertSame( 3, $this->fresh()->get_stock_quantity(), 'Reading at the till still sees the shelf.' );
	}

	public function test_a_till_sale_moves_the_shelf_and_never_the_warehouse() {
		$this->assertFalse( Pool::divert_reduce( true, $this->sale( 2 ) ) );

		$this->assertSame( 1, $this->shelf() );
		$this->assertSame( '40', (string) get_post_meta( $this->product, '_stock', true ) );
	}

	public function test_an_unmarked_order_is_left_to_woocommerce() {
		$this->assertTrue( Pool::divert_reduce( true, $this->sale( 2, false ) ) );
		$this->assertSame( 3, $this->shelf() );
	}

	public function test_a_reduced_then_cancelled_sale_restores_the_shelf_only() {
		$order = $this->sale( 2 );

		wc_maybe_reduce_stock_levels( $order->get_id() );
		$this->assertSame( 1, $this->shelf() );

		wc_maybe_increase_stock_levels( $order->get_id() );
		$this->assertSame( 3, $this->shelf() );
		$this->assertSame( '40', (string) get_post_meta( $this->product, '_stock', true ) );
	}

	/** A refund with "restock" ticked puts the goods back on the till's shelf, never in the warehouse. */
	public function test_a_refunded_sale_with_restock_goes_back_on_the_shelf() {
		$order = $this->sale( 2 );
		wc_maybe_reduce_stock_levels( $order->get_id() );
		$this->assertSame( 1, $this->shelf() );

		$item_id = array_key_first( wc_get_order( $order->get_id() )->get_items() );
		wc_create_refund( array(
			'order_id'      => $order->get_id(),
			'amount'        => 0,
			'line_items'    => array( $item_id => array( 'qty' => 2, 'refund_total' => 0 ) ),
			'restock_items' => true,
		) );

		$this->assertSame( 3, $this->shelf() );
		$this->assertSame( '40', (string) get_post_meta( $this->product, '_stock', true ) );
	}

	private function refund( WC_Order $order, int $qty ): void {
		$item_id = array_key_first( wc_get_order( $order->get_id() )->get_items() );
		wc_create_refund( array(
			'order_id'      => $order->get_id(),
			'amount'        => 0,
			'line_items'    => array( $item_id => array( 'qty' => $qty, 'refund_total' => 0 ) ),
			'restock_items' => true,
		) );
	}

	/** Each unit goes back once: what a refund restocked, a cancel does not restock again. */
	public function test_a_partly_refunded_then_cancelled_sale_restores_only_the_rest() {
		$order = $this->sale( 3 );
		wc_maybe_reduce_stock_levels( $order->get_id() );
		$this->assertSame( 0, $this->shelf() );

		$this->refund( $order, 1 );
		$this->assertSame( 1, $this->shelf() );

		wc_maybe_increase_stock_levels( $order->get_id() );
		$this->assertSame( 3, $this->shelf(), 'Not 4: the refunded unit was already back.' );
	}

	public function test_a_cancelled_then_refunded_sale_is_not_restocked_twice() {
		$order = $this->sale( 2 );
		wc_maybe_reduce_stock_levels( $order->get_id() );
		wc_maybe_increase_stock_levels( $order->get_id() );
		$this->assertSame( 3, $this->shelf() );

		$this->refund( $order, 2 );
		$this->assertSame( 3, $this->shelf() );
	}

	public function test_a_refund_of_a_sale_never_reduced_restocks_nothing() {
		$order = $this->sale( 2 );

		$this->refund( $order, 2 );

		$this->assertSame( 3, $this->shelf() );
	}

	public function test_a_till_restock_leaves_an_order_note() {
		$order = $this->sale( 2 );
		wc_maybe_reduce_stock_levels( $order->get_id() );
		$this->refund( $order, 1 );

		$notes = implode( ' | ', wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 'content' ) );
		$this->assertStringContainsString( 'Till stock', $notes );
		$this->assertStringContainsString( '1 → 2', $notes );
	}

	public function test_a_refunded_website_order_is_left_to_woocommerce() {
		$this->assertTrue( Pool::divert_restock_refund( true, $this->sale( 2, false ), array() ) );
	}

	public function test_a_sale_can_take_the_shelf_below_zero() {
		Pool::divert_reduce( true, $this->sale( 5 ) );
		$this->assertSame( -2, $this->shelf() );
	}

	public function test_a_count_is_never_invented_for_an_uncounted_product() {
		delete_post_meta( $this->product, Pool::META );

		Pool::divert_reduce( true, $this->sale( 2 ) );

		$this->assertSame( '', get_post_meta( $this->product, Pool::META, true ) );
	}

	/** Arithmetic in the UPDATE, so a stale cached count cannot lose a sale. */
	public function test_a_decrement_is_not_computed_from_a_stale_cached_count() {
		$order = $this->sale( 2 );

		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = 2 WHERE post_id = %d AND meta_key = %s",
			$this->product,
			Pool::META
		) );
		$this->assertSame( 3, (int) get_post_meta( $this->product, Pool::META, true ), 'Cache still says 3.' );

		Pool::divert_reduce( true, $order );

		$this->assertSame( 0, $this->shelf() );
		$this->assertSame( 0, (int) get_post_meta( $this->product, Pool::META, true ), 'Cache invalidated.' );
	}

	public function test_a_till_sale_reserves_nothing_from_the_warehouse() {
		$this->assertSame( 0, Pool::no_hold( 30, $this->sale( 1 ) ) );
		$this->assertSame( 30, Pool::no_hold( 30, $this->sale( 1, false ) ) );
	}

	public function test_the_sale_is_marked_before_woocommerce_reserves_stock() {
		$ours = has_action( 'woocommerce_checkout_order_created', array( Pool::class, 'record' ) );

		$this->assertIsInt( $ours );
		$this->assertLessThan( 10, $ours, 'wc_reserve_stock_for_order runs at 10.' );
	}

	public function test_record_marks_only_till_sessions() {
		$order = new WC_Order();
		$order->save();
		Pool::record( $order );
		$this->assertFalse( Till::is_pos_sale( wc_get_order( $order->get_id() ) ) );

		$_SERVER['HTTP_HOST'] = Host::name();
		wp_set_current_user( $this->operator );
		Pool::record( $order );
		$this->assertTrue( Till::is_pos_sale( wc_get_order( $order->get_id() ) ) );
	}
}
