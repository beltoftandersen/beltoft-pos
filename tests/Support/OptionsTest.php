<?php

use BeltoftPos\Support\Installer;
use BeltoftPos\Support\Options;

class PosOptionsTest extends WP_UnitTestCase {

	public function test_page_ids_count_only_when_published_pages() {
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'caixa' ) );
		update_option( Options::PAGE, $page );
		$this->assertSame( $page, Options::page_id() );

		wp_update_post( array( 'ID' => $page, 'post_status' => 'draft' ) );
		$this->assertSame( 0, Options::page_id(), 'A draft must not become the checkout.' );

		$post = self::factory()->post->create();
		update_option( Options::PAGE, $post );
		$this->assertSame( 0, Options::page_id(), 'Only a page.' );
	}

	public function test_login_path_follows_the_configured_page_or_falls_back_to_the_slug() {
		$this->assertSame( '/caixa-entrar/', Options::login_path() );

		$login = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'acesso-caixa' ) );
		update_option( Options::LOGIN_PAGE, $login );
		$this->assertSame( '/acesso-caixa/', Options::login_path() );
	}

	public function test_walk_in_defaults_and_overrides() {
		$this->assertSame( 'Walk-in customer', Options::walkin_name() );
		$this->assertSame( 'encomendas@mindthetrash.pt', Options::walkin_email() );

		update_option( Options::WALKIN_NAME, 'Balcão' );
		update_option( Options::WALKIN_EMAIL, 'loja@example.test' );
		$this->assertSame( 'Balcão', Options::walkin_name() );
		$this->assertSame( 'loja@example.test', Options::walkin_email() );
	}

	public function test_the_role_is_installed_with_customer_capabilities() {
		remove_role( Installer::ROLE );
		Installer::install();

		$role = get_role( Installer::ROLE );
		$this->assertNotNull( $role );
		$this->assertSame( get_role( 'customer' )->capabilities, $role->capabilities );
		$this->assertSame( BELTOFT_POS_VERSION, get_option( Installer::VERSION_OPTION ) );

		Installer::install();
		$this->assertNotNull( get_role( Installer::ROLE ), 'Idempotent.' );
	}
}
