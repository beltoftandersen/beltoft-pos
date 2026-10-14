<?php

use BeltoftPos\Stock\AdminField;
use BeltoftPos\Stock\Pool;
use BeltoftPos\Stock\Recount;

class PosStockAdminTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_POST[ AdminField::FIELD ] );
		parent::tearDown();
	}

	public function test_saving_a_count_and_clearing_it() {
		$p  = new WC_Product_Simple();
		$id = $p->save();

		$_POST[ AdminField::FIELD ] = '7';
		AdminField::save_simple( wc_get_product( $id ) );
		$this->assertSame( '7', get_post_meta( $id, Pool::META, true ) );

		$_POST[ AdminField::FIELD ] = '';
		AdminField::save_simple( wc_get_product( $id ) );
		$this->assertSame( '', get_post_meta( $id, Pool::META, true ), 'Blank is never counted, not zero.' );
	}

	public function test_saving_a_variation_count() {
		$parent    = ( new WC_Product_Variable() )->save();
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent );
		$vid = $variation->save();

		$_POST[ AdminField::VARIATION_FIELD ] = array( 3 => '-1' );
		AdminField::save_variation( $vid, 3 );

		$this->assertSame( '-1', get_post_meta( $vid, Pool::META, true ) );
	}

	public function test_recount_lists_parents_of_shelves_below_zero() {
		$simple = ( new WC_Product_Simple() )->save();
		$ok     = ( new WC_Product_Simple() )->save();
		$parent = ( new WC_Product_Variable() )->save();
		$var    = new WC_Product_Variation();
		$var->set_parent_id( $parent );
		$vid = $var->save();

		update_post_meta( $simple, Pool::META, -3 );
		update_post_meta( $ok, Pool::META, 4 );
		update_post_meta( $vid, Pool::META, -1 );

		$this->assertEqualsCanonicalizing( array( $simple, $vid ), Recount::below_zero() );
		$this->assertEqualsCanonicalizing( array( $simple, $parent ), Recount::products_to_recount() );
	}
}
