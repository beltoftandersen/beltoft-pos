<?php

use BeltoftPos\Host\Gate;
use BeltoftPos\Support\Host;

class PosGateTest extends WP_UnitTestCase {

	private WP_User $operator;
	private WP_User $manager;
	private WP_User $customer;

	public function setUp(): void {
		parent::setUp();
		$this->operator = get_user_by( 'id', self::factory()->user->create( array( 'role' => 'pos_operator' ) ) );
		$this->manager  = get_user_by( 'id', self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->customer = get_user_by( 'id', self::factory()->user->create( array( 'role' => 'customer' ) ) );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_logged_out_visitors_reach_only_the_login_page() {
		$this->assertSame( Gate::ALLOW, Gate::decide( '/caixa-entrar/', null ) );
		$this->assertSame( Gate::ALLOW, Gate::decide( '/Caixa-Entrar', null ), 'Case and trailing slash.' );
		$this->assertSame( Gate::ALLOW, Gate::decide( '/robots.txt', null ) );
		$this->assertSame( Gate::LOGIN, Gate::decide( '/', null ) );
		$this->assertSame( Gate::LOGIN, Gate::decide( '/caixa/', null ) );
		$this->assertSame( Gate::LOGIN, Gate::decide( '/caixa-entrar-falso/', null ), 'A prefix is not the page.' );
	}

	public function test_favicon_is_public() {
		$this->assertSame( Gate::ALLOW, Gate::decide( '/favicon.ico', null ) );
	}

	/** As on the B2B host: an open path may not be used to select other content. */
	public function test_the_login_page_cannot_be_used_to_reach_other_content() {
		$this->assertSame( Gate::ALLOW, Gate::decide( '/caixa-entrar/', null, array( 'refused' => '1' ) ) );
		$this->assertSame( Gate::LOGIN, Gate::decide( '/caixa-entrar/', null, array( 'page_id' => '5' ) ) );
		$this->assertSame( Gate::LOGIN, Gate::decide( '/caixa-entrar/', null, array( 'p' => '5' ) ) );
		$this->assertSame( Gate::LOGIN, Gate::decide( '/caixa-entrar/', null, array( 'rest_route' => '/wc/store/v1/cart' ) ) );
		$this->assertSame( Gate::LOGIN, Gate::decide( '/robots.txt', null, array( 's' => 'x' ) ) );
	}

	public function test_till_users_get_in_and_skip_the_login_page() {
		$this->assertSame( Gate::ALLOW, Gate::decide( '/', $this->operator ) );
		$this->assertSame( Gate::ALLOW, Gate::decide( '/caixa/', $this->manager ) );
		$this->assertSame( Gate::HOME, Gate::decide( '/caixa-entrar/', $this->operator ) );
	}

	public function test_a_customer_is_refused_then_can_reach_the_login_page() {
		$this->assertSame( Gate::REFUSE, Gate::decide( '/', $this->customer ) );
		$this->assertSame( Gate::REFUSE, Gate::decide( '/caixa-entrar/', $this->customer ) );
		// After the refusal logs them out, the login page loads - no loop.
		$this->assertSame( Gate::ALLOW, Gate::decide( '/caixa-entrar/', null ) );
	}

	/**
	 * The theme hooks wp_logout to redirect to the shop's sign-in and exit,
	 * which swallowed the till's refusal notice and sent its logout the long
	 * way round. On the POS host that one callback is taken off; every other
	 * logout handler still runs.
	 */
	public function test_on_the_pos_host_logout_skips_the_shop_redirect_but_not_other_handlers() {
		if ( ! function_exists( 'ps_redirect_after_logout' ) ) {
			eval( 'function ps_redirect_after_logout() { throw new RuntimeException( "shop logout redirect ran" ); }' );
		}
		add_action( 'wp_logout', 'ps_redirect_after_logout' );

		$_SERVER['HTTP_HOST'] = Host::name();
		Gate::calm_logout();
		$this->assertFalse( has_action( 'wp_logout', 'ps_redirect_after_logout' ) );

		wp_set_current_user( $this->customer->ID );
		Gate::sign_out();

		$this->assertSame( 0, get_current_user_id() );
		// The wp_logout() path itself (headers not yet sent) is checked on
		// staging: PHPUnit has always sent its headers by now.
	}

	public function test_other_hosts_keep_the_shop_logout_redirect() {
		if ( ! function_exists( 'ps_redirect_after_logout' ) ) {
			eval( 'function ps_redirect_after_logout() { throw new RuntimeException( "shop logout redirect ran" ); }' );
		}
		add_action( 'wp_logout', 'ps_redirect_after_logout' );
		$_SERVER['HTTP_HOST'] = 'example.org';

		Gate::calm_logout();

		$this->assertSame( 10, has_action( 'wp_logout', 'ps_redirect_after_logout' ) );
		remove_action( 'wp_logout', 'ps_redirect_after_logout' );
	}

	public function test_api_calls() {
		$this->assertSame( 401, Gate::api_status( null ) );
		$this->assertSame( 401, Gate::api_status( new WP_User( 0 ) ) );
		$this->assertSame( 403, Gate::api_status( $this->customer ) );
		$this->assertSame( 0, Gate::api_status( $this->operator ) );
	}

	public function test_rest_is_closed_to_all_but_till_users_on_the_pos_host() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$error = Gate::guard_rest( null );
		$this->assertWPError( $error );
		$this->assertSame( 401, $error->get_error_data()['status'] );

		wp_set_current_user( $this->customer->ID );
		$this->assertSame( 403, Gate::guard_rest( null )->get_error_data()['status'] );

		wp_set_current_user( $this->operator->ID );
		$this->assertNull( Gate::guard_rest( null ) );

		$_SERVER['HTTP_HOST'] = 'example.org';
		wp_set_current_user( 0 );
		$this->assertNull( Gate::guard_rest( null ), 'Other hosts are not ours to close.' );
	}

	/** Admins must sign in at wp-login.php (beltoft-hardening, for two-factor), so it stays open. */
	public function test_wp_login_is_open_for_sign_in_reset_and_logout_only() {
		foreach ( array( 'login', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'logout' ) as $action ) {
			$this->assertTrue( Gate::login_action_allowed( $action ), $action );
		}

		$this->assertFalse( Gate::login_action_allowed( 'register' ) );
		$this->assertFalse( Gate::login_action_allowed( 'confirm_admin_email' ) );
	}

	/** Bricks reads wp_login_url() to recognise wp-login.php; changing it sent the till to /registar/. */
	public function test_the_wordpress_login_url_is_left_alone_on_the_pos_host() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$this->assertStringContainsString( '/wp-login.php', wp_login_url() );
	}

