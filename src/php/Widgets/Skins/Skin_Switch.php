<?php

namespace Arts\ColorSwitcher\Widgets\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Managers\Toggle as ToggleManager;

/**
 * The pill with a sliding knob — the shape most visitors read as "dark mode"
 * without being told. Two positions and no third, which is why this is the one
 * skin that ignores the States control; the knob has nowhere to stand for
 * "follow the system".
 */
class Skin_Switch extends Skin {

	public function get_id(): string {
		return ToggleManager::SKIN_SWITCH;
	}

	public function get_title(): string {
		return esc_html__( 'Switch', 'color-switcher-for-elementor' );
	}
}
