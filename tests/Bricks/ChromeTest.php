<?php

use BeltoftPos\Bricks\Chrome;
use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

/** The till's own header, like the B2B host's: chosen by slug, on the POS host only. */
class PosChromeTest extends WP_UnitTestCase {

	private int $header;
	private int $login;

	public function setUp(): void {
		parent::setUp();
		$this->header = self::factory()->post->create( array( 'post_type' => 'bricks_template', 'post_name' => Chrome::HEADER_SLUG ) );
		$this->login  = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'caixa-entrar' ) );
		update_option( Options::LOGIN_PAGE, $this->login );
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	private function retail(): array {
		return array( 'header' => 11, 'footer' => 22, 'content' => 33 );
	}

	public function test_the_till_gets_its_own_header_and_no_shop_footer() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$this->go_to( '/?p=' . self::factory()->post->create() );

		$out = Chrome::choose( $this->retail() );

		$this->assertSame( $this->header, $out['header'] );
		$this->assertArrayNotHasKey( 'footer', $out, 'No footer template: the till ends with the counter.' );
		$this->assertSame( 33, $out['content'] );
	}

	public function test_the_login_page_has_no_header_or_footer() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$this->go_to( get_permalink( $this->login ) );

		$out = Chrome::choose( $this->retail() );

		$this->assertArrayNotHasKey( 'header', $out );
		$this->assertArrayNotHasKey( 'footer', $out );
	}

	public function test_without_its_template_the_till_falls_back_to_the_shop_header() {
		$_SERVER['HTTP_HOST'] = Host::name();
		wp_delete_post( $this->header, true );
		$this->go_to( '/?p=' . self::factory()->post->create() );

		$this->assertSame( 11, Chrome::choose( $this->retail() )['header'] );
	}

	public function test_other_hosts_keep_their_templates() {
		$_SERVER['HTTP_HOST'] = 'example.org';

		$this->assertSame( $this->retail(), Chrome::choose( $this->retail() ) );
	}

	public function test_admins_are_told_when_the_till_header_is_missing() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		ob_start();
		Chrome::admin_notice();
		$this->assertSame( '', ob_get_clean(), 'Present: nothing to say.' );

		wp_delete_post( $this->header, true );
		ob_start();
		Chrome::admin_notice();
		$this->assertStringContainsString( Chrome::HEADER_SLUG, ob_get_clean() );
	}
}
