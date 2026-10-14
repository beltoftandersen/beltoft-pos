<?php

namespace BeltoftPos;

defined( 'ABSPATH' ) || exit;

/**
 * Single boot entry point. Every hook this plugin registers is wired here,
 * so the whole till surface is readable in one file.
 */
class Plugin {

	public static function init(): void {
		load_plugin_textdomain(
			'beltoft-pos',
			false,
			dirname( plugin_basename( BELTOFT_POS_FILE ) ) . '/languages'
		);

		// The till prices, stocks and audits through beltoft-commerce.
		if ( ! class_exists( 'BeltoftCommerce\\Plugin' ) ) {
			return;
		}
	}
}
