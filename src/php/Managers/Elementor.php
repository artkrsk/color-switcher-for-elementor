<?php

namespace Arts\ColorSwitcher\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Base\Manager as BaseManager;
use Arts\ColorSwitcher\Widgets\Toggle;

class Elementor extends BaseManager {

	/**
	 * Elementor builds a fresh instance of the registered class for every
	 * element it renders, so the managers are handed to the class here rather
	 * than to the instance registered below.
	 */
	public function register_widgets( \Elementor\Widgets_Manager $widgets_manager ): void {
		if ( null === $this->managers ) {
			return;
		}

		Toggle::bootstrap( $this->managers->toggle, $this->managers->kit );
		$widgets_manager->register( new Toggle() );
	}
}
