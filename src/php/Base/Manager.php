<?php

namespace Arts\ColorSwitcher\Base;

use ArtsColorSwitcher\Arts\Base\Managers\BaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Manager extends BaseManager {

	/** @var ManagersContainer|null */
	protected $managers;
}
