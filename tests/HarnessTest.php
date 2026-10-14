<?php

class PosHarnessTest extends WP_UnitTestCase {

	public function test_woocommerce_and_the_till_are_loaded() {
		$this->assertTrue( class_exists( 'WooCommerce' ) );
		$this->assertTrue( class_exists( 'BeltoftPos\\Plugin' ) );
	}

	/** The till must stand on WooCommerce alone. */
	public function test_beltoft_commerce_is_not_loaded() {
		$this->assertFalse( class_exists( 'BeltoftCommerce\\Plugin' ) );
	}

	/** The WordPress test suite drops every table in the database it is pointed at. */
	public function test_it_is_not_running_against_a_real_database() {
		global $wpdb;

		$this->assertStringContainsString( 'tests', $wpdb->dbname );
		$this->assertNotSame( 'woo_staging', $wpdb->dbname );
		$this->assertNotSame( 'woo_site_a', $wpdb->dbname );
	}
}
