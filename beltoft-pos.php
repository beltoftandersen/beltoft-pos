<?php
/**
 * Plugin Name:      Beltoft POS
 * Description:      Point of sale for the shop counter: its own host, login, product list and checkout.
 * Version:          0.1.0
 * Author:           beltoft.net
 * Text Domain:      beltoft-pos
 * Domain Path:      /languages
 * Requires PHP:     8.3
 * Requires Plugins: woocommerce, beltoft-commerce
 */

defined( 'ABSPATH' ) || exit;

define( 'BELTOFT_POS_FILE', __FILE__ );
define( 'BELTOFT_POS_PATH', plugin_dir_path( __FILE__ ) );

spl_autoload_register( function ( $class ) {
	$prefix = 'BeltoftPos\\';

	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$path     = BELTOFT_POS_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_readable( $path ) ) {
		require_once $path;
	}
} );

// After beltoft-commerce, whose pricing groups, stock pool and catalog audience the till uses.
add_action( 'plugins_loaded', array( 'BeltoftPos\\Plugin', 'init' ), 20 );
