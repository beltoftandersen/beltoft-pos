<?php
/**
 * Plugin Name:      Beltoft POS
 * Description:      Point of sale for the shop counter: its own host, login, product list and checkout.
 * Version:          0.1.0
 * Author:           beltoft.net
 * Text Domain:      beltoft-pos
 * Domain Path:      /languages
 * Requires PHP:     8.3
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'BELTOFT_POS_FILE', __FILE__ );
define( 'BELTOFT_POS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BELTOFT_POS_VERSION', '0.1.0' );

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

/*
 * Registered now rather than in Plugin::init(): other plugins build URLs
 * while plugins_loaded is still running, and on the POS host every one of
 * them must already point here.
 */
BeltoftPos\Host\Urls::register();

// WooCommerce boots on plugins_loaded at 10; the till wires its hooks after it.
add_action( 'plugins_loaded', array( 'BeltoftPos\\Plugin', 'init' ), 20 );

register_activation_hook( __FILE__, array( 'BeltoftPos\\Support\\Installer', 'install' ) );