	public function test_signing_in_on_the_pos_host_lands_on_the_till_not_the_admin() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$this->assertSame( home_url( '/' ), Gate::after_login( admin_url(), '' ) );
		$this->assertSame( home_url( '/' ), Gate::after_login( admin_url(), admin_url( 'index.php' ) ) );
		$this->assertSame( home_url( '/caixa/' ), Gate::after_login( home_url( '/caixa/' ), home_url( '/caixa/' ) ), 'An explicit till page is kept.' );

		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( admin_url(), Gate::after_login( admin_url(), '' ) );
	}

	public function test_elsewhere_till_pages_and_operators_go_to_the_pos_host() {
		$this->assertSame( Gate::TO_POS, Gate::decide_elsewhere( true, null, false ), 'The till page never renders as retail.' );
		$this->assertSame( Gate::ALLOW, Gate::decide_elsewhere( true, $this->manager, true ), 'Bricks edits it on retail.' );
		$this->assertSame( Gate::TO_POS, Gate::decide_elsewhere( false, $this->operator, false ) );
		$this->assertSame( Gate::ALLOW, Gate::decide_elsewhere( false, $this->manager, false ), 'Staff also use the shop.' );
		$this->assertSame( Gate::ALLOW, Gate::decide_elsewhere( false, $this->customer, false ) );
		$this->assertSame( Gate::ALLOW, Gate::decide_elsewhere( false, null, false ) );
	}

	public function test_logout_leads_to_the_till_login_on_the_pos_host() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$this->assertSame( home_url( '/caixa-entrar/' ), Gate::logout_redirect( 'https://x/' ) );

		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( 'https://x/', Gate::logout_redirect( 'https://x/' ) );
	}
}
