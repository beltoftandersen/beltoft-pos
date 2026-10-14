<?php

use BeltoftPos\Receipt\Email;
use BeltoftPos\Support\Options;
use BeltoftPos\Support\Till;

class PosReceiptEmailTest extends WP_UnitTestCase {

	private WP_User $operator;

	public function setUp(): void {
		parent::setUp();
		reset_phpmailer_instance();
		$_SERVER['HTTP_HOST'] = \BeltoftPos\Support\Host::name();
		$this->operator = get_user_by( 'id', self::factory()->user->create( array( 'role' => 'pos_operator' ) ) );
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_receipts_are_sent_from_the_till_only() {
		$order = $this->sale( Options::walkin_email() );
		$_SERVER['HTTP_HOST'] = 'example.org';

		$this->assertSame( 'beltoft_pos_forbidden', Email::send( $order->get_id(), 'a@example.test', $this->operator )->get_error_code() );
		$this->assertSame( array(), $this->sent_to() );
	}

	private function sale( string $email, ?int $operator = null ): WC_Order {
		$order = wc_create_order();
		$order->set_billing_email( $email );
		Till::mark_sale( $order, $operator ?? $this->operator->ID );
		$order->save();

		return $order;
	}

	private function sent_to(): array {
		$sent = tests_retrieve_phpmailer_instance()->get_sent( 0 );

		return $sent ? array_map( fn( $to ) => $to[0], $sent->to ) : array();
	}

	public function test_a_walk_in_receipt_goes_to_the_typed_address_and_is_kept() {
		$order = $this->sale( Options::walkin_email() );

		$this->assertTrue( Email::send( $order->get_id(), 'ana@example.test', $this->operator ) );

		$this->assertSame( array( 'ana@example.test' ), $this->sent_to() );
		$this->assertSame( 'ana@example.test', wc_get_order( $order->get_id() )->get_billing_email() );
	}

	public function test_a_customer_email_on_the_order_is_not_overwritten() {
		$order = $this->sale( 'rui@example.test' );

		$this->assertTrue( Email::send( $order->get_id(), 'other@example.test', $this->operator ) );

		$this->assertSame( array( 'other@example.test' ), $this->sent_to() );
		$this->assertSame( 'rui@example.test', wc_get_order( $order->get_id() )->get_billing_email() );
	}

	public function test_refusals() {
		$order    = $this->sale( Options::walkin_email() );
		$customer = get_user_by( 'id', self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$web      = wc_create_order();

		$this->assertSame( 'beltoft_pos_forbidden', Email::send( $order->get_id(), 'a@example.test', $customer )->get_error_code() );
		$this->assertSame( 'beltoft_pos_not_a_sale', Email::send( $web->get_id(), 'a@example.test', $this->operator )->get_error_code() );
		$this->assertSame( 'beltoft_pos_invalid_email', Email::send( $order->get_id(), 'not-an-email', $this->operator )->get_error_code() );
		$this->assertSame( array(), $this->sent_to(), 'Nothing sent on a refusal.' );
	}

	public function test_an_operator_sends_receipts_for_their_own_sales_only() {
		$other   = self::factory()->user->create( array( 'role' => 'pos_operator' ) );
		$order   = $this->sale( Options::walkin_email(), $other );
		$manager = get_user_by( 'id', self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertSame( 'beltoft_pos_not_yours', Email::send( $order->get_id(), 'a@example.test', $this->operator )->get_error_code() );
		$this->assertSame( array(), $this->sent_to() );
		$this->assertSame( Options::walkin_email(), wc_get_order( $order->get_id() )->get_billing_email(), 'A refused send leaves the order as it was.' );

		$this->assertTrue( Email::send( $order->get_id(), 'a@example.test', $manager ), 'A shop manager may send any till receipt.' );
	}

	public function test_the_bricks_form_is_read_by_field_name() {
		$form = new class() {
			public array $results = array();
			public function get_settings() {
				return array( 'fields' => array(
					array( 'id' => 'abc123', 'name' => 'pos_receipt_order' ),
					array( 'id' => 'def456', 'name' => 'pos_receipt_email' ),
				) );
			}
			public function get_fields() {
				return array( 'form-field-abc123' => '42', 'form-field-def456' => 'ana@example.test' );
			}
			public function set_result( $r ) {
				$this->results[] = $r;
			}
		};

		$this->assertSame( '42', Email::field( $form, Email::ORDER_FIELD ) );
		$this->assertSame( 'ana@example.test', Email::field( $form, Email::EMAIL_FIELD ) );
		$this->assertSame( '', Email::field( $form, 'missing' ) );
	}

	public function test_other_bricks_forms_are_left_alone() {
		$form = new class() {
			public array $results = array();
			public function get_settings() {
				return array( 'fields' => array( array( 'id' => 'x', 'name' => 'newsletter' ) ) );
			}
			public function get_fields() {
				return array( 'form-field-x' => 'a@example.test' );
			}
			public function set_result( $r ) {
				$this->results[] = $r;
			}
		};

		Email::handle( $form );

		$this->assertSame( array(), $form->results );
	}
}
