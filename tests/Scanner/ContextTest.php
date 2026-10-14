<?php

use BeltoftPos\Scanner\Context;
use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

class PosScannerContextTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_the_till_registers_its_own_context_beside_others() {
		$contexts = Context::add_context( array( 'b2b-loja' => array( 'x' ) ) );

		$this->assertArrayHasKey( 'b2b-loja', $contexts, 'Never removes another plugin\'s context.' );
		$this->assertIsCallable( $contexts['pos']['applies'] );
		$this->assertIsCallable( $contexts['pos']['may_scan'] );
	}

	public function test_only_till_users_may_scan() {
		$may = Context::add_context( array() )['pos']['may_scan'];

		$this->assertTrue( $may( get_user_by( 'id', self::factory()->user->create( array( 'role' => 'pos_operator' ) ) ) ) );
		$this->assertFalse( $may( get_user_by( 'id', self::factory()->user->create( array( 'role' => 'customer' ) ) ) ) );
	}

	public function test_it_applies_on_the_pos_page_on_the_pos_host_only() {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( Options::PAGE, $page );
		$this->go_to( get_permalink( $page ) );

		$this->assertFalse( Context::applies(), 'Not on another host.' );

		$_SERVER['HTTP_HOST'] = Host::name();
		$this->assertTrue( Context::applies() );

		unset( $_SERVER['HTTP_HOST'] );
		$this->go_to( '/?p=' . self::factory()->post->create() );
		$_SERVER['HTTP_HOST'] = Host::name();
		$this->assertFalse( Context::applies(), 'Not on another page.' );
	}
}
