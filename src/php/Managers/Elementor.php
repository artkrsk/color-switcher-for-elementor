<?php

namespace Arts\ColorSwitcher\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Base\Manager as BaseManager;
use Arts\ColorSwitcher\Widgets\Toggle;

class Elementor extends BaseManager {

	private const VERSION_OPTION = 'arts_color_switcher_version';

	/**
	 * Elementor constructs the widget itself, so its dependencies are handed
	 * to the class here rather than to an instance.
	 */
	public function register_widgets( \Elementor\Widgets_Manager $widgets_manager ): void {
		if ( null === $this->managers ) {
			return;
		}

		Toggle::bootstrap( $this->managers->toggle, $this->managers->kit );
		$widgets_manager->register( new Toggle() );
	}

	/**
	 * Saved pages keep Elementor's generated CSS and element cache until
	 * something clears them — a plugin update that changes control selectors
	 * (the kit color-mix layer lives entirely in selectors) would otherwise
	 * keep serving stale variable schemes. One clear per version.
	 */
	public function maybe_clear_cache_on_version_change(): void {
		$saved = get_option( self::VERSION_OPTION, '' );

		if ( is_string( $saved ) && ARTS_COLOR_SWITCHER_PLUGIN_VERSION === $saved ) {
			return;
		}

		\Elementor\Plugin::$instance->files_manager->clear_cache();
		update_option( self::VERSION_OPTION, ARTS_COLOR_SWITCHER_PLUGIN_VERSION );
	}
}
