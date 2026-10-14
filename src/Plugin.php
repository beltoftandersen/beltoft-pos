<?php

namespace BeltoftPos;

defined( 'ABSPATH' ) || exit;

/**
 * Single boot entry point. Every hook this plugin registers is wired here,
 * so the whole till surface is readable in one file.
 *
 * Depends on WooCommerce and Bricks only. Anything else it talks to - the
 * scanner, the pickup-point plugin, the newsletter opt-in - is reached through
 * that plugin's own hooks and simply does nothing when the plugin is absent.
 */
class Plugin {

	public static function init(): void {
		load_plugin_textdomain(
			'beltoft-pos',
			false,
			dirname( plugin_basename( BELTOFT_POS_FILE ) ) . '/languages'
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'needs_woocommerce' ) );

			return;
		}

		add_action( 'init', array( Support\Installer::class, 'maybe_upgrade' ) );

		Host\NoIndex::register();
		Host\Gate::register();
		Checkout\Page::register();
	}

	public static function needs_woocommerce(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Beltoft POS needs WooCommerce.', 'beltoft-pos' )
		);
	}
}
