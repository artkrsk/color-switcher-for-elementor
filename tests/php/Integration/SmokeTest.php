<?php

namespace Arts\ColorSwitcher\Tests\Integration;

class SmokeTest extends TestCase {

	public function test_plugin_class_loads_from_built_artifact(): void {
		$file = ( new \ReflectionClass( \Arts\ColorSwitcher\Plugin::class ) )->getFileName();

		$this->assertIsString( $file );
		$this->assertStringContainsString( '/wp-content/plugins/color-switcher-for-elementor/', $file );
	}

	public function test_elementor_is_active(): void {
		$this->assertGreaterThan( 0, did_action( 'elementor/loaded' ) );
	}

	public function test_polyfill_package_is_vendored_and_prefixed(): void {
		$this->assertTrue( class_exists( \ArtsColorSwitcher\Arts\ScrollTimelinePolyfill\Plugin::class ) );
	}
}
