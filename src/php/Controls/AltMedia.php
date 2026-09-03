<?php

namespace Arts\ColorSwitcher\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Control_Media;

/**
 * A stock media control that also carries an Alt image.
 *
 * Every image media control on the site is re-typed to this one by
 * Managers\Media, the way ArtsEnhancedURLControl re-types every URL control.
 * The Alt lives as extra sub-keys inside the control's OWN value object —
 * legal because Control_Media extends Control_Base_Multiple, whose
 * get_style_value() reads a `{{KEY}}` selector placeholder straight off the
 * value array. That is the whole reason this feature needs no render filter:
 * the Alt URL reaches CSS the same way the default one does.
 *
 * The keys are namespaced deliberately. `alt` is already a live key in this
 * value object (Control_Media::get_image_alt reads it), and these keys are
 * stored inside OTHER widgets' settings, where a later rename would be a data
 * migration across every document rather than a code change.
 */
class AltMedia extends Control_Media {

	const TYPE = 'arts_cs_media';

	const KEY_URL = 'arts_cs_alt_url';
	const KEY_ID  = 'arts_cs_alt_id';

	/** Control arg carrying the panel's own words. Read by the editor view. */
	const SETTING_LABELS = 'arts_cs_alt_labels';

	public function get_type(): string {
		return self::TYPE;
	}

	/** @return array<string, mixed> */
	public function get_default_value() {
		$default = parent::get_default_value();
		$merged  = array();

		if ( is_array( $default ) ) {
			foreach ( $default as $key => $value ) {
				$merged[ (string) $key ] = $value;
			}
		}

		$merged[ self::KEY_URL ] = '';
		$merged[ self::KEY_ID ]  = '';

		return $merged;
	}

	/**
	 * Resolve one of our own placeholders; everything else is the parent's.
	 *
	 * Two things have to hold here. A value saved before this plugin existed
	 * carries no Alt keys at all, and Control_Base_Multiple reads sub-keys
	 * with a bare index — so without this the first Alt selector on the site
	 * warns on every legacy image. Returning '' instead puts an absent Alt on
	 * Elementor's own no-value path, where Files\CSS\Base::add_control_rules()
	 * skips the rule, which is exactly what an unset Alt should mean.
	 *
	 * And the Alt has to resolve the way the parent resolves its own URL:
	 * Control_Media::get_style_value() sends a library value back through
	 * wp_get_attachment_image_url() so the emitted image honours the Image
	 * Size control. Returning the stored URL here instead would render the
	 * default at the chosen size and the Alt at full size — the same picture
	 * at two resolutions.
	 *
	 * @param string               $css_property  Placeholder name, upper-cased.
	 * @param mixed                $control_value The saved value.
	 * @param array<string, mixed> $control_data  The control's args.
	 * @return mixed
	 */
	public function get_style_value( $css_property, $control_value, array $control_data ) {
		// Elementor only ever hands a multiple control its own settings array;
		// anything else is the no-value path, which is what '' expresses.
		if ( ! is_array( $control_value ) ) {
			return '';
		}

		$key = strtolower( $css_property );

		if ( self::KEY_URL !== $key && self::KEY_ID !== $key ) {
			return parent::get_style_value( $css_property, $control_value, $control_data );
		}

		if ( empty( $control_value[ $key ] ) ) {
			return '';
		}

		$stored = $control_value[ $key ];

		if ( self::KEY_URL === $key ) {
			$id = $control_value[ self::KEY_ID ] ?? null;

			if ( is_numeric( $id ) ) {
				$size     = $control_value['size'] ?? '';
				$resolved = wp_get_attachment_image_url(
					(int) $id,
					is_string( $size ) && '' !== $size ? $size : 'full'
				);

				// A deleted attachment falls back to the stored URL rather
				// than emptying the rule, so a broken Alt degrades to the
				// last known image instead of reverting to the default one.
				if ( is_string( $resolved ) && '' !== $resolved ) {
					return $resolved;
				}
			}
		}

		if ( is_string( $stored ) ) {
			return $stored;
		}

		return is_int( $stored ) || is_float( $stored ) ? (string) $stored : '';
	}

	/**
	 * Editor-panel words, carried as a control arg rather than hardcoded in
	 * the view that prints them.
	 *
	 * The view injects its button into the stock control's own tools row —
	 * the same row and the same markup Elementor's dynamic-tags switcher fills
	 * in — rather than appending a second control field, which is what the Alt
	 * picker is: part of this control, not one beside it. That leaves the
	 * labels on the JS side, and this plugin ships no script translations, so
	 * they travel as args and stay on the normal .po path.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_default_settings() {
		$defaults = parent::get_default_settings();
		$merged   = array();

		if ( is_array( $defaults ) ) {
			foreach ( $defaults as $key => $value ) {
				$merged[ (string) $key ] = $value;
			}
		}

		$merged[ self::SETTING_LABELS ] = array(
			'choose' => esc_html__( 'Choose Alt Image', 'color-switcher-for-elementor' ),
			'set'    => esc_html__( 'Alt Image is Set', 'color-switcher-for-elementor' ),
			'remove' => esc_html__( 'Remove Alt Image', 'color-switcher-for-elementor' ),
			'frame'  => esc_html__( 'Select Alt Image', 'color-switcher-for-elementor' ),
		);

		return $merged;
	}
}
