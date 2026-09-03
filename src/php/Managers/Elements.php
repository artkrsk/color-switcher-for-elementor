<?php

namespace Arts\ColorSwitcher\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Base\Manager as BaseManager;
use Elementor\Controls_Manager;

class Elements extends BaseManager {

	const SECTION_ID = 'section_arts_cs';

	const CONTROL_ENABLED       = 'arts_cs_enabled';
	const CONTROL_VIEWPORT      = 'arts_cs_viewport';
	const CONTROL_VIEWPORT_HINT = 'arts_cs_viewport_hint';

	const ENABLED_NONE   = 'none';
	const ENABLED_SWITCH = 'switch';

	/**
	 * Own "Color Switcher" section on container + section elements — appended
	 * as a new section after the Layout section rather than spliced into a
	 * core one, so core reordering its own controls can't break us.
	 *
	 * The enable control is responsive, and both behavior controls are
	 * `frontend_available`: the runtime reads the current breakpoint's value
	 * through the handler's own getCurrentDeviceSetting(). The hint row below
	 * them is editor-only and carries neither flag.
	 *
	 * @param \Elementor\Element_Base $element The element being registered.
	 */
	public function add_section_controls( $element ): void {
		$element->start_controls_section(
			self::SECTION_ID,
			array(
				'label' => esc_html__( 'Color Switcher', 'color-switcher-for-elementor' ),
				'tab'   => Controls_Manager::TAB_LAYOUT,
			)
		);

		$element->add_responsive_control(
			self::CONTROL_ENABLED,
			array(
				'type'               => Controls_Manager::SELECT,
				'label'              => esc_html__( 'Flip colors in this section', 'color-switcher-for-elementor' ),
				'description'        => esc_html__( 'While this section is on screen the whole page swaps to the other palette — Alt colors on a Default page, Default colors on an Alt page — and swaps back when it leaves.', 'color-switcher-for-elementor' ),
				'default'            => self::ENABLED_NONE,
				// Deliberately a select rather than a switcher: a switcher's off
				// state is an empty string, which Elementor's responsive
				// inheritance reads as "unset" (so it would inherit the desktop
				// "on") and which the frontend settings payload drops entirely.
				// Both states must carry a real value for "off on mobile" to be
				// expressible at all.
				'options'            => array(
					self::ENABLED_NONE   => esc_html__( 'No', 'color-switcher-for-elementor' ),
					self::ENABLED_SWITCH => esc_html__( 'Yes', 'color-switcher-for-elementor' ),
				),
				'frontend_available' => true,
			)
		);

		// Deliberately NOT responsive: add_responsive_control() seeds each
		// breakpoint's duplicate with the plain slider's empty value
		// ({unit:px, size:'', sizes:[]}) rather than a start/end pair, so the
		// range UI has nothing to build its two handles from and degrades to a
		// single-handle slider with an empty input. The per-device need is
		// "don't run the effect here", which the enable control above covers.
		$element->add_control(
			self::CONTROL_VIEWPORT,
			array(
				'type'               => Controls_Manager::SLIDER,
				'label'              => esc_html__( 'Viewport', 'color-switcher-for-elementor' ),
				'handles'            => 'range',
				'labels'             => array(
					esc_html__( 'Bottom', 'color-switcher-for-elementor' ),
					esc_html__( 'Top', 'color-switcher-for-elementor' ),
				),
				'scales'             => 1,
				'size_units'         => array( '%' ),
				'range'              => array(
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				// Both handles at the top: the section switches the page once it
				// has scrolled up to fill the screen, and hands the colors back
				// when its bottom reaches the same line.
				'default'            => array(
					'unit'  => '%',
					'sizes' => array(
						'start' => 100,
						'end'   => 100,
					),
				),
				'condition'          => array(
					self::CONTROL_ENABLED => self::ENABLED_SWITCH,
				),
				'frontend_available' => true,
			)
		);

		// The range slider prints its handle values below the track, over
		// anything the control itself renders underneath — so the explanation
		// is a control of its own rather than the slider's `description`.
		$element->add_control(
			self::CONTROL_VIEWPORT_HINT,
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'How far this section travels before the colors change: the first handle starts the change, the second completes it. Put both together for an instant switch.', 'color-switcher-for-elementor' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array(
					self::CONTROL_ENABLED => self::ENABLED_SWITCH,
				),
			)
		);

		$element->end_controls_section();
	}
}
