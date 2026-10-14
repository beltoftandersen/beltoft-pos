<?php

use BeltoftPos\Catalog\AddToCartUrl;
use BeltoftPos\Catalog\CartNewestFirst;
use BeltoftPos\Catalog\FilterCounts;
use BeltoftPos\Catalog\RelevanssiSearch;
use BeltoftPos\Support\Host;

/** The smaller B2B list behaviours, carried over to the till. */
class PosListBehaviourTest extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_rows_rendered_over_rest_link_back_to_the_page_they_are_on() {
		$this->assertSame(
			'https://pos.example/caixa/?add-to-cart=5&quantity=2',
			AddToCartUrl::rebase( 'https://pos.example/wp-json/bricks/v1/query_result?add-to-cart=5&quantity=2&junk=1', 'https://pos.example/caixa/' )
		);
		$this->assertSame( 'https://x/no-cart/', AddToCartUrl::rebase( 'https://x/no-cart/', 'https://pos.example/caixa/' ) );
	}

	public function test_the_newest_cart_line_comes_first_at_the_till() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$cart = array( 'a' => 1, 'b' => 2 );

		$this->assertSame( array( 'b' => 2, 'a' => 1 ), CartNewestFirst::newest_first( $cart, (object) array( 'object_type' => 'wooCart' ) ) );
		$this->assertSame( $cart, CartNewestFirst::newest_first( $cart, (object) array( 'object_type' => 'post' ) ) );

		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( $cart, CartNewestFirst::newest_first( $cart, (object) array( 'object_type' => 'wooCart' ) ) );
	}

	public function test_filter_counts_are_hidden_at_the_till() {
		$_SERVER['HTTP_HOST'] = Host::name();

		$this->assertTrue( FilterCounts::hide( array(), (object) array( 'name' => 'filter-checkbox' ) )['filterHideCount'] );
		$this->assertSame( array(), FilterCounts::hide( array(), (object) array( 'name' => 'heading' ) ) );

		$_SERVER['HTTP_HOST'] = 'example.org';
		$this->assertSame( array(), FilterCounts::hide( array(), (object) array( 'name' => 'filter-checkbox' ) ) );
	}

	public function test_search_goes_through_relevanssi_when_it_is_there() {
		$_SERVER['HTTP_HOST'] = Host::name();
		$vars = array( 's' => 'OCMB_M' );

		if ( function_exists( 'relevanssi_do_query' ) ) {
			$this->assertTrue( RelevanssiSearch::use_relevanssi( $vars )['relevanssi'] );
		} else {
			$this->assertSame( $vars, RelevanssiSearch::use_relevanssi( $vars ), 'Without Relevanssi nothing changes.' );
		}

		$this->assertSame( array(), RelevanssiSearch::use_relevanssi( array() ) );
	}
}
