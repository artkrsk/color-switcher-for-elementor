<?php
/**
 * Demo page fixture — one script, two consumers: the e2e suite seeds the site
 * with it, and the wp.org Playground blueprint inlines it verbatim. A page
 * that stops rendering breaks the suite and the shop-window preview on the
 * same signal.
 *
 * Idempotent: re-running replaces the page and re-applies the palette.
 *
 * Run: wp eval-file dev/seed/demo-page.php --user=1
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use Elementor\Plugin as Elementor;

// Single source of truth for the page id: the blueprint's landingPage is
// generated from this define, so the seeded page and the URL cannot drift.
define( 'ACS_DEMO_PAGE_ID', 9921 );

$acs_kit = Elementor::$instance->kits_manager->get_active_kit();

if ( ! $acs_kit->get_main_id() ) {
	Elementor::$instance->kits_manager->create_new_kit( 'Color Switcher Demo' );
	$acs_kit = Elementor::$instance->kits_manager->get_active_kit();
}

// A light palette with a dark Alt counterpart for every color the page uses.
$acs_colors = array(
	array(
		'_id'       => 'acsbg',
		'title'     => 'Demo Background',
		'color'     => '#F2F1ED',
		'color_alt' => '#15151A',
	),
	array(
		'_id'       => 'acstext',
		'title'     => 'Demo Text',
		'color'     => '#1B1B1F',
		'color_alt' => '#F0EFEA',
	),
	array(
		'_id'       => 'acsaccent',
		'title'     => 'Demo Accent',
		'color'     => '#C46A3F',
		'color_alt' => '#E8B98F',
	),
);

$acs_kit->save( array( 'settings' => array( 'custom_colors' => $acs_colors ) ) );

$acs_section = static function ( string $id, string $heading, string $text, bool $switches = false, int $start = 100 ): array {
	$settings = array(
		'background_background' => 'classic',
		'min_height'            => array(
			'unit' => 'vh',
			'size' => 100,
		),
		'flex_direction'        => 'column',
		'flex_justify_content'  => 'center',
		'flex_align_items'      => 'center',
		'__globals__'           => array( 'background_color' => 'globals/colors?id=acsbg' ),
	);

	if ( $switches ) {
		$settings['arts_cs_enabled']  = 'switch';
		$settings['arts_cs_viewport'] = array(
			'unit'  => '%',
			'sizes' => array(
				'start' => $start,
				'end'   => 100,
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
			array(
				'id'         => $id . 't',
				'elType'     => 'widget',
				'widgetType' => 'text-editor',
				'settings'   => array(
					'editor'      => '<p style="text-align:center">' . $text . '</p>',
					'__globals__' => array( 'text_color' => 'globals/colors?id=acsaccent' ),
				),
				'elements'   => array(),
			),
		),
	);
};

$acs_data = array(
	$acs_section( 'acsintro', 'Scroll down', 'Every color on this page is an Elementor Global Color. Nothing here is wired to the switcher.' ),
	$acs_section( 'acsalt', 'The whole page turned', 'This section switches the page while it is on screen — background, headings and body text move together.', true ),
	$acs_section( 'acsmid', 'And this one back to normal', 'Nothing marks this section, so the page returns to its own palette by itself.' ),
	$acs_section( 'acsscrub', 'This one takes its time', 'Its viewport handles are apart, so the change follows scroll position across the lower half of the screen instead of switching at a line.', true, 50 ),
	$acs_section( 'acsoutro', 'Back to default', 'Leaving a marked section always restores the page baseline.' ),
);

// A Switch toggle on the scrolling page: its knob rides --arts-cs-p, so the
// e2e suite can assert it following the zones — snapping in the instant one,
// gliding through the scrubbed one. The icon colours are set because their
// paint is a scalar-driven blend of exactly these two author values, which
// only exists on screen when an author has picked them.
$acs_data[0]['elements'][] = array(
	'id'         => 'acsintrosw',
	'elType'     => 'widget',
	'widgetType' => 'arts-color-switcher-toggle',
	'settings'   => array(
		'_skin'             => 'switch',
		'icon_color'        => '#7A7A7A',
		'icon_color_active' => '#FFC24B',
	),
	'elements'   => array(),
);

$acs_existing = get_page_by_path( 'color-switcher-demo', OBJECT, 'page' );

if ( $acs_existing ) {
	wp_delete_post( $acs_existing->ID, true );
}

$acs_page_id = wp_insert_post(
	array(
		'import_id'   => ACS_DEMO_PAGE_ID,
		'post_title'  => 'Color Switcher Demo',
		'post_name'   => 'color-switcher-demo',
		'post_type'   => 'page',
		'post_status' => 'publish',
	)
);

update_post_meta( $acs_page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $acs_page_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $acs_page_id, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $acs_page_id, '_elementor_data', wp_slash( wp_json_encode( $acs_data ) ) );
update_post_meta( $acs_page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );

Elementor::$instance->files_manager->clear_cache();

WP_CLI::success( 'Demo page seeded: ' . get_permalink( $acs_page_id ) );
