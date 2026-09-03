<?php
/**
 * Cross-plugin fixture: a marked panel INSIDE an Arts Horizontal Scroll track.
 *
 * Separate from demo-page.php on purpose — that one is also inlined by the
 * wp.org Live Preview blueprint, which ships Color Switcher alone. This page
 * only exists where the horizontal plugin is installed too (the e2e run, and
 * the dev site).
 *
 * A pinned panel's vertical band is frozen while its neighbours slide past, so
 * this is the fixture that proves zone state follows what is actually on
 * screen rather than what a rect comparison would report.
 *
 * Idempotent. Run: wp eval-file dev/seed/horizontal-page.php --user=1
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use Elementor\Plugin as Elementor;

$acshs_panel = static function ( string $id, string $heading, bool $switches, int $start = 100, ?int $end = null ): array {
	$settings = array(
		'background_background' => 'classic',
		'_title'                => $heading,
		'flex_direction'        => 'column',
		'flex_justify_content'  => 'center',
		'flex_align_items'      => 'center',
		'width'                 => array(
			'unit' => 'vw',
			'size' => 100,
		),
		'__globals__'           => array( 'background_color' => 'globals/colors?id=acsbg' ),
	);

	if ( $switches ) {
		$settings['arts_cs_enabled']  = 'switch';
		$settings['arts_cs_viewport'] = array(
			'unit'  => '%',
			// Inside a pinned track the handles read across the stage. Handles
			// together switch the page as the panel reaches that line — the
			// case that tells a rotated trigger line from an on-stage one —
			// and handles apart scrub the scalar across the band between them.
			'sizes' => array(
				'start' => $start,
				'end'   => $end ?? $start,
			),
		);
	}

	return array(
		'id'       => $id,
		'elType'   => 'container',
		'settings' => $settings,
		'elements' => array(
			array(
				'id'         => $id . 'h',
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => array(
					'title'       => $heading,
					'align'       => 'center',
					'__globals__' => array( 'title_color' => 'globals/colors?id=acstext' ),
				),
				'elements'   => array(),
			),
		),
	);
};

$acshs_section = static function ( string $id, string $heading ): array {
	return array(
		'id'       => $id,
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
				'id'         => $id . 'h',
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => array(
					'title'       => $heading,
					'align'       => 'center',
					'__globals__' => array( 'title_color' => 'globals/colors?id=acstext' ),
				),
				'elements'   => array(),
			),
		),
	);
};

$acshs_panels = array(
	$acshs_panel( 'acshsp1', 'Panel one', false ),
	// Handles apart: this panel scrubs the scalar across the stage, which is
	// what exercises the Arts HS timeline tier — and, since a scrubbing zone
	// never flips the theme attribute, it stays invisible to the binary
	// assertions the specs make about panel three.
	$acshs_panel( 'acshsp2', 'Panel two scrubs across the stage', true, 25, 75 ),
	$acshs_panel( 'acshsp3', 'Panel three switches at mid-screen', true, 50 ),
	$acshs_panel( 'acshsp4', 'Panel four', false ),
);

$acshs_widget = array(
	'id'         => 'acshswidget',
	'elType'     => 'widget',
	'widgetType' => 'arts-horizontal-scroll',
	'settings'   => array(
		// Row count MUST equal child-container count — linkage is positional.
		'panels'           => array(
			array(
				'_id'         => 'acshsr1',
				'panel_title' => 'Panel one',
			),
			array(
				'_id'         => 'acshsr2',
				'panel_title' => 'Panel two scrubs across the stage',
			),
			array(
				'_id'         => 'acshsr3',
				'panel_title' => 'Panel three switches at mid-screen',
			),
			array(
				'_id'         => 'acshsr4',
				'panel_title' => 'Panel four',
			),
		),
		'layout'           => 'horizontal',
		'layout_mobile'    => 'vertical',
		'touch_vertical'   => 'yes',
		'viewport_height'  => array(
			'unit' => 'vh',
			'size' => 100,
		),
		'scroll_direction' => 'ltr',
		'scroll_factor'    => array(
			'unit' => 'px',
			'size' => 1,
		),
	),
	'elements'   => $acshs_panels,
);

$acshs_data = array(
	$acshs_section( 'acshsintro', 'Scroll down' ),
	array(
		'id'       => 'acshslane',
		'elType'   => 'container',
		'settings' => array(
			'content_width' => 'full',
			'_title'        => 'Horizontal lane',
		),
		'elements' => array( $acshs_widget ),
	),
	$acshs_section( 'acshsoutro', 'Back to default' ),
);

// A Switch toggle outside the track: its knob rides --arts-cs-p, so the e2e
// suite can assert it gliding while a pinned panel scrubs the page.
$acshs_data[0]['elements'][] = array(
	'id'         => 'acshsintrosw',
	'elType'     => 'widget',
	'widgetType' => 'arts-color-switcher-toggle',
	'settings'   => array( '_skin' => 'switch' ),
	'elements'   => array(),
);

$acshs_existing = get_page_by_path( 'color-switcher-horizontal', OBJECT, 'page' );

if ( $acshs_existing ) {
	wp_delete_post( $acshs_existing->ID, true );
}

$acshs_id = wp_insert_post(
	array(
		'post_title'   => 'Color Switcher Horizontal',
		'post_name'    => 'color-switcher-horizontal',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '',
	)
);

update_post_meta( $acshs_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $acshs_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $acshs_id, '_wp_page_template', 'elementor_canvas' );
update_post_meta( $acshs_id, '_elementor_data', wp_slash( (string) wp_json_encode( $acshs_data ) ) );

Elementor::$instance->files_manager->clear_cache();

WP_CLI::success( 'Horizontal fixture seeded: ' . get_permalink( $acshs_id ) );
