<?php

namespace Arts\ColorSwitcher\Base;

use ArtsColorSwitcher\Arts\Base\Plugins\BasePlugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @extends BasePlugin<ManagersContainer>
 */
abstract class Plugin extends BasePlugin {

	/** @var ManagersContainer */
	protected $managers;
}
