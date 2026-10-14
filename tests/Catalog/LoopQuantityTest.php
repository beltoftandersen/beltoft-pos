<?php

use BeltoftPos\Catalog\LoopQuantity;
use BeltoftPos\Support\Host;

/** Like the B2B list: a quantity beside every row's add button. */
class PosLoopQuantityTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	private function simple(): WC_Product {
		$p = new WC_Product_Simple();
		$p->set_regular_price( '2' );
		$p->save();

		return $p;
	}

	public function test_a_simple_row_gets_a_quantity_before_its_button() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$html = LoopQuantity::prepend_quantity( '<a class="button">Add</a>', $this->simple() );

		$this->assertStringContainsString( 'beltoft-loop-qty__input', $html );
		$this->assertStringEndsWith( '<a class="button">Add</a>', $html );
	}

	public function test_a_variation_row_gets_its_own_line_control() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$parent = ( new WC_Product_Variable() )->save();
		$v      = new WC_Product_Variation();
		$v->set_parent_id( $parent );
		$v->set_regular_price( '3' );
		$v->save();
		$v = wc_get_product( $v->get_id() ); // as a row loads it, with its parent's data

		$html = LoopQuantity::prepend_quantity( '<a>link to product page</a>', $v );

		$this->assertStringContainsString( 'beltoft-loop-variable__add', $html );
		$this->assertStringContainsString( 'data-product_id="' . $v->get_id() . '"', $html );
		$this->assertStringNotContainsString( 'link to product page', $html );
	}

	public function test_other_hosts_are_untouched() {
		$_SERVER['HTTP_HOST'] = 'example.org';

		$this->assertSame( '<a>x</a>', LoopQuantity::prepend_quantity( '<a>x</a>', $this->simple() ) );
	}
}
