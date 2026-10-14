<?php

namespace BeltoftPos\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The only place in this plugin that knows the till's hostname.
 *
 * One WordPress install answers on several hosts; the host decides which
 * interface a request gets. The till's is set per environment: the
 * BELTOFT_POS_HOST constant in wp-config.php (production), else the
 * beltoft_pos_host filter, else staging's.
 */
class Host {

	public const DEFAULT_HOST = 'pos.staging.mindthetrash.pt';

	public static function name(): string {
		$host = defined( 'BELTOFT_POS_HOST' )
			? (string) BELTOFT_POS_HOST
			: (string) apply_filters( 'beltoft_pos_host', self::DEFAULT_HOST );

		return self::normalise( $host );
	}

	/** This request's host, or '' for cron, WP-CLI and queued jobs. */
	public static function current(): string {
		return self::normalise( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );
	}

	/**
	 * Case and port are normalised away: HTTP_HOST carries the port on
	 * non-standard ports, and a raw comparison would silently fall through
	 * to the retail behaviour on the till.
	 */
	public static function is_pos_host( ?string $host = null ): bool {
		$host = null === $host ? self::current() : self::normalise( $host );

		return '' !== $host && $host === self::name();
	}

	public static function url( string $path = '/' ): string {
		return 'https://' . self::name() . '/' . ltrim( $path, '/' );
	}

	private static function normalise( string $host ): string {
		// A fully qualified name may end in a dot ("pos.example.com.").
		return rtrim( explode( ':', strtolower( trim( $host ) ) )[0], '.' );
	}
}
