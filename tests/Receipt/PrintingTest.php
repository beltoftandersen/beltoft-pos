<?php

use BeltoftPos\Receipt\Printing;

/**
 * Receipt printing is off unless switched on in code. The receipt's
 * "Imprimir" button renders only when {beltoft_pos_printing} is 1.
 */
class PosPrintingTest extends WP_UnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'beltoft_pos_printing' );
		parent::tearDown();
	}

	public function test_printing_is_off_by_default() {
		$this->assertFalse( Printing::enabled() );
		$this->assertSame( '0', Printing::render_tag( '{beltoft_pos_printing}' ) );
		$this->assertSame( 'on: 0', Printing::render_content( 'on: {beltoft_pos_printing}' ) );
	}

	public function test_printing_can_be_switched_on_in_code() {
		add_filter( 'beltoft_pos_printing', '__return_true' );

		$this->assertTrue( Printing::enabled() );
		$this->assertSame( '1', Printing::render_tag( 'beltoft_pos_printing' ) );
	}

	public function test_other_tags_are_left_alone() {
		$this->assertSame( '{post_title}', Printing::render_tag( '{post_title}' ) );
		$this->assertSame( 'plain', Printing::render_content( 'plain' ) );
	}

	public function test_the_tag_is_offered_in_the_builder() {
		$names = array_column( Printing::add_tag( array() ), 'name' );
		$this->assertContains( '{beltoft_pos_printing}', $names );
	}
}
