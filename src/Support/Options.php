<?php

namespace BeltoftPos\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The till's settings, all plain options set on deploy (see the staging
 * migration changelog), so nothing here is hard-coded to one site.
 */
class Options {

	public const PAGE = 'beltoft_pos_page_id';

	public const LOGIN_PAGE = 'beltoft_pos_login_page_id';

	public const WALKIN_NAME = 'beltoft_pos_walkin_name';

	public const WALKIN_EMAIL = 'beltoft_pos_walkin_email';

	/** Where the login lives before its page is configured. */
	public const LOGIN_SLUG = 'caixa-entrar';

	public static function page_id(): int {
		return self::published_page( self::PAGE );
	}

	public static function login_page_id(): int {
		return self::published_page( self::LOGIN_PAGE );
	}

	public static function login_path(): string {
		$id = self::login_page_id();

		return '/' . ( $id ? trim( (string) get_page_uri( $id ), '/' ) : self::LOGIN_SLUG ) . '/';
	}

	public static function login_url(): string {
		return home_url( self::login_path() );
	}

	/** The name a sale carries when nobody gives one. */
	public static function walkin_name(): string {
		$name = (string) get_option( self::WALKIN_NAME, '' );

		return '' !== $name ? $name : __( 'Walk-in customer', 'beltoft-pos' );
	}

	/**
	 * Where a counter sale's paperwork goes when the customer gives no email:
	 * the shop's own inbox, never the operator's account address.
	 */
	public static function walkin_email(): string {
		$email = (string) get_option( self::WALKIN_EMAIL, '' );

		return '' !== $email ? $email : 'encomendas@mindthetrash.pt';
	}

	/**
	 * A trashed, drafted or deleted page counts as unset, so the till falls
	 * back to WooCommerce's own pages instead of a 404 with a full cart.
	 */
	private static function published_page( string $option ): int {
		$id = (int) get_option( $option, 0 );

		return $id && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
	}
}
