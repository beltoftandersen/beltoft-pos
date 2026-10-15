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
 * must never render there as shop pages; and a till account has no login
 * there at all - it is refused at sign-in and an existing session counts as
 * signed out.
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
	private const OPEN_PATHS = array( '/robots.txt', '/favicon.ico' );

	/**
	 * Query vars that make WordPress serve something other than the path -
	 * /caixa-entrar/?page_id=5 renders page 5. An open path carrying one of
	 * these is not open (the same list as the B2B host's gate).
	 */
	private const CONTENT_VARS = array(
		'p', 'page_id', 'pagename', 'name', 'attachment', 'attachment_id', 'subpost', 'subpost_id',
		'preview', 'post_type', 'static', 'pb', 'embed',
		's', 'cat', 'category_name', 'tag', 'tag_id', 'taxonomy', 'term', 'author', 'author_name',
		'feed', 'm', 'year', 'monthnum', 'day', 'w', 'sitemap', 'post_format',
		'product', 'product_cat', 'product_tag', 'product_brand',
		'rest_route', 'wc-ajax',
	);

	/**
	 * wp-login.php screens that stay open on the POS host. Sign-in among them:
	 * beltoft-hardening lets administrators sign in only there (the two-factor
	 * step lives on it), so the till's own login form cannot serve them.
	 */
	private const LOGIN_ACTIONS = array( 'login', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'logout' );

	/**
	 * Request keys WooCommerce acts on during wp_loaded - before any page gate
	 * runs - to change a cart or place an order.
	 */
	private const FORM_ACTIONS = array( 'add-to-cart', 'remove_item', 'undo_item', 'update_cart', 'apply_coupon', 'order_again', 'woocommerce-process-checkout-nonce', 'woocommerce-pay-nonce' );

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
		add_action( 'wp_loaded', array( __CLASS__, 'guard_forms' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'guard_admin' ), 1 );

		// After core's cookie check (100), which may still reset the user.
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'guard_rest' ), 101 );
		add_action( 'login_init', array( __CLASS__, 'guard_login' ), 1 );
		add_action( 'init', array( __CLASS__, 'calm_logout' ), 20 );
		add_filter( 'login_redirect', array( __CLASS__, 'after_login' ), 10, 2 );
		add_filter( 'logout_redirect', array( __CLASS__, 'logout_redirect' ) );

		/*
		 * wp_login_url() is deliberately NOT filtered. Bricks recognises
		 * wp-login.php by comparing against it; pointed at the till's login,
		 * it took wp-login.php for the registration URL and sent it to the
		 * shop's sign-up page.
		 */
	}

	/**
	 * The till account's login rules, registered as the plugin file loads -
	 * not in register(), which runs on plugins_loaded 20: other plugins ask
	 * for the current user before that, and WordPress resolves it once.
	 */
	public static function register_early(): void {
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'operator_login_elsewhere' ), 99 );
		add_filter( 'determine_current_user', array( __CLASS__, 'operator_elsewhere' ), 99 );
	}

	public static function decide( string $path, ?WP_User $user, array $query = array() ): string {
		$path     = self::normalise( $path );
		$on_login = $path === Options::login_path();

		if ( $user instanceof WP_User && $user->exists() ) {
			if ( ! Till::is_till_user( $user ) ) {
				return self::REFUSE;
			}

			return $on_login ? self::HOME : self::ALLOW;
		}

		if ( array_intersect( array_keys( $query ), self::CONTENT_VARS ) ) {
			return self::LOGIN;
		}

		if ( $on_login || in_array( untrailingslashit( $path ), self::OPEN_PATHS, true ) ) {
			return self::ALLOW;
		}

		return self::LOGIN;
	}

	/**
	 * Off the POS host the till only looks after its own two pages: they exist
	 * on every host of the install and must not render there. Who may use
	 * another host is that host's business - a till account simply has no
	 * login there (see operator_elsewhere()).
	 */
	public static function decide_elsewhere( bool $is_pos_page, ?WP_User $user, bool $is_builder ): string {
		if ( $is_builder ) {
			return self::ALLOW;
		}

		return $is_pos_page ? self::TO_POS : self::ALLOW;
	}

	/** A till-only account: the till role and nothing that runs the shop. */
	public static function is_operator_only( $user ): bool {
		return $user instanceof WP_User
			&& $user->exists()
			&& in_array( Installer::ROLE, (array) $user->roles, true )
			&& ! user_can( $user, 'manage_woocommerce' );
	}

	/** A web request on a host other than the till's (cron and WP-CLI have no host). */
	private static function elsewhere(): bool {
		return '' !== Host::current() && ! Host::is_pos_host();
	}

	/**
	 * A till account cannot sign in anywhere but the till.
	 *
	 * On wp_authenticate_user, which every username/password sign-in passes
	 * through - wp-login.php, the B2B and shop login forms, WooCommerce's
	 * account login - after the password is known to be right, so the refusal
	 * says nothing about whether it was.
	 *
	 * @param mixed $user
	 * @return mixed
	 */
	public static function operator_login_elsewhere( $user ) {
		if ( ! self::elsewhere() || ! self::is_operator_only( $user ) ) {
			return $user;
		}

		return new WP_Error( 'beltoft_pos_till_only', __( 'This account can only sign in at the till.', 'beltoft-pos' ) );
	}

	/**
	 * A till session already open on another host counts as signed out there
	 * - a login from before this rule, or one carried over by any other route.
	 *
	 * @param mixed $user_id
	 * @return mixed
	 */
	public static function operator_elsewhere( $user_id ) {
		if ( ! $user_id || ! self::elsewhere() ) {
			return $user_id;
		}

		return self::is_operator_only( get_userdata( (int) $user_id ) ) ? 0 : $user_id;
	}

	/** Whether a cart/checkout form action in $request must be refused for $user. */
	public static function form_action_blocked( array $request, ?WP_User $user ): bool {
		if ( ! array_intersect( array_keys( $request ), self::FORM_ACTIONS ) ) {
			return false;
		}

		return 0 !== self::api_status( $user );
	}

	public static function guard_forms(): void {
		if ( ! Host::is_pos_host() || wp_doing_ajax() || is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only the keys are read, to refuse.
		if ( self::form_action_blocked( (array) $_REQUEST, wp_get_current_user() ) ) {
			wp_safe_redirect( Options::login_url(), 302 );
			exit;
		}
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
			// No till host configured: nowhere to send anyone.
			if ( ! Host::is_configured() ) {
				return;
			}

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

		$query = array();
		parse_str( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY ), $query );

		$decision = self::decide( $path, $user, $query );

		/*
		 * What WordPress actually resolved, not only what the URL says. Query
		 * vars also come from the POST body (WP::parse_request), and Bricks
		 * adds content vars of its own (bricks_template), so a URL check alone
		 * let a logged-out POST of page_id=<till> render the till on the
		 * login path. Logged out, the only page that renders is the login page.
		 */
		if ( self::ALLOW === $decision && ! $user->exists() && ! self::resolved_login_page() && ! in_array( untrailingslashit( self::normalise( $path ) ), self::OPEN_PATHS, true ) ) {
			$decision = self::LOGIN;
		}

		switch ( $decision ) {
			case self::LOGIN:
				/*
				 * With no published login page (not replayed yet, or put back
				 * to draft) the login URL is this request again: say so rather
				 * than redirect to it forever.
				 */
				if ( ! Options::login_page_id() && self::normalise( $path ) === Options::login_path() ) {
					wp_die( esc_html__( 'The till is not set up yet: its login page is missing.', 'beltoft-pos' ), '', array( 'response' => 503 ) );
				}

				wp_safe_redirect( Options::login_url(), 302 );
				exit;

			case self::REFUSE:
				self::sign_out();
				wp_safe_redirect( add_query_arg( 'refused', '1', Options::login_url() ), 302 );
				exit;

			case self::HOME:
				wp_safe_redirect( home_url( '/' ), 302 );
				exit;
		}
	}

	/**
	 * Whether this request resolved to the till's login page itself, and
	 * nothing else.
	 *
	 * is_page() is not enough: with the login path AND a page_id (from the
	 * POST body), WP_Query takes the queried object from the path but selects
	 * the post by page_id, so is_page( login ) was true while the loop held
	 * the till. The post actually loaded has to be the login page too.
	 */
	public static function resolved_login_page(): bool {
		global $wp_query;

		$login = Options::login_page_id();

		if ( ! $login || ! is_page( $login ) || is_feed() || is_embed() || is_preview() ) {
			return false;
		}

		$posts = $wp_query instanceof \WP_Query ? (array) $wp_query->posts : array();

		return 1 === count( $posts ) && $login === (int) ( $posts[0]->ID ?? 0 );
	}

	/** A request parameter as a string; anything else (an array, say) is empty. */
	private static function request_string( string $key, string $default = '' ): string {
		$value = $_REQUEST[ $key ] ?? $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Take the shop's logout redirect off the POS host.
	 *
	 * The theme hooks `wp_logout` to send everyone to the shop's own sign-in
	 * page and exit. On the till that swallowed the refusal notice and sent
	 * the header's logout the long way round (shop sign-in, then the gate).
	 * Only that one callback is removed - every other logout handler runs -
	 * and only on the POS host.
	 */
	public static function calm_logout(): void {
		if ( Host::is_pos_host() && function_exists( 'ps_redirect_after_logout' ) ) {
			remove_action( 'wp_logout', 'ps_redirect_after_logout' );
		}
	}

	/** Sign a refused account out - WordPress's own logout, minus the shop's redirect. */
	public static function sign_out(): void {
		self::calm_logout();

		if ( headers_sent() ) {
			// Too late for cookies - logout handlers would only fail to set
			// theirs - but the session still ends on the server.
			wp_destroy_current_session();
			wp_set_current_user( 0 );

			return;
		}

		wp_logout();
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
			$action = sanitize_key( self::request_string( 'action' ) );

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

		$action = self::request_string( 'action', 'login' );

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
