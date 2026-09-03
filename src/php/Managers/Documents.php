<?php

namespace Arts\ColorSwitcher\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Base\Manager as BaseManager;
use Elementor\Controls_Manager;
use Elementor\Core\Base\Document;
use Elementor\Core\DocumentTypes\PageBase;

class Documents extends BaseManager {

	const CONTROL_PAGE_THEME = 'arts_cs_page_theme';

	/** The visitor's stored choice. Pinned against the TS constant in phpParity. */
	const COOKIE_PREFERENCE = 'arts_cs_pref';

	/**
	 * That same choice mirrored onto `<html>`, where the stylesheet reads it to
	 * decide which of a control's options looks active. Written before first
	 * paint by the head script, so no skin has to be corrected by a script
	 * that runs after it. Pinned against the TS constant in phpParity.
	 */
	const ATTR_PREFERENCE = 'data-arts-cs-pref';

	const THEME_ALT = 'alt';

	/**
	 * The author declining to decide, so the visitor's device does.
	 *
	 * Stored as the empty string because it is the control's default, which
	 * keeps every untouched document on this value without a migration.
	 * `THEME_DEFAULT` is the author overriding that with "light, for everyone" —
	 * a genuinely different instruction, and the reason this control is no
	 * longer binary.
	 */
	const THEME_AUTO = '';

	const THEME_DEFAULT = 'default';

	/**
	 * "Page theme" — the baseline zones return to; what dark-start pages use.
	 * Label symmetry with the element-level control is deliberate.
	 *
	 * @param Document $document The document whose controls are being registered.
	 */
	public function register_document_controls( $document ): void {
		if ( ! $document instanceof PageBase ) {
			return;
		}

		$document->start_controls_section(
			'section_arts_cs',
			array(
				'label' => esc_html__( 'Color Switcher', 'color-switcher-for-elementor' ),
				'tab'   => Controls_Manager::TAB_SETTINGS,
			)
		);

		// Three values, because the visitor's device can decide: "let it" and
		// "no, light for everyone" are different instructions. Auto keeps the
		// empty string so it stays the stored default on untouched documents.
		$document->add_control(
			self::CONTROL_PAGE_THEME,
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Page opens in', 'color-switcher-for-elementor' ),
				'default' => self::THEME_AUTO,
				'options' => array(
					self::THEME_AUTO    => esc_html__( 'Auto — the visitor\'s device', 'color-switcher-for-elementor' ),
					self::THEME_DEFAULT => esc_html__( 'Default colors', 'color-switcher-for-elementor' ),
					self::THEME_ALT     => esc_html__( 'Alt colors', 'color-switcher-for-elementor' ),
				),
			)
		);

		$document->end_controls_section();
	}

	/**
	 * Server-render the baseline on <html> so a dark-start page has zero flash
	 * and works without JS. ArtsAJAXTransitions reconciles html attributes
	 * across swaps, carrying the baseline between pages automatically.
	 *
	 * Deliberately blind to the visitor's cookie AND to their device: this
	 * output is cacheable page content, and either one baked into a shared
	 * cache entry would serve one visitor's theme to everyone. Both are
	 * applied before paint by the head script instead.
	 *
	 * So an Auto page renders nothing here and the head script resolves it.
	 * Only an explicit Alt is server-rendered, which is what keeps a
	 * deliberately dark page flash-free and working without JS.
	 *
	 * @param string $output The language attributes string.
	 */
	public function add_baseline_html_attribute( $output ): string {
		if ( self::THEME_ALT === $this->get_current_page_theme() ) {
			$output .= ' data-arts-cs="alt"';
		}

		return $output;
	}


	/**
	 * Mirror the baseline onto the Elementor document wrapper — the
	 * router-agnostic source the runtime re-reads after AJAX swaps (the
	 * wrapper travels inside the swapped container; <html> attributes only
	 * sync on routers that reconcile them).
	 *
	 * @param array<string, string> $attributes Wrapper attributes.
	 * @param Document              $document   The rendering document.
	 * @return array<string, string>
	 */
	public function add_baseline_wrapper_attribute( $attributes, $document ): array {
		if ( ! is_array( $attributes ) || ! $document instanceof PageBase ) {
			return is_array( $attributes ) ? $attributes : array();
		}

		$attributes['data-arts-cs-baseline'] = $this->baseline_name( $document->get_settings( self::CONTROL_PAGE_THEME ) );

		return $attributes;
	}

	/**
	 * The wrapper attribute's vocabulary. Auto is spelled out rather than left
	 * empty: the runtime has to tell "follow the device" apart from "light for
	 * everyone" after an AJAX swap, and an empty attribute value cannot.
	 *
	 * @param mixed $theme The stored control value.
	 */
	private function baseline_name( $theme ): string {
		if ( self::THEME_ALT === $theme ) {
			return self::THEME_ALT;
		}

		return self::THEME_DEFAULT === $theme ? self::THEME_DEFAULT : 'auto';
	}

	/** Returns one of `THEME_AUTO` (''), `THEME_DEFAULT` or `THEME_ALT`. */
	public function get_current_page_theme(): string {
		$post_id = get_the_ID();

		if ( ! is_singular() || false === $post_id || ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		if ( ! $document || ! $document->is_built_with_elementor() ) {
			return '';
		}

		$theme = $document->get_settings( self::CONTROL_PAGE_THEME );

		return is_string( $theme ) ? $theme : '';
	}
}
