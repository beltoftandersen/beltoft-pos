<?php

use BeltoftPos\Support\Host;

class PosHostTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		remove_all_filters( 'beltoft_pos_host' );
		parent::tearDown();
	}

	public function test_the_host_comes_from_the_constant() {
		$this->assertSame( 'pos.staging.mindthetrash.pt', Host::name() );
		$this->assertTrue( Host::is_configured() );
	}

	public function test_no_host_means_no_till_anywhere() {
		add_filter( 'beltoft_pos_host', '__return_empty_string' );
		$_SERVER['HTTP_HOST'] = 'pos.staging.mindthetrash.pt';

		$this->assertFalse( Host::is_configured() );
		$this->assertFalse( Host::is_pos_host(), 'With no host set, no request is the till.' );
		$this->assertFalse( Host::is_pos_host( '' ) );
	}

	public function test_the_host_can_be_filtered() {
		add_filter( 'beltoft_pos_host', fn() => 'POS.Example.TEST' );
		$this->assertSame( 'pos.example.test', Host::name() );
	}

	public function test_host_matching_ignores_case_and_port() {
		$_SERVER['HTTP_HOST'] = 'POS.Staging.MindTheTrash.pt:443';
		$this->assertTrue( Host::is_pos_host() );
		$this->assertTrue( Host::is_pos_host( 'pos.staging.mindthetrash.pt:8080' ) );
		$this->assertTrue( Host::is_pos_host( 'pos.staging.mindthetrash.pt.' ), 'A fully qualified name ends in a dot.' );
	}

	public function test_other_hosts_and_no_host_are_not_the_till() {
		$this->assertFalse( Host::is_pos_host(), 'CLI and cron have no host.' );
		$_SERVER['HTTP_HOST'] = 'b2b.staging.mindthetrash.pt';
		$this->assertFalse( Host::is_pos_host() );
		$this->assertFalse( Host::is_pos_host( 'staging.mindthetrash.pt' ) );
	}

	public function test_url_builds_on_the_pos_host() {
		$this->assertSame( 'https://pos.staging.mindthetrash.pt/caixa/', Host::url( '/caixa/' ) );
		$this->assertSame( 'https://pos.staging.mindthetrash.pt/', Host::url() );
	}
}
