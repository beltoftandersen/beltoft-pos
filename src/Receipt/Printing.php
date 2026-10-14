<?php

namespace BeltoftPos\Receipt;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the receipt can be printed at the till.
 *
 * OFF BY DEFAULT: there is no receipt printer at the counter yet. Switched on
 * in code only - `define( 'BELTOFT_POS_PRINTING', true );` in wp-config.php,
 * or the `beltoft_pos_printing` filter - not from a setting somebody could
 * flip by accident.
 *
 * Bricks reads it through the dynamic tag {beltoft_pos_printing} ("1" or
 * "0"): the receipt's "Imprimir" button carries the condition
 * {beltoft_pos_printing} == 1, so with printing off it is not rendered. A tag
 * rather than a custom condition key because the site's Bricks abilities only
 * accept Bricks' own condition keys. The 80 mm print layout stays in the
 * receipt's custom CSS and costs nothing while unused.
 */
class Printing {

	public const TAG = 'beltoft_pos_printing';

	public static function register(): void {
		add_filter( 'bricks/dynamic_tags_list', array( __CLASS__, 'add_tag' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( __CLASS__, 'render_tag' ), 20, 1 );
		add_filter( 'bricks/dynamic_data/render_content', array( __CLASS__, 'render_content' ), 20, 1 );
		add_filter( 'bricks/frontend/render_data', array( __CLASS__, 'render_content' ), 20, 1 );
	}

	public static function enabled(): bool {
		if ( defined( 'BELTOFT_POS_PRINTING' ) ) {
			return (bool) BELTOFT_POS_PRINTING;
		}

		return (bool) apply_filters( 'beltoft_pos_printing', false );
	}

	/**
	 * @param mixed $tags
	 */
	public static function add_tag( $tags ): array {
		$tags   = is_array( $tags ) ? $tags : array();
		$tags[] = array(
			'name'  => '{' . self::TAG . '}',
			'label' => __( 'Till printing is on (1/0)', 'beltoft-pos' ),
			'group' => 'Beltoft POS',
		);

		return $tags;
	}

	/**
	 * @param mixed $tag
	 * @return mixed
	 */
	public static function render_tag( $tag ) {
		if ( ! is_string( $tag ) || self::TAG !== trim( $tag, '{}' ) ) {
			return $tag;
		}

		return self::enabled() ? '1' : '0';
	}

	/**
	 * @param mixed $content
	 * @return mixed
	 */
	public static function render_content( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{' . self::TAG . '}' ) ) {
			return $content;
		}

		return str_replace( '{' . self::TAG . '}', self::enabled() ? '1' : '0', $content );
	}
}
