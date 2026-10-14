<?php

use BeltoftPos\Host\NoIndex;
use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

class PosNoIndexTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_the_pos_host_asks_not_to_be_indexed() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$robots = NoIndex::robots( array( 'max-image-preview' => 'large' ) );
		$this->assertTrue( $robots['noindex'] );
		$this->assertTrue( $robots['nofollow'] );
		$this->assertArrayNotHasKey( 'max-image-preview', $robots );

		$this->assertSame( "whatever\n\nUser-agent: *\nDisallow: /\n", NoIndex::robots_txt( 'whatever' ), 'Added to, never replaced.' );
		$this->assertFalse( NoIndex::sitemaps( true ) );
	}

	/** Slim SEO writes robots.txt at 9999; its text stays and the till's block follows it. */
	public function test_robots_txt_keeps_the_seo_text_and_adds_after_it() {
		$_SERVER['HTTP_HOST'] = Host::name();
		add_filter( 'robots_txt', fn() => 'Allow: /', 9999 );

		$this->assertSame( "Allow: /\n\nUser-agent: *\nDisallow: /\n", apply_filters( 'robots_txt', '', true ) );
	}

	public function test_other_hosts_are_untouched() {
		$_SERVER['HTTP_HOST'] = 'example.org';

		$this->assertSame( array( 'max-image-preview' => 'large' ), NoIndex::robots( array( 'max-image-preview' => 'large' ) ) );
		$this->assertSame( 'whatever', NoIndex::robots_txt( 'whatever' ) );
		$this->assertTrue( NoIndex::sitemaps( true ) );
	}

	public function test_the_till_pages_are_left_out_of_every_sitemap() {
		$pos   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$login = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( Options::PAGE, $pos );
		update_option( Options::LOGIN_PAGE, $login );

		$args = NoIndex::exclude_pages( array( 'post__not_in' => array( 7 ) ) );
		$this->assertEqualsCanonicalizing( array( 7, $pos, $login ), $args['post__not_in'] );
	}
}
