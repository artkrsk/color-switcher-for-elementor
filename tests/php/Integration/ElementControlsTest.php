<?php

namespace Arts\ColorSwitcher\Tests\Integration;

use Arts\ColorSwitcher\Managers\Elements;
use Elementor\Controls_Manager;

class ElementControlsTest extends TestCase {

	/** @return array<int, array<int, string>> */
	public function element_provider(): array {
		return array(
			array( 'container' ),
			array( 'section' ),
		);
	}

	/**
	 * @dataProvider element_provider
	 */
	public function test_zone_controls_are_registered( string $el_type ): void {
		$controls = $this->element_controls( $el_type );

		foreach ( array( Elements::CONTROL_ENABLED, Elements::CONTROL_VIEWPORT ) as $id ) {
			$this->assertArrayHasKey( $id, $controls, $el_type );
		}
	}

	/**
	 * The runtime reads every zone setting from the element's data-settings
	 * payload; without this flag they never reach the frontend.
	 *
	 * @dataProvider element_provider
	 */
	public function test_zone_controls_are_frontend_available( string $el_type ): void {
		$controls = $this->element_controls( $el_type );

		foreach ( array( Elements::CONTROL_ENABLED, Elements::CONTROL_VIEWPORT ) as $id ) {
			$control = $this->array_value( $controls[ $id ] );
			$this->assertTrue( $control['frontend_available'] ?? false, $id );
		}
	}

	/**
	 * Per-breakpoint keys are what "don't run this on mobile" is expressed in;
	 * the handler reads them through Elementor's own device inheritance.
	 *
	 * @dataProvider element_provider
	 */
	public function test_enable_control_is_responsive( string $el_type ): void {
		$controls = $this->element_controls( $el_type );

		foreach ( array( '_tablet', '_mobile' ) as $suffix ) {
			$this->assertArrayHasKey( Elements::CONTROL_ENABLED . $suffix, $controls );
		}
	}

	/**
	 * The viewport range must NOT be responsive: Elementor seeds a responsive
	 * duplicate with the plain slider's empty value instead of a start/end
	 * pair, and the range UI then renders as a single-handle slider.
	 *
	 * @dataProvider element_provider
	 */
	public function test_viewport_control_is_not_responsive( string $el_type ): void {
		$controls = $this->element_controls( $el_type );

		foreach ( array( '_tablet', '_mobile' ) as $suffix ) {
			$this->assertArrayNotHasKey( Elements::CONTROL_VIEWPORT . $suffix, $controls );
		}
	}

	/**
	 * The invariant that makes a per-breakpoint "off" expressible at all: a
	 * SWITCHER's off state is an empty string, which Elementor's responsive
	 * inheritance reads as "unset" (so mobile would inherit the desktop "on")
	 * and which get_frontend_settings() drops before the runtime ever sees it.
	 * Both of this control's states must carry a real value.
	 *
	 * @dataProvider element_provider
	 */
	public function test_enable_control_has_no_empty_state( string $el_type ): void {
		$controls = $this->element_controls( $el_type );
		$control  = $this->array_value( $controls[ Elements::CONTROL_ENABLED ] );

		// Elementor keeps only the server-side args outside the editor, so the
		// option labels are not assertable here — the control's type and the
		// values themselves are, and they are what carries the invariant.
		$this->assertSame( Controls_Manager::SELECT, $control['type'] ?? null );
		$this->assertSame( Elements::ENABLED_NONE, $control['default'] ?? null );
		$this->assertNotSame( '', Elements::ENABLED_NONE );
		$this->assertNotSame( '', Elements::ENABLED_SWITCH );
	}

	/**
	 * Both handles default to the top of the viewport: a section switches the
	 * page once it has scrolled up to fill the screen, and hands the colors
	 * back when its bottom reaches the same line.
	 *
	 * @dataProvider element_provider
	 */
	public function test_viewport_control_is_a_range_at_the_top( string $el_type ): void {
		$controls = $this->element_controls( $el_type );
		$control  = $this->array_value( $controls[ Elements::CONTROL_VIEWPORT ] );
		$default  = $this->array_value( $control['default'] ?? null );
		$sizes    = $this->array_value( $default['sizes'] ?? null );

		$this->assertSame( Controls_Manager::SLIDER, $control['type'] ?? null );
		$this->assertSame( 100, $sizes['start'] ?? null );
		$this->assertSame( 100, $sizes['end'] ?? null );
	}
}
