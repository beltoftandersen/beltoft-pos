<?php

use BeltoftPos\Checkout\Counter;
use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

class PosCounterTest extends WP_UnitTestCase {

	private int $operator;

	public function setUp(): void {
		parent::setUp();
		$this->operator = self::factory()->user->create( array( 'role' => 'pos_operator' ) );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		unset( $_SERVER['HTTP_HOST'], $_POST['billing_first_name'], $_POST['post_data'] );
		parent::tearDown();
	}

	private function at_the_till(): void {
		$_SERVER['HTTP_HOST'] = Host::name();
		wp_set_current_user( $this->operator );
	}

	private function sample_fields(): array {
		return array(
			'billing'  => array(
				'billing_first_name' => array( 'required' => true ),
				'billing_email'      => array( 'required' => true ),
			),
			'shipping' => array( 'shipping_address_1' => array( 'required' => true ) ),
		);
	}

	public function test_nothing_is_required_of_a_walk_in_customer() {
		$this->at_the_till();
		$out = Counter::fields( $this->sample_fields() );

		$this->assertFalse( $out['billing']['billing_first_name']['required'] );
		$this->assertFalse( $out['billing']['billing_email']['required'] );
		$this->assertArrayNotHasKey( 'shipping', $out );
		$this->assertSame( Options::walkin_name(), $out['billing']['billing_first_name']['default'] );
		$this->assertSame( Options::walkin_email(), $out['billing']['billing_email']['default'] );
		$this->assertFalse( Counter::no_shipping( true ) );
		$this->assertFalse( Counter::no_terms( true ) );
	}

	public function test_other_hosts_are_untouched() {
		$_SERVER['HTTP_HOST'] = 'example.org';
		wp_set_current_user( $this->operator );

		$this->assertSame( $this->sample_fields(), Counter::fields( $this->sample_fields() ) );
		$this->assertTrue( Counter::no_shipping( true ) );
		$this->assertTrue( Counter::no_terms( true ) );
		$this->assertSame( 'x', Counter::prefill( 'x', 'billing_first_name' ) );
	}

	public function test_each_sale_starts_from_the_walk_in_defaults() {
		$this->at_the_till();

		$this->assertSame( Options::walkin_name(), Counter::prefill( 'Last customer', 'billing_first_name' ) );
		$this->assertSame( Options::walkin_email(), Counter::prefill( 'last@example.test', 'billing_email' ) );
		$this->assertSame( '', Counter::prefill( '912345678', 'billing_phone' ) );
		$this->assertSame( WC()->countries->get_base_country(), Counter::prefill( 'ES', 'billing_country' ) );
	}

	public function test_typed_values_survive_the_order_review_refresh() {
		$this->at_the_till();

		$_POST['post_data'] = 'billing_first_name=Ana+Silva&billing_email=ana%40example.test';
		$this->assertSame( 'Ana Silva', Counter::prefill( '', 'billing_first_name' ) );
		$this->assertSame( 'ana@example.test', Counter::prefill( '', 'billing_email' ) );

		unset( $_POST['post_data'] );
		$_POST['billing_first_name'] = 'Rui';
		$this->assertSame( 'Rui', Counter::prefill( '', 'billing_first_name' ) );
	}

	public function test_a_blank_postcode_stays_blank_at_the_till_only() {
		$this->assertSame( '-', Counter::blank_postcode_stays_blank( '-', 'PT' ), 'Not the till.' );

		$this->at_the_till();
		$this->assertSame( '', Counter::blank_postcode_stays_blank( '-', 'PT' ) );
		$this->assertSame( '1000-001', Counter::blank_postcode_stays_blank( '1000-001', 'PT' ) );
	}

	public function test_an_anonymous_sale_gets_the_walk_in_name_and_email() {
		$this->at_the_till();
		$order = new WC_Order();

		Counter::name_the_sale( $order );

		$this->assertSame( Options::walkin_name(), $order->get_billing_first_name() );
		$this->assertSame( Options::walkin_email(), $order->get_billing_email() );
	}

	public function test_the_till_account_is_reset_after_a_sale() {
		$this->at_the_till();
		update_user_meta( $this->operator, 'billing_phone', '912345678' );
		update_user_meta( $this->operator, 'billing_nif', '123456789' );
		update_user_meta( $this->operator, 'billing_first_name', 'Last customer' );

		Counter::clear_after_sale();

		$this->assertSame( '', get_user_meta( $this->operator, 'billing_phone', true ) );
		$this->assertSame( '', get_user_meta( $this->operator, 'billing_nif', true ) );
		$this->assertSame( Options::walkin_name(), get_user_meta( $this->operator, 'billing_first_name', true ) );
		$this->assertFalse( Counter::keep_account_clean( true ) );
	}

	public function test_the_terms_element_is_dropped_by_its_content() {
		$this->at_the_till();

		$this->assertSame( '', Counter::drop_terms_element( '<div><input type="hidden" name="terms-field" value="1"></div>' ) );
		$this->assertSame( '<div>other</div>', Counter::drop_terms_element( '<div>other</div>' ) );
	}

	/** The till's login is not the customer: a sale is a guest order, so the account earns nothing from it. */
	public function test_a_till_sale_is_a_guest_order() {
		$this->assertSame( 7, Counter::no_customer( 7 ), 'Elsewhere the signed-in customer buys.' );

		$this->at_the_till();
		$this->assertSame( 0, Counter::no_customer( $this->operator ) );
	}
}
