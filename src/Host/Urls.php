<?php

namespace BeltoftPos\Host;

use BeltoftPos\Support\Host;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress, answering on the till's hostname.
 *
 * The install stores one home URL - retail's. On the POS host every link,
 * REST route, AJAX endpoint and asset has to stay on the POS host, or the
 * till's own requests would land on a host where nobody is signed in. Filtering
 * the two options is what every URL function reads from, so nothing else has
 * to know.
 *
 * Exceptions, each for a reason:
 * - wp-admin goes to retail, where staff are signed in to edit - admin-post.php
 *   included, as Host\Gate sends it there too. admin-ajax.php stays here:
 *   the till's own forms (login, receipt email) post to it.
 * - Action Scheduler runs queued jobs at the end of whatever request happens
 *   to trigger it; a job must not inherit the till's host by accident.
 * - Emails sent during a till request link to retail: the customer reading
 *   them cannot get past the till's login.
 */
class Urls {

	private static bool $suspended = false;

	public static function register(): void {
		add_filter( 'option_home', array( __CLASS__, 'filter' ) );
		add_filter( 'option_siteurl', array( __CLASS__, 'filter' ) );
		add_filter( 'admin_url', array( __CLASS__, 'admin_url' ) );
		add_filter( 'woocommerce_mail_content', array( __CLASS__, 'retail_links' ) );
		add_action( 'action_scheduler_before_process_queue', array( __CLASS__, 'suspend' ) );
		add_action( 'action_scheduler_after_process_queue', array( __CLASS__, 'resume' ) );
	}

	public static function suspend(): void {
		self::$suspended = true;
	}

	public static function resume(): void {
		self::$suspended = false;
	}

	/** The stored home or siteurl, whatever host this request is on. */
	public static function stored( string $option = 'home' ): string {
		$was             = self::$suspended;
		self::$suspended = true;
		$value           = (string) get_option( $option );
		self::$suspended = $was;

		return untrailingslashit( $value );
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public static function filter( $value ) {
		if ( self::$suspended || ! is_string( $value ) || '' === $value || ! Host::is_pos_host() ) {
			return $value;
		}

		return 'https://' . Host::name() . untrailingslashit( (string) wp_parse_url( $value, PHP_URL_PATH ) );
	}

	/**
	 * @param mixed $url
	 * @return mixed
	 */
	public static function admin_url( $url ) {
		if ( ! is_string( $url ) || ! Host::is_pos_host() ) {
			return $url;
		}

		if ( false !== strpos( $url, 'admin-ajax.php' ) ) {
			return $url;
		}

		return self::to_retail( $url, 'siteurl' );
	}

	/**
	 * @param mixed $content
	 * @return mixed
	 */
	public static function retail_links( $content ) {
		return is_string( $content ) && Host::is_pos_host() ? self::to_retail( $content, 'home' ) : $content;
	}

	/** Either scheme: admin_url() follows is_ssl(), which depends on how the proxy reports it. */
	private static function to_retail( string $text, string $option ): string {
		return (string) preg_replace( '#https?://' . preg_quote( Host::name(), '#' ) . '#i', self::stored( $option ), $text );
	}
}
