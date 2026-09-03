<?php
/**
 * A page carrying the toggle, seeded for the dark-mode e2e journey.
 *
 * Deliberately renders the SHORTCODE rather than the widget: it exercises the
 * path that has no Elementor element_ready hook behind it, which is the one
 * that would break silently if boot's catch-up scan regressed.
 *
 * Idempotent. Run: wp eval-file dev/seed/toggle-page.php --user=1
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use Elementor\Plugin as Elementor;

$acstg_data = array(
	array(
		'id'       => 'acstgintro',
		'elType'   => 'container',
		'settings' => array(
			'background_background' => 'classic',
			'min_height'            => array(
				'unit' => 'vh',
				'size' => 100,
			),
			'flex_direction'        => 'column',
			'flex_justify_content'  => 'center',
			'flex_align_items'      => 'center',
			'__globals__'           => array( 'background_color' => 'globals/colors?id=acsbg' ),
		),
		'elements' => array(
			array(
				'id'         => 'acstgh',
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => array(
					'title'       => 'Pick a side',
					'align'       => 'center',
					'__globals__' => array( 'title_color' => 'globals/colors?id=acstext' ),
				),
				'elements'   => array(),
			),
			array(
				'id'         => 'acstgt',
				'elType'     => 'widget',
				'widgetType' => 'shortcode',
				'settings'   => array( 'shortcode' => '[arts_color_switcher_toggle]' ),
				'elements'   => array(),
			),
			// The real Elementor widget, not just the shortcode: its container
			// is the one the stylesheet turns flex to remove the strut.
			array(
				'id'         => 'acstgw',
				'elType'     => 'widget',
				'widgetType' => 'arts-color-switcher-toggle',
				'settings'   => array(
					'_skin' => 'icon',
					'mode'  => 'binary',
				),
				'elements'   => array(),
			),
			array(
				'id'         => 'acstgc',
				'elType'     => 'widget',
				'widgetType' => 'shortcode',
				'settings'   => array( 'shortcode' => '[arts_color_switcher_toggle mode="cycle"]' ),
				'elements'   => array(),
			),
			// One of every skin, so a regression in any of them is a failing
			// spec rather than something an author finds.
			array(
				'id'         => 'acstgsw',
				'elType'     => 'widget',
				'widgetType' => 'arts-color-switcher-toggle',
				'settings'   => array( '_skin' => 'switch' ),
				'elements'   => array(),
			),
			// Two options and no System button: the pattern where "neither
			// pressed" IS the third state.
			array(
				'id'         => 'acstgb2',
				'elType'     => 'widget',
				'widgetType' => 'arts-color-switcher-toggle',
				'settings'   => array(
					'_skin'         => 'buttons',
					'mode'          => 'binary',
					'buttons_style' => 'separate',
				),
				'elements'   => array(),
			),
			array(
				'id'         => 'acstgb3',
				'elType'     => 'widget',
				'widgetType' => 'arts-color-switcher-toggle',
				'settings'   => array(
					'_skin'         => 'buttons',
					'mode'          => 'cycle',
					'buttons_style' => 'joined',
				),
				'elements'   => array(),
			),
			array(
				'id'         => 'acstgd',
				'elType'     => 'widget',
				'widgetType' => 'arts-color-switcher-toggle',
				'settings'   => array(
					'_skin' => 'dropdown',
					'mode'  => 'cycle',
				),
				'elements'   => array(),
			),
		),
	),
);

$acstg_existing = get_page_by_path( 'color-switcher-toggle', OBJECT, 'page' );

if ( $acstg_existing ) {
	wp_delete_post( $acstg_existing->ID, true );
}

$acstg_id = wp_insert_post(
	array(
		'post_title'   => 'Color Switcher Toggle',
		'post_name'    => 'color-switcher-toggle',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '',
	)
);

update_post_meta( $acstg_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $acstg_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $acstg_id, '_wp_page_template', 'elementor_canvas' );
update_post_meta( $acstg_id, '_elementor_data', wp_slash( (string) wp_json_encode( $acstg_data ) ) );

/**
 * The same controls on a page whose author pinned the light palette. An
 * untouched page follows the visitor's device; this one must not, and that is
 * the property protecting every site that deliberately designed in one
 * palette. Only a real browser with a dark colour scheme can prove it.
 */
$acstg_pinned_existing = get_page_by_path( 'color-switcher-pinned-light', OBJECT, 'page' );

if ( $acstg_pinned_existing ) {
	wp_delete_post( $acstg_pinned_existing->ID, true );
}

$acstg_pinned_id = wp_insert_post(
	array(
		'post_title'   => 'Color Switcher Pinned Light',
		'post_name'    => 'color-switcher-pinned-light',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '',
	)
);

update_post_meta( $acstg_pinned_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $acstg_pinned_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $acstg_pinned_id, '_wp_page_template', 'elementor_canvas' );
update_post_meta( $acstg_pinned_id, '_elementor_data', wp_slash( (string) wp_json_encode( $acstg_data ) ) );
update_post_meta( $acstg_pinned_id, '_elementor_page_settings', array( 'arts_cs_page_theme' => 'default' ) );

Elementor::$instance->files_manager->clear_cache();

WP_CLI::success( 'Toggle fixture seeded: ' . get_permalink( $acstg_id ) );
WP_CLI::success( 'Pinned-light fixture seeded: ' . get_permalink( $acstg_pinned_id ) );
