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

	public function test_wp_login_only_for_password_reset_and_logout() {
		foreach ( array( 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'logout' ) as $action ) {
			$this->assertTrue( Gate::login_action_allowed( $action ), $action );
		}

		$this->assertFalse( Gate::login_action_allowed( 'login' ) );
		$this->assertFalse( Gate::login_action_allowed( 'register' ) );
	}

	public function test_elsewhere_till_pages_and_operators_go_to_the_pos_host() {
		$this->assertSame( Gate::TO_POS, Gate::decide_elsewhere( true, null, false ), 'The till page never renders as retail.' );
		$this->assertSame( Gate::ALLOW, Gate::decide_elsewhere( true, $this->manager, true ), 'Bricks edits it on retail.' );
		$this->assertSame( Gate::TO_POS, Gate::decide_elsewhere( false, $this->operator, false ) );
		$this->assertSame( Gate::ALLOW, Gate::decide_elsewhere( false, $this->manager, false ), 'Staff also use the shop.' );
		$this->assertSame( Gate::ALLOW, Gate::decide_elsewhere( false, $this->customer, false ) );
		$this->assertSame( Gate::ALLOW, Gate::decide_elsewhere( false, null, false ) );
	}

	public function test_login_and_logout_lead_to_the_till_login_on_the_pos_host() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$this->assertSame( home_url( '/caixa-entrar/' ), Gate::login_url( 'https://x/wp-login.php' ) );
		$this->assertSame( home_url( '/caixa-entrar/' ), Gate::logout_redirect( 'https://x/' ) );

		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( 'https://x/wp-login.php', Gate::login_url( 'https://x/wp-login.php' ) );
	}
}
