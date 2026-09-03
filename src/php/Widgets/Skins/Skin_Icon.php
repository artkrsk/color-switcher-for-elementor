<?php

namespace Arts\ColorSwitcher\Widgets\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Managers\Toggle as ToggleManager;

/**
 * One icon that carries the state. The smallest thing that can sit in a menu
 * bar, and the only skin that offers the three-state cycle in a single
 * control — where the icon must show the state the visitor is IN, not the
 * palette it resolved to, or "following the system" and an explicit choice
 * would look identical and the control would cycle blind.
 */
class Skin_Icon extends Skin {

	public function get_id(): string {
		return ToggleManager::SKIN_ICON;
	}

	public function get_title(): string {
		return esc_html__( 'Icon', 'color-switcher-for-elementor' );
	}
}
