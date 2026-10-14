<?php

use BeltoftPos\Catalog\VariationRows;
use BeltoftPos\Support\Host;

/** Like the B2B list: a variable product is listed as its variations, never as itself. */
class PosVariationRowsTest extends WP_UnitTestCase {

	private int $parent;
	private int $medium;
	private int $unpriced;
	private int $simple;

	public function setUp(): void {
		parent::setUp();

		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Tamanho' );
		$attribute->set_options( array( 'M', 'L' ) );
		$attribute->set_variation( true );

		$variable = new WC_Product_Variable();
		$variable->set_name( 'Saco de rede' );
		$variable->set_attributes( array( $attribute ) );
		$this->parent = $variable->save();

		$m = new WC_Product_Variation();
		$m->set_parent_id( $this->parent );
		$m->set_attributes( array( 'tamanho' => 'M' ) );
		$m->set_regular_price( '3.60' );
		$m->set_sku( 'OCMB_M' );
		$this->medium = $m->save();

		$l = new WC_Product_Variation();
		$l->set_parent_id( $this->parent );
		$l->set_attributes( array( 'tamanho' => 'L' ) );
		$this->unpriced = $l->save(); // no price: not purchasable

		$this->simple = ( new WC_Product_Simple() )->save();
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	private function result( string $search = '' ): WP_Query {
		$q        = new WP_Query();
		$q->query_vars['s'] = $search;
		$q->posts = array( get_post( $this->parent ), get_post( $this->simple ) );
		$q->post_count = 2;

		return $q;
	}

	private function ids( WP_Query $q ): array {
		return array_map( fn( $p ) => $p->ID, $q->posts );
	}

	public function test_a_variable_product_becomes_its_orderable_variations() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$out = VariationRows::expand( $this->result(), (object) array( 'object_type' => 'post' ) );

		$this->assertSame( array( $this->medium, $this->simple ), $this->ids( $out ), 'Unpriced variation left out; simple kept.' );
	}

	public function test_an_exact_sku_narrows_to_that_variation() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$l = wc_get_product( $this->unpriced );
		$l->set_regular_price( '4' );
		$l->save();

		$out = VariationRows::expand( $this->result( 'ocmb_m' ), (object) array( 'object_type' => 'post' ) );

		$this->assertSame( array( $this->medium, $this->simple ), $this->ids( $out ) );
	}

	public function test_a_variation_row_is_titled_parent_and_attributes() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertSame( 'Saco de rede - M', VariationRows::variation_title( 'x', $this->medium ) );
	}

	public function test_other_hosts_are_untouched() {
		$_SERVER['HTTP_HOST'] = 'example.org';

		$out = VariationRows::expand( $this->result(), (object) array( 'object_type' => 'post' ) );
		$this->assertSame( array( $this->parent, $this->simple ), $this->ids( $out ) );
		$this->assertSame( 'x', VariationRows::variation_title( 'x', $this->medium ) );
	}
}
