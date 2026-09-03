<?php
/**
 * PHPUnit bootstrap — runs INSIDE the wp-env tests-cli container.
 *
 * `wp-env start` generates the tests config this file requires via
 * WP_PHPUNIT__TESTS_CONFIG (set in phpunit.xml.dist). The suite loads the BUILT
 * plugin from wp-content/plugins (the dist/ mount), never the repo source
 * tree — SmokeTest pins that autoloader precedence.
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$acs_wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );
$acs_tests_config   = getenv( 'WP_PHPUNIT__TESTS_CONFIG' );

if ( false === $acs_wp_phpunit_dir || ! is_dir( $acs_wp_phpunit_dir ) ) {
	fwrite( STDERR, "WP_PHPUNIT__DIR is not available — run `composer install` first.\n" );
	exit( 1 );
}

if ( false === $acs_tests_config || ! file_exists( $acs_tests_config ) ) {
	fwrite( STDERR, "Tests config not found — run through wp-env: `pnpm test:php`.\n" );
	exit( 1 );
}

require $acs_wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require WP_PLUGIN_DIR . '/elementor/elementor.php';
		require WP_PLUGIN_DIR . '/color-switcher-for-elementor/color-switcher-for-elementor.php';
	}
);

require $acs_wp_phpunit_dir . '/includes/bootstrap.php';
