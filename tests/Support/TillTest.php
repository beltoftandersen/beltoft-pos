<?php

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Till;

class PosTillTest extends WP_UnitTestCase {

	public function tearDown(): void {
		wp_set_current_user( 0 );
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_till_users_are_operators_and_shop_staff() {
		$this->assertTrue( Till::is_till_user( get_user_by( 'id', self::factory()->user->create( array( 'role' => 'pos_operator' ) ) ) ) );
		$this->assertTrue( Till::is_till_user( get_user_by( 'id', self::factory()->user->create( array( 'role' => 'shop_manager' ) ) ) ) );
		$this->assertTrue( Till::is_till_user( get_user_by( 'id', self::factory()->user->create( array( 'role' => 'administrator' ) ) ) ) );
		$this->assertFalse( Till::is_till_user( get_user_by( 'id', self::factory()->user->create( array( 'role' => 'customer' ) ) ) ) );
		$this->assertFalse( Till::is_till_user( null ) );
		$this->assertFalse( Till::is_till_user( new WP_User( 0 ) ) );
	}

	public function test_a_session_needs_the_host_and_a_till_user() {
		$pos = self::factory()->user->create( array( 'role' => 'pos_operator' ) );
		wp_set_current_user( $pos );
		$this->assertFalse( Till::is_session(), 'Same user on another host is not the till.' );

		$_SERVER['HTTP_HOST'] = Host::name();
		$this->assertTrue( Till::is_session() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->assertFalse( Till::is_session(), 'A customer on the POS host is not a till session.' );

		wp_set_current_user( 0 );
		$this->assertFalse( Till::is_session() );
	}

	public function test_a_sale_is_known_by_its_marker() {
		$order = new WC_Order();
		$this->assertFalse( Till::is_pos_sale( $order ) );

		Till::mark_sale( $order );
		$order->save();

		$this->assertTrue( Till::is_pos_sale( wc_get_order( $order->get_id() ) ) );
		$this->assertFalse( Till::is_pos_sale( null ) );
	}
}
