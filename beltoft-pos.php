<?php
/**
 * Plugin Name:      Beltoft POS
 * Description:      Point of sale for the shop counter: its own host, login, product list and checkout.
 * Version:          0.2.1
 * Author:           beltoft.net
 * Text Domain:      beltoft-pos
 * Domain Path:      /languages
 * Requires PHP:     8.3
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'BELTOFT_POS_FILE', __FILE__ );
define( 'BELTOFT_POS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BELTOFT_POS_VERSION', '0.2.1' );

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
BeltoftPos\Host\Gate::register_early();

// WooCommerce boots on plugins_loaded at 10; the till wires its hooks after it.
add_action( 'plugins_loaded', array( 'BeltoftPos\\Plugin', 'init' ), 20 );

/*
 * Orders are read and written through WooCommerce's order API only, so the
 * till works with either order storage. Undeclared, WooCommerce lists it as
 * incompatible and will not let a site switch HPOS on.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_activation_hook( __FILE__, array( 'BeltoftPos\\Support\\Installer', 'install' ) );
