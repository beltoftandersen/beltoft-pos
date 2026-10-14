<?php

namespace BeltoftPos\Catalog;

use BeltoftPos\Support\Host;

defined( 'ABSPATH' ) || exit;

/**
 * The till's search goes through Relevanssi when it is installed - as on the
 * B2B list.
 *
 * Relevanssi only takes over a query that is a search AND the main query,
 * unless the query opts in. Bricks' filter-search builds its own WP_Query
 * inside an AJAX endpoint, which is neither, so it fell through to core's
 * search - title and content only - and a SKU found nothing although it was
 * in the index. Without Relevanssi this does nothing.
 */
class RelevanssiSearch {

	private const PRIORITY = 1000;

	public static function register(): void {
		add_filter( 'bricks/posts/query_vars', array( __CLASS__, 'use_relevanssi' ), self::PRIORITY, 1 );
	}

	/**
	 * @param mixed $query_vars
	 * @return mixed
	 */
	public static function use_relevanssi( $query_vars ) {
		if ( ! is_array( $query_vars ) || empty( $query_vars['s'] ) || ! function_exists( 'relevanssi_do_query' ) || ! Host::is_pos_host() ) {
			return $query_vars;
		}

		$query_vars['relevanssi'] = true;

		return $query_vars;
	}
}
