<?php

namespace BeltoftPos\Host;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Installer;
use BeltoftPos\Support\Options;
use BeltoftPos\Support\Till;
use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Who gets into the till, and who is kept on it.
 *
 * On the POS host: logged out, only the login page (and the password-reset
 * screens) load; signed in, only till users stay - anyone else is signed out
 * and told why. Every way in is closed, not only pages: REST, admin-ajax and
 * wc-ajax answer 401/403 too, because the cart and the checkout run on them.
 *
 * Elsewhere: the two till pages exist on every host of this install, and
 * must never render there as shop pages; and an operator account belongs on
 * the till, so it is sent there.
 *
 * The decisions are pure functions of path and user, so they can be tested
 * as a table; the hooks below only carry them out.
 */
class Gate {

	public const ALLOW  = 'allow';
	public const LOGIN  = 'login';
	public const REFUSE = 'refuse';
	public const HOME   = 'home';
	public const TO_POS = 'to_pos';

	/** Served to anyone, because there is nothing behind them. */
	private const OPEN_PATHS = array( '/robots.txt' );

	/**
	 * wp-login.php screens that stay open on the POS host. Sign-in among them:
	 * beltoft-hardening lets administrators sign in only there (the two-factor
	 * step lives on it), so the till's own login form cannot serve them.
	 */
	private const LOGIN_ACTIONS = array( 'login', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'logout' );

