<?php

namespace BeltoftPos\Bricks;

use BeltoftPos\Support\Host;
use BeltoftPos\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The header the till renders, and where it renders none - as on the B2B host.
 *
 * Bricks resolves one header and one footer per request by scoring template
 * conditions, and those conditions cannot see the hostname, so the shop's
 * header (condition: entire website) would sit above the counter.
 * `bricks/active_templates` runs after that resolution; here the POS host
 * swaps in its own header and drops the footer - the till ends with the
 * counter. The login page gets neither.
 *
 * The till's header template carries NO conditions, so it can never win by
 * itself on another host. Found by slug, not id, because production replays
 * it as a new post with a different id.
 */
class Chrome {

	public const HEADER_SLUG = 'pos-cabecalho';

	public static function register(): void {
		add_filter( 'bricks/active_templates', array( __CLASS__, 'choose' ), 10, 3 );
	}

	/**
	 * @param mixed $active_templates
	 * @param mixed $post_id
	 * @param mixed $content_type
	 * @return mixed
	 */
	public static function choose( $active_templates, $post_id = 0, $content_type = '' ) {
		if ( ! is_array( $active_templates ) || ! Host::is_pos_host() ) {
			return $active_templates;
		}

		$login = Options::login_page_id();

		if ( $login && is_page( $login ) ) {
			unset( $active_templates['header'], $active_templates['footer'] );

			return $active_templates;
		}

		$header = self::template_id( self::HEADER_SLUG );

		if ( $header ) {
			$active_templates['header'] = $header;
			unset( $active_templates['footer'] );
		}

		return $active_templates;
	}

	private static function template_id( string $slug ): int {
		$template = get_page_by_path( $slug, OBJECT, 'bricks_template' );

		return $template instanceof \WP_Post && 'publish' === $template->post_status ? (int) $template->ID : 0;
	}
}
