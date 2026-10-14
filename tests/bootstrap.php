<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads ONLY WooCommerce and this plugin. That is the point: the till must
 * work with nothing else, and a suite that loaded beltoft-commerce would
 * hide every accidental dependency on it.
 *
 * The WordPress test install and its throwaway database (woo_staging_tests)
 * are shared with beltoft-commerce's harness; the suites run one at a time.
 */

// As staging's wp-config.php does: the plugin has no built-in host.
define( 'BELTOFT_POS_HOST', 'pos.staging.mindthetrash.pt' );

define( 'BELTOFT_SITE_PLUGINS', '/var/www/staging.mindthetrash.pt/wp-content/plugins' );

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	require BELTOFT_SITE_PLUGINS . '/woocommerce/woocommerce.php';
	require dirname( __DIR__ ) . '/beltoft-pos.php';
} );

// WooCommerce needs its schema in the test database; its activation never runs here.
tests_add_filter( 'setup_theme', function () {
	if ( class_exists( 'WC_Install' ) ) {
		WC_Install::install();

		if ( is_callable( array( 'WC', 'instance' ) ) ) {
			WC();
		}
	}
} );

require $_tests_dir . '/includes/bootstrap.php';

// The role, once, outside any test's transaction - as activation would.
\BeltoftPos\Support\Installer::install();
