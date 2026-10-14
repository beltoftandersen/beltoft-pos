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
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
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

	/**
	 * Without its header template the till falls back to the shop's header -
	 * whose menu script is kept off the till - so say so where it is noticed
	 * (most likely right after a production replay).
	 */
	public static function admin_notice(): void {
		if ( self::template_id( self::HEADER_SLUG ) || ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: template slug. */
					__( 'The till has no header: publish a Bricks header template with the slug %s (no conditions).', 'beltoft-pos' ),
					self::HEADER_SLUG
				)
			)
		);
	}

	private static function template_id( string $slug ): int {
		$template = get_page_by_path( $slug, OBJECT, 'bricks_template' );

		return $template instanceof \WP_Post && 'publish' === $template->post_status ? (int) $template->ID : 0;
	}
}
