<?php

namespace Arts\ColorSwitcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin extends Base\Plugin {

	/** @return array<string, mixed> */
	protected function get_default_config(): array {
		return array();
	}

	/** @return array<string, mixed> */
	protected function get_default_strings(): array {
		return array();
	}

	protected function get_default_run_action(): string {
		return 'plugins_loaded';
	}

	protected function get_run_action_priority(): int {
		return 20;
	}

	/** @return array<string, class-string> */
	protected function get_managers_classes(): array {
		return array(
			'assets'    => Managers\Assets::class,
			'documents' => Managers\Documents::class,
			'elements'  => Managers\Elements::class,
			'elementor' => Managers\Elementor::class,
			'kit'       => Managers\Kit::class,
			'media'     => Managers\Media::class,
			'toggle'    => Managers\Toggle::class,
		);
	}

	protected function add_actions(): void {
		add_action( 'elementor/element/kit/section_global_colors/before_section_end', array( $this->managers->kit, 'inject_global_colors_controls' ), 10, 1 );
		add_action( 'elementor/element/container/section_layout/after_section_end', array( $this->managers->elements, 'add_section_controls' ) );
		add_action( 'elementor/element/section/section_layout/after_section_end', array( $this->managers->elements, 'add_section_controls' ) );
		add_action( 'elementor/controls/register', array( $this->managers->media, 'register_control' ) );
		add_action( 'elementor/element/before_section_end', array( $this->managers->media, 'upgrade_media_controls' ), 10, 3 );
		add_action( 'elementor/documents/register_controls', array( $this->managers->documents, 'register_document_controls' ) );
		add_action( 'elementor/init', array( $this->managers->elementor, 'maybe_clear_cache_on_version_change' ) );
		add_action( 'elementor/widgets/register', array( $this->managers->elementor, 'register_widgets' ) );
		add_action( 'init', array( $this->managers->toggle, 'register_shortcode' ) );
		add_action( 'wp_head', array( $this->managers->assets, 'print_head_script' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this->managers->assets, 'register_frontend' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this->managers->assets, 'enqueue_frontend' ), 20 );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this->managers->assets, 'enqueue_editor_panel_css' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this->managers->assets, 'enqueue_editor_js' ) );
	}

	protected function add_filters(): void {
		add_filter( 'language_attributes', array( $this->managers->documents, 'add_baseline_html_attribute' ) );
		add_filter( 'elementor/document/wrapper_attributes', array( $this->managers->documents, 'add_baseline_wrapper_attribute' ), 10, 2 );
		add_filter( 'arts/scroll_timeline_polyfill/skipped_styles', array( $this->managers->assets, 'skip_polyfill_transpiling' ) );
	}
}
