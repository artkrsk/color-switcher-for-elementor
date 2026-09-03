<?php

namespace Arts\ColorSwitcher\Base;

use ArtsColorSwitcher\Arts\Base\Containers\ManagersContainer as BaseManagersContainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @property \Arts\ColorSwitcher\Managers\Assets    $assets
 * @property \Arts\ColorSwitcher\Managers\Documents $documents
 * @property \Arts\ColorSwitcher\Managers\Elements  $elements
 * @property \Arts\ColorSwitcher\Managers\Elementor $elementor
 * @property \Arts\ColorSwitcher\Managers\Kit       $kit
 * @property \Arts\ColorSwitcher\Managers\Media     $media
 * @property \Arts\ColorSwitcher\Managers\Toggle    $toggle
 */
class ManagersContainer extends BaseManagersContainer {
}
