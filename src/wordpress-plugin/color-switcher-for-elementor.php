<?php
/**
 * Plugin Name: Arts Color Switcher for Elementor – Dark Mode & Color Switching on Scroll
 * Plugin URI: https://artemsemkin.com/plugins/color-switcher-for-elementor/
 * Description: Scroll-triggered color theme switching for Elementor — give Global Colors an Alt value and morph the whole page palette between sections.
 * Version: 1.0.0
 * Author: Artem Semkin
 * Author URI: https://artemsemkin.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0
 * Text Domain: color-switcher-for-elementor
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 8.0
 * Requires Plugins: elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARTS_COLOR_SWITCHER_PLUGIN_VERSION', '1.0.0' );

require_once __DIR__ . '/vendor/autoload.php';

// No dependency guard needed: "Requires Plugins: elementor" blocks activation
// without Elementor on WP 6.5+, and every entry point is an elementor/* hook
// (inert without it). Plugin extends Base\Plugin (arts/base BasePlugin), which
// schedules run() on the hook/priority from get_default_run_action().
\Arts\ColorSwitcher\Plugin::instance();

// Registers the shared `scroll-timeline-polyfill` handle the frontend script
// depends on. Self-gating: browsers with native scroll-driven animations fetch
// the small loader and nothing more. The handle is deliberately shared — first
// registration across Arts plugins wins, one polyfill copy per page.
\ArtsColorSwitcher\Arts\ScrollTimelinePolyfill\Plugin::instance();
