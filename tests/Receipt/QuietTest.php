<?php

use BeltoftPos\Support\Till;

class PosReceiptQuietTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// The mailer is built once per process; the test case resets hooks per test.
		\BeltoftPos\Receipt\Quiet::hush( WC()->mailer()->get_emails() );
	}

	private function mail( string $class, WC_Order $order ): WC_Email {
		$mail          = WC()->mailer()->get_emails()[ $class ];
		$mail->enabled = 'yes';
		$mail->object  = $order;

		return $mail;
	}

	private function order( bool $till ): WC_Order {
		$order = wc_create_order();

		if ( $till ) {
			Till::mark_sale( $order );
			$order->save();
		}

		return $order;
	}

	public function test_a_till_sale_sends_no_automatic_customer_email() {
		$sale = $this->order( true );

		$this->assertFalse( $this->mail( 'WC_Email_Customer_Processing_Order', $sale )->is_enabled() );
		$this->assertFalse( $this->mail( 'WC_Email_Customer_Completed_Order', $sale )->is_enabled() );
	}

	public function test_the_receipt_on_request_and_website_orders_are_untouched() {
		$this->assertTrue( $this->mail( 'WC_Email_Customer_Invoice', $this->order( true ) )->is_enabled() );
		$this->assertTrue( $this->mail( 'WC_Email_Customer_Completed_Order', $this->order( false ) )->is_enabled() );
	}
}
