<?php

use BeltoftPos\Checkout\PaymentMethods;
use BeltoftPos\Checkout\Tender;
use BeltoftPos\Checkout\Tenders;
use BeltoftPos\Support\Host;

class PosTendersTest extends WP_UnitTestCase {

	private int $operator;

	public function setUp(): void {
		parent::setUp();
		$this->operator = self::factory()->user->create( array( 'role' => 'pos_operator' ) );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_the_four_tenders_in_counter_order() {
		$this->assertSame( array( 'pos_mbway', 'pos_card', 'pos_cash', 'pos_bank' ), Tenders::ids() );

		$gateways = Tenders::add( array() );
		$this->assertCount( 4, $gateways );
		$this->assertContainsOnlyInstancesOf( Tender::class, $gateways );
	}

	/** A tender is a real gateway; without this it would be offered to shoppers. */
	public function test_a_tender_is_not_offered_outside_the_till() {
		$_SERVER['HTTP_HOST'] = 'example.org';
		wp_set_current_user( $this->operator );

		$available = PaymentMethods::filter( array(
			'pos_cash' => new Tender( 'pos_cash', 'Cash' ),
			'bacs'     => new stdClass(),
		) );

		$this->assertArrayNotHasKey( 'pos_cash', $available );
		$this->assertArrayHasKey( 'bacs', $available );
	}

	/** At the till the list is exactly the tenders - never a real gateway. */
	public function test_the_till_offers_exactly_the_tenders() {
		$_SERVER['HTTP_HOST'] = Host::name();
		wp_set_current_user( $this->operator );

		$available = PaymentMethods::filter( array( 'bacs' => new stdClass() ) );

		$this->assertSame( Tenders::ids(), array_keys( $available ) );
	}

	public function test_a_tender_refuses_payment_outside_the_till() {
		$_SERVER['HTTP_HOST'] = 'example.org';
		$order = wc_create_order();

		$result = ( new Tender( 'pos_cash', 'Cash' ) )->process_payment( $order->get_id() );

		$this->assertSame( 'failure', $result['result'] );
		$this->assertFalse( wc_get_order( $order->get_id() )->is_paid() );
	}

	public function test_a_till_sale_is_paid_on_arrival() {
		$_SERVER['HTTP_HOST'] = Host::name();
		wp_set_current_user( $this->operator );
		$order = wc_create_order();

		$result = ( new Tender( 'pos_cash', 'Cash' ) )->process_payment( $order->get_id() );

		$this->assertSame( 'success', $result['result'] );
		$this->assertTrue( wc_get_order( $order->get_id() )->is_paid() );
	}
}
