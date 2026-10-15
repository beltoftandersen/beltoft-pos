<?php

namespace BeltoftPos\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The only place in this plugin that knows the till's hostname.
 *
 * One WordPress install answers on several hosts; the host decides which
 * interface a request gets. The till's is set per environment with the
 * BELTOFT_POS_HOST constant in wp-config.php; the beltoft_pos_host filter can
 * still override it.
 *
 * NO BUILT-IN DEFAULT. It used to fall back to staging's hostname, so a
 * deploy without the constant quietly treated another environment's host as
 * its own - and sent operators and the till pages there. Unset, there is no
 * till host: nothing is gated, nothing redirects, and admins get a notice.
 */
class Host {

	/**
	 * Asked thousands of times on a busy shop page - every URL WordPress
	 * builds and every stock read passes a host check - so the answer is kept
	 * for the request. Only while nothing filters it: a filter can change its
	 * answer at any moment, so then it is asked every time.
	 */
	private static ?string $name = null;

	/** @var array<string, string> raw HTTP_HOST => normalised */
	private static array $normalised = array();

	public static function name(): string {
		if ( null !== self::$name && ! has_filter( 'beltoft_pos_host' ) ) {
			return self::$name;
		}

		$host = defined( 'BELTOFT_POS_HOST' ) ? (string) BELTOFT_POS_HOST : '';
		$name = self::normalise( (string) apply_filters( 'beltoft_pos_host', $host ) );

		if ( ! has_filter( 'beltoft_pos_host' ) ) {
			self::$name = $name;
		}

		return $name;
	}

	public static function is_configured(): bool {
		return '' !== self::name();
	}

	/** This request's host, or '' for cron, WP-CLI and queued jobs. */
	public static function current(): string {
		$raw = (string) ( $_SERVER['HTTP_HOST'] ?? '' );

		return self::$normalised[ $raw ] ??= self::normalise( $raw );
	}

	/**
	 * Case and port are normalised away: HTTP_HOST carries the port on
	 * non-standard ports, and a raw comparison would silently fall through
	 * to the retail behaviour on the till.
	 */
	public static function is_pos_host( ?string $host = null ): bool {
		$host = null === $host ? self::current() : self::normalise( $host );

		if ( '' === $host ) {
			return false;
		}

		$name = self::name();

		return '' !== $name && $host === $name;
	}

	public static function url( string $path = '/' ): string {
		return 'https://' . self::name() . '/' . ltrim( $path, '/' );
	}

	private static function normalise( string $host ): string {
		// A fully qualified name may end in a dot ("pos.example.com.").
		return rtrim( explode( ':', strtolower( trim( $host ) ) )[0], '.' );
	}
}
