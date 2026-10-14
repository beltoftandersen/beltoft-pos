<?php

use BeltoftPos\Host\NoIndex;
use BeltoftPos\Support\Host;

/**
 * Same as the B2B host (beltoft-commerce SeoGuards since 64cabca): noindex by
 * header and meta tag, sitemaps off, robots.txt left to Slim SEO - and nothing
 * at all on any other host.
 */
class PosNoIndexTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_the_pos_host_prints_a_noindex_meta_tag() {
		$_SERVER['HTTP_HOST'] = Host::name();

		ob_start();
		NoIndex::print_noindex();
		$this->assertSame( '<meta name="robots" content="noindex, nofollow" />' . "\n", ob_get_clean() );
		$this->assertFalse( NoIndex::sitemaps( true ) );
	}

	/** A Disallow would stop crawlers reading the noindex; robots.txt belongs to Slim SEO. */
	public function test_robots_txt_is_left_to_the_seo_plugin() {
		$_SERVER['HTTP_HOST'] = Host::name();
		add_filter( 'robots_txt', fn() => 'Allow: /', 9999 );

		$this->assertSame( 'Allow: /', apply_filters( 'robots_txt', '', true ) );
	}

	public function test_other_hosts_are_untouched() {
		$_SERVER['HTTP_HOST'] = 'staging.mindthetrash.pt';

		ob_start();
		NoIndex::print_noindex();
		$this->assertSame( '', ob_get_clean() );
		$this->assertTrue( NoIndex::sitemaps( true ) );
		$this->assertFalse( has_filter( 'wp_sitemaps_posts_query_args', array( NoIndex::class, 'exclude_pages' ) ), 'Retail sitemaps are not ours.' );
		$this->assertFalse( has_filter( 'slim_seo_sitemap_post_type_query_args', array( NoIndex::class, 'exclude_pages' ) ) );
	}
}
