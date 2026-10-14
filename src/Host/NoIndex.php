<?php

namespace BeltoftPos\Host;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The till is not a website.
 *
 * Nothing on the POS host is for search engines, and nothing on it may be
 * cached: every page is one operator's cart. The server's page cache already
 * bypasses every host but retail; this says it again from WordPress, so a
 * cache plugin that keys on host cannot serve one till's page to another.
 *
 * The two till pages also exist on every other host of this install (one
 * install, shared slugs), so they are kept out of the sitemaps there too.
 */
class NoIndex {

	public static function register(): void {
		add_action( 'send_headers', array( __CLASS__, 'headers' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 99 );
		// Last: Slim SEO rewrites robots.txt at 9999.
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), PHP_INT_MAX );
		add_filter( 'wp_sitemaps_enabled', array( __CLASS__, 'sitemaps' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_feeds' ), 0 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'exclude_pages' ) );
		add_filter( 'slim_seo_sitemap_post_type_query_args', array( __CLASS__, 'exclude_pages' ) );
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

	/**
	 * @param mixed $robots
	 * @return mixed
	 */
	public static function robots( $robots ) {
		if ( ! is_array( $robots ) || ! Host::is_pos_host() ) {
			return $robots;
		}

		unset( $robots['index'], $robots['follow'], $robots['max-image-preview'] );
		$robots['noindex']  = true;
		$robots['nofollow'] = true;

		return $robots;
	}

	/**
	 * @param mixed $output
	 * @return mixed
	 */
	public static function robots_txt( $output ) {
		return Host::is_pos_host() ? "User-agent: *\nDisallow: /\n" : $output;
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

	/**
	 * @param mixed $args
	 * @return mixed
	 */
	public static function exclude_pages( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$pages = array_filter( array( Options::page_id(), Options::login_page_id() ) );

		if ( $pages ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), $pages ) ) );
		}

		return $args;
	}
}
