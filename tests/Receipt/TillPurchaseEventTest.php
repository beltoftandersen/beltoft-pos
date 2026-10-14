<?php

use BeltoftPos\Receipt\TillPurchaseEvent;
use BeltoftPos\Support\Till;

class PosTillPurchaseEventTest extends WP_UnitTestCase {

	private function web_purchase(): array {
		return array(
			'event'     => 'purchase',
			'ecommerce' => array( 'transaction_id' => '123', 'affiliation' => '', 'value' => 10.0 ),
			'user_data' => array( 'sha256_email_address' => 'x' ),
		);
	}

	public function test_a_till_sale_is_its_own_tagged_event_without_customer_data() {
		$sale = wc_create_order();
		Till::mark_sale( $sale );
		$sale->save();

		$out = TillPurchaseEvent::tag( $this->web_purchase(), $sale );

		$this->assertSame( 'pos_purchase', $out['event'], 'Not "purchase": the web conversion tags must not fire.' );
		$this->assertSame( 'POS', $out['ecommerce']['affiliation'] );
		$this->assertSame( 'pos', $out['sales_channel'] );
		$this->assertSame( 10.0, $out['ecommerce']['value'], 'The sale itself is still there to report.' );
		$this->assertArrayNotHasKey( 'user_data', $out );
	}

	public function test_a_website_purchase_is_untouched() {
		$this->assertSame( $this->web_purchase(), TillPurchaseEvent::tag( $this->web_purchase(), wc_create_order() ) );
	}
}