	/** The login page's own form submits through admin-ajax while logged out. */
	private const OPEN_AJAX = array( 'bricks_form_submit' );

	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'guard_page' ), 1 );

		/*
		 * wc-ajax (add to cart, order review, checkout) is answered on
		 * template_redirect at priority 0 and exits, before any page gate.
		 * The user is already known on init.
		 */
		add_action( 'init', array( __CLASS__, 'guard_wc_ajax' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'guard_admin' ), 1 );

		// After core's cookie check (100), which may still reset the user.
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'guard_rest' ), 101 );
		add_action( 'login_init', array( __CLASS__, 'guard_login' ), 1 );
		add_filter( 'login_redirect', array( __CLASS__, 'after_login' ), 10, 2 );
		add_filter( 'logout_redirect', array( __CLASS__, 'logout_redirect' ) );

		/*
		 * wp_login_url() is deliberately NOT filtered. Bricks recognises
		 * wp-login.php by comparing against it; pointed at the till's login,
		 * it took wp-login.php for the registration URL and sent it to the
		 * shop's sign-up page.
		 */
	}

	public static function decide( string $path, ?WP_User $user ): string {
		$path     = self::normalise( $path );
		$on_login = $path === Options::login_path();

		if ( $user instanceof WP_User && $user->exists() ) {
			if ( ! Till::is_till_user( $user ) ) {
				return self::REFUSE;
			}

			return $on_login ? self::HOME : self::ALLOW;
		}

		if ( $on_login || in_array( untrailingslashit( $path ), self::OPEN_PATHS, true ) ) {
			return self::ALLOW;
		}

		return self::LOGIN;
	}

	public static function decide_elsewhere( bool $is_pos_page, ?WP_User $user, bool $is_builder ): string {
		if ( $is_builder ) {
			return self::ALLOW;
		}

		if ( $is_pos_page ) {
			return self::TO_POS;
		}

		$operator = $user instanceof WP_User
			&& in_array( Installer::ROLE, (array) $user->roles, true )
			&& ! user_can( $user, 'manage_woocommerce' );

		return $operator ? self::TO_POS : self::ALLOW;
	}

	public static function api_status( ?WP_User $user ): int {
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return 401;
		}

		return Till::is_till_user( $user ) ? 0 : 403;
	}

	public static function login_action_allowed( string $action ): bool {
		return in_array( strtolower( $action ), self::LOGIN_ACTIONS, true );
	}

	public static function guard_page(): void {
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		$user = wp_get_current_user();

		if ( ! Host::is_pos_host() ) {
			$pages       = array_filter( array( Options::page_id(), Options::login_page_id() ) );
			$is_pos_page = $pages && is_page( $pages );
			$is_builder  = isset( $_GET['bricks'] ) || isset( $_GET['brickspreview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only compared.

			if ( self::TO_POS === self::decide_elsewhere( (bool) $is_pos_page, $user, $is_builder ) ) {
				// A different host, which wp_safe_redirect() would refuse.
				wp_redirect( Host::url( $is_pos_page ? $path : '/' ), 302 );
				exit;
			}

			return;
		}

		switch ( self::decide( $path, $user ) ) {
			case self::LOGIN:
				wp_safe_redirect( Options::login_url(), 302 );
				exit;

			case self::REFUSE:
				wp_logout();
				wp_safe_redirect( add_query_arg( 'refused', '1', Options::login_url() ), 302 );
				exit;

			case self::HOME:
				wp_safe_redirect( home_url( '/' ), 302 );
				exit;
		}
	}

	public static function guard_wc_ajax(): void {
		if ( ! Host::is_pos_host() || empty( $_GET['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checked for presence.
			return;
		}

		$status = self::api_status( wp_get_current_user() );

		if ( $status ) {
			wp_send_json( array( 'error' => self::message( $status ) ), $status );
		}
	}

	public static function guard_admin(): void {
		if ( ! Host::is_pos_host() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			$action = sanitize_key( (string) ( $_REQUEST['action'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.

			if ( in_array( $action, self::OPEN_AJAX, true ) ) {
				return;
			}

			$status = self::api_status( wp_get_current_user() );

			if ( $status ) {
				wp_send_json_error( array( 'message' => self::message( $status ) ), $status );
			}

			return;
		}

		// The admin is used on retail, where staff are signed in to it.
		wp_redirect( Urls::stored( 'siteurl' ) . (string) ( $_SERVER['REQUEST_URI'] ?? '/wp-admin/' ), 302 );
		exit;
	}

	/**
	 * @param mixed $result
	 * @return mixed
	 */
	public static function guard_rest( $result ) {
		if ( ! Host::is_pos_host() || is_wp_error( $result ) ) {
			return $result;
		}

		$status = self::api_status( wp_get_current_user() );

		if ( ! $status ) {
			return $result;
		}

		return new WP_Error(
			401 === $status ? 'beltoft_pos_login_required' : 'beltoft_pos_forbidden',
			self::message( $status ),
			array( 'status' => $status )
		);
	}

	public static function guard_login(): void {
		if ( ! Host::is_pos_host() ) {
			return;
		}

		$action = (string) ( $_REQUEST['action'] ?? 'login' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.

		if ( self::login_action_allowed( $action ) ) {
			return;
		}

		wp_safe_redirect( Options::login_url(), 302 );
		exit;
	}

	/**
	 * Signing in on the POS host lands on the till. The default target is the
	 * admin, which lives on retail, where this sign-in does not count.
	 *
	 * @param mixed $redirect_to
	 * @param mixed $requested
	 * @return mixed
	 */
	public static function after_login( $redirect_to, $requested = '' ) {
		if ( ! Host::is_pos_host() ) {
			return $redirect_to;
		}

		$requested = is_string( $requested ) ? $requested : '';

		if ( '' === $requested || false !== strpos( $requested, '/wp-admin' ) ) {
			return home_url( '/' );
		}

		return $redirect_to;
	}

	/**
	 * @param mixed $url
	 * @return mixed
	 */
	public static function logout_redirect( $url ) {
		return Host::is_pos_host() ? Options::login_url() : $url;
	}

	private static function message( int $status ): string {
		return 401 === $status
			? __( 'Please sign in to the till.', 'beltoft-pos' )
			: __( 'This account has no access to the till.', 'beltoft-pos' );
	}

	/** Lower case, decoded, one leading and one trailing slash - as Options::login_path() is. */
	private static function normalise( string $path ): string {
		$path = strtolower( rawurldecode( $path ) );

		return '/' . trim( $path, '/' ) . ( '' === trim( $path, '/' ) ? '' : '/' );
	}
}
