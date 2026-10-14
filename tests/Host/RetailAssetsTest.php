<?php

use BeltoftPos\Host\RetailAssets;
use BeltoftPos\Support\Host;

/** The shop's menu and wishlist scripts have nothing to work on at the till. */
class PosRetailAssetsTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		wp_enqueue_script( 'custom-menu-scripts', 'https://x/custom-menu-scripts.js', array(), '1', true );
		wp_enqueue_script( 'woosw-frontend', 'https://x/woosw.js', array(), '1', true );
		wp_enqueue_script( 'beltoft-pos-list', 'https://x/list.js', array(), '1', true );
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		foreach ( array( 'custom-menu-scripts', 'woosw-frontend', 'beltoft-pos-list' ) as $h ) {
			wp_dequeue_script( $h );
			wp_deregister_script( $h );
		}
		parent::tearDown();
	}

	public function test_the_till_drops_the_shop_header_scripts() {
		$_SERVER['HTTP_HOST'] = Host::name();

		RetailAssets::drop();

		$this->assertFalse( wp_script_is( 'custom-menu-scripts', 'enqueued' ), 'The retail mega menu is not on the page.' );
		$this->assertFalse( wp_script_is( 'woosw-frontend', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'beltoft-pos-list', 'enqueued' ) );
	}

	public function test_other_hosts_keep_them() {
		$_SERVER['HTTP_HOST'] = 'example.org';

		RetailAssets::drop();

		$this->assertTrue( wp_script_is( 'custom-menu-scripts', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'woosw-frontend', 'enqueued' ) );
	}
}
