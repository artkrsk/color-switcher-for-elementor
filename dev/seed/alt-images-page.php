<?php
/**
 * A page whose images carry Alt versions, seeded for the alt-image e2e journey.
 *
 * Every other tier proves the CSS is GENERATED; only a browser proves it
 * applies. Both branches are here: a container background, which mirrors the
 * widget's own `background-image: url({{URL}})` declaration, and an Image
 * widget, which has no declaration of its own and so gets `content: url(...)`
 * on the <img> instead.
 *
 * The URLs are plain and carry no attachment id on purpose. AltMedia's style
 * value falls back to the stored URL when there is no attachment, so this
 * needs no media fixtures — and a computed background-image reports its url()
 * whether or not the file resolves.
 *
 * Idempotent. Run: wp eval-file dev/seed/alt-images-page.php --user=1
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use Elementor\Plugin as Elementor;

const ACSAI_BG      = 'https://alt.test/hero-default.jpg';
const ACSAI_BG_ALT  = 'https://alt.test/hero-alt.jpg';
const ACSAI_IMG     = 'https://alt.test/logo-default.png';
const ACSAI_IMG_ALT = 'https://alt.test/logo-alt.png';

$acsai_data = array(
	array(
		'id'       => 'acsaibg',
		'elType'   => 'container',
		'settings' => array(
			'background_background' => 'classic',
			'background_image'      => array(
				'url'             => ACSAI_BG,
				'id'              => '',
				'arts_cs_alt_url' => ACSAI_BG_ALT,
				'arts_cs_alt_id'  => '',
			),
			'min_height'            => array(
				'unit' => 'vh',
				'size' => 60,
			),
		),
		'elements' => array(
			array(
				'id'         => 'acsaiimg',
				'elType'     => 'widget',
				'widgetType' => 'image',
				'settings'   => array(
					'image' => array(
						'url'             => ACSAI_IMG,
						'id'              => '',
						'arts_cs_alt_url' => ACSAI_IMG_ALT,
						'arts_cs_alt_id'  => '',
					),
				),
				'elements'   => array(),
			),
			// The flip has to come from somewhere the page does not author:
			// pressing the toggle leaves the document's own baseline alone.
			array(
				'id'         => 'acsaitg',
				'elType'     => 'widget',
				'widgetType' => 'arts-color-switcher-toggle',
				'settings'   => array(
					'_skin' => 'icon',
					'mode'  => 'binary',
				),
				'elements'   => array(),
			),
		),
	),
);

$acsai_existing = get_page_by_path( 'color-switcher-alt-images', OBJECT, 'page' );

if ( $acsai_existing ) {
	wp_delete_post( $acsai_existing->ID, true );
}

$acsai_id = wp_insert_post(
	array(
		'post_title'   => 'Color Switcher Alt Images',
		'post_name'    => 'color-switcher-alt-images',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '',
	)
);

update_post_meta( $acsai_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $acsai_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $acsai_id, '_wp_page_template', 'elementor_canvas' );
update_post_meta( $acsai_id, '_elementor_data', wp_slash( (string) wp_json_encode( $acsai_data ) ) );

Elementor::$instance->files_manager->clear_cache();

WP_CLI::success( 'Alt images fixture seeded: ' . get_permalink( $acsai_id ) );
