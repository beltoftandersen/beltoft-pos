<?php

use BeltoftPos\Checkout\Page;
use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

class PosPageTest extends WP_UnitTestCase {

	private int $pos;

	public function setUp(): void {
		parent::setUp();
		$this->pos = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'caixa' ) );
		update_option( Options::PAGE, $this->pos );
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_on_the_pos_host_the_pos_page_is_checkout_cart_and_front() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertSame( $this->pos, Page::page_id( 123 ) );
		$this->assertSame( 'page', Page::show_on_front( 'posts' ) );
		$this->assertSame( $this->pos, Page::page_on_front( 29 ) );
	}

	public function test_through_woocommerce_itself() {
		Page::register();
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertSame( $this->pos, wc_get_page_id( 'checkout' ) );
		$this->assertNotSame( $this->pos, wc_get_page_id( 'cart' ), 'The retail cart page redirects itself away (beltoft-offcanvas-cart), so the till is not it.' );
	}

	/** Every cart link on the till leads back to the till, and an empty cart stays on it. */
	public function test_the_cart_url_is_the_till_and_an_empty_cart_stays() {
		Page::register();
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertSame( get_permalink( $this->pos ), Page::cart_url( 'https://x/carrinho/' ) );
		$this->assertFalse( Page::keep_empty_checkout( true ) );

		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( 'https://x/carrinho/', Page::cart_url( 'https://x/carrinho/' ) );
		$this->assertTrue( Page::keep_empty_checkout( true ) );
	}

	public function test_other_hosts_keep_their_pages() {
		$_SERVER['HTTP_HOST'] = 'example.org';

		$this->assertSame( 123, Page::page_id( 123 ) );
		$this->assertSame( 'posts', Page::show_on_front( 'posts' ) );
		$this->assertSame( 29, Page::page_on_front( 29 ) );
	}

	/** A missing page must fall back to WooCommerce's, not a 404 with a full cart. */
	public function test_an_unpublished_pos_page_falls_back() {
		wp_update_post( array( 'ID' => $this->pos, 'post_status' => 'draft' ) );
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertSame( 123, Page::page_id( 123 ) );
		$this->assertSame( 29, Page::page_on_front( 29 ) );
		$this->assertSame( 'posts', Page::show_on_front( 'posts' ) );
	}
}
