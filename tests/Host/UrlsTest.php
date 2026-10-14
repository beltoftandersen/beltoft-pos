<?php

use BeltoftPos\Host\Urls;
use BeltoftPos\Support\Host;

class PosUrlsTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		Urls::register();
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		Urls::resume();
		parent::tearDown();
	}

	public function test_home_and_siteurl_follow_the_pos_host() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertSame( 'https://pos.staging.mindthetrash.pt', get_option( 'home' ) );
		$this->assertSame( 'https://pos.staging.mindthetrash.pt/caixa/', home_url( '/caixa/' ) );
		$this->assertStringStartsWith( 'https://pos.staging.mindthetrash.pt/', rest_url() );
	}

	public function test_other_hosts_keep_the_stored_urls() {
		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( 'http://example.org', get_option( 'home' ) );

		unset( $_SERVER['HTTP_HOST'] );
		$this->assertSame( 'http://example.org', get_option( 'home' ), 'Cron and CLI have no host.' );
	}

	public function test_admin_stays_on_the_retail_host_but_ajax_stays_here() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertStringStartsWith( 'http://example.org/wp-admin/', admin_url( 'options.php' ) );
		$this->assertStringContainsString( '//pos.staging.mindthetrash.pt/wp-admin/admin-ajax.php', admin_url( 'admin-ajax.php' ), 'Scheme follows is_ssl().' );
		$this->assertStringStartsWith( 'http://example.org/wp-admin/admin-post.php', admin_url( 'admin-post.php' ), 'Like the rest of wp-admin: the gate sends it to retail.' );
	}

	public function test_background_jobs_never_get_the_pos_host() {
		$_SERVER['HTTP_HOST'] = Host::name();
		Urls::suspend();

		$this->assertSame( 'http://example.org', get_option( 'home' ) );
	}

	public function test_stored_reads_through_the_filter() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertSame( 'http://example.org', Urls::stored() );
		$this->assertSame( 'https://pos.staging.mindthetrash.pt', get_option( 'home' ), 'And the filter is back on.' );
	}

	public function test_email_links_from_a_till_request_point_at_retail() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertSame(
			'<a href="http://example.org/minha-conta/">',
			Urls::retail_links( '<a href="https://pos.staging.mindthetrash.pt/minha-conta/">' )
		);
	}
}
