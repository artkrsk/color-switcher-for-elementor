<?php

namespace Arts\ColorSwitcher\Widgets\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Managers\Toggle as ToggleManager;

/**
 * A native select, for the platform's own keyboard handling and its touch
 * wheel. The one skin CSS cannot fully state — CSS cannot SET a select's
 * value, and although browsers with customizable selects let it read one
 * (`option:checked` marks the list's chosen row), the collapsed label on a
 * cached page may still read the unchosen state for a frame before the
 * runtime corrects it. The page's colors are right in that frame; only the
 * word is late.
 */
class Skin_Dropdown extends Skin {

	public function get_id(): string {
		return ToggleManager::SKIN_DROPDOWN;
	}

	public function get_title(): string {
		return esc_html__( 'Dropdown', 'color-switcher-for-elementor' );
	}
}
