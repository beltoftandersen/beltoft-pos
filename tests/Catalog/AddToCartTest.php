<?php

use BeltoftPos\Catalog\AddToCart;
use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

/**
 * The till lists every variation as its own row, and a row's button must add
 * that variation in place, like a simple product's does.
 */
class PosAddToCartTest extends WP_UnitTestCase {

	private int $variation;
	private int $simple;
	private int $page;

	public function setUp(): void {
		parent::setUp();

		$parent          = ( new WC_Product_Variable() )->save();
		$v               = new WC_Product_Variation();
		$v->set_parent_id( $parent );
		$v->set_regular_price( '3' );
		$this->variation = $v->save();
		$this->simple    = ( new WC_Product_Simple() )->save();
		$this->page      = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'caixa' ) );
		update_option( Options::PAGE, $this->page );
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_a_variation_link_adds_on_the_till() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$url = AddToCart::url( 'https://x/loja/p/?add-to-cart=1', wc_get_product( $this->variation ) );
		$this->assertSame( add_query_arg( 'add-to-cart', $this->variation, get_permalink( $this->page ) ), $url, 'Without JavaScript it still lands on the till.' );
	}

	public function test_simple_products_and_other_hosts_are_untouched() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$this->assertSame( 'https://x/', AddToCart::url( 'https://x/', wc_get_product( $this->simple ) ) );

		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( 'https://x/', AddToCart::url( 'https://x/', wc_get_product( $this->variation ) ) );
	}
}
