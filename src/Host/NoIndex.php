<?php

namespace BeltoftPos\Host;

use BeltoftPos\Support\Host;

defined( 'ABSPATH' ) || exit;

/**
 * The till is not a website.
 *
 * Kept out of every search index the same way as the B2B host: an
 * X-Robots-Tag header and a robots meta tag on every response, and no
 * sitemaps. robots.txt is left to Slim SEO - a crawler that may not fetch a
 * page cannot read the noindex on it either.
 *
 * Nor may anything on it be cached: every page is one operator's cart. The
 * server's page cache already bypasses every host but retail; this says it
 * again from WordPress, so a cache plugin that keys on host cannot serve one
 * till's page to another.
 *
 * Everything here acts on the POS host only. Retail is never touched.
 */
class NoIndex {

	public static function register(): void {
		add_action( 'send_headers', array( __CLASS__, 'headers' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_noindex' ), 1 );
		add_filter( 'wp_sitemaps_enabled', array( __CLASS__, 'sitemaps' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_feeds' ), 0 );
	}

	public static function headers(): void {
		if ( ! Host::is_pos_host() || headers_sent() ) {
			return;
		}

		header( 'X-Robots-Tag: noindex, nofollow', true );
		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}

	public static function print_noindex(): void {
		if ( ! Host::is_pos_host() ) {
			return;
		}

		echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
	}

	/**
	 * @param mixed $enabled
	 * @return mixed
	 */
	public static function sitemaps( $enabled ) {
		return Host::is_pos_host() ? false : $enabled;
	}

	public static function no_feeds(): void {
		if ( Host::is_pos_host() && is_feed() ) {
			wp_die( '', '', array( 'response' => 404 ) );
		}
	}
}
