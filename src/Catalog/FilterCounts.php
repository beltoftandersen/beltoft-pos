<?php

namespace BeltoftPos\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * No counts on the till's filters - as on the B2B list.
 *
 * A count is a count of PRODUCTS and the list shows ROWS: VariationRows gives
 * a variable product one row per variation, so "(7)" sits above nine lines.
 * Switched off with Bricks' own setting (`filterHideCount`, tested with
 * isset()), which both the page render and the filters' REST re-render read.
 */
class FilterCounts {

	private const COUNTED = array( 'filter-checkbox', 'filter-radio', 'filter-select' );

	public static function register(): void {
		add_filter( 'bricks/element/settings', array( __CLASS__, 'hide' ), 10, 2 );
	}

	/**
	 * @param mixed $settings
	 * @param mixed $element
	 * @return mixed
	 */
	public static function hide( $settings, $element = null ) {
		if ( ! is_array( $settings ) || ! is_object( $element ) || ! VariationRows::expands_rows() ) {
			return $settings;
		}

		if ( ! in_array( (string) ( $element->name ?? '' ), self::COUNTED, true ) ) {
			return $settings;
		}

		$settings['filterHideCount'] = true;

		return $settings;
	}
}
