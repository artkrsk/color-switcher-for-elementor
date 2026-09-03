<?php

namespace Arts\ColorSwitcher\Widgets\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Widgets\Toggle;
use Elementor\Skin_Base;

/**
 * What every skin has in common: it draws nothing itself and registers no
 * controls. The markup lives in the Toggle manager, which the shortcode calls
 * too — a skin that rendered its own HTML would be a shape only the widget
 * could offer — and the controls live on the widget, conditioned on `_skin`.
 *
 * Controls are NOT registered here on purpose. `Skin_Base::start_controls_tabs()`
 * assembles a `_skin` condition into a local it then never passes on, so four
 * skins each opening a Normal/Hover/Active group would leave three empty tab
 * wrappers in the panel; and all four would be writing `current_tab` on one
 * shared stack, where a callback that failed to close its tabs takes the next
 * skin down with a `wp_die`. Declared on the widget, there is one group and
 * one owner.
 */
abstract class Skin extends Skin_Base {

	abstract public function get_id(): string;

	public function render(): void {
		$widget = $this->parent;

		if ( $widget instanceof Toggle ) {
			$widget->render_skin( $this->get_id() );
		}
	}
}
