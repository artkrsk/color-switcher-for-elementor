<?php

namespace Arts\ColorSwitcher\Widgets\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Managers\Toggle as ToggleManager;

/**
 * Every option visible at once, so nothing has to be cycled through to be
 * discovered — and, with two options, none of them lit while the visitor has
 * chosen nothing. That empty state is what lets two controls offer three
 * states without a third button for "System".
 */
class Skin_Buttons extends Skin {

	public function get_id(): string {
		return ToggleManager::SKIN_BUTTONS;
	}

	public function get_title(): string {
		return esc_html__( 'Buttons', 'color-switcher-for-elementor' );
	}
}
