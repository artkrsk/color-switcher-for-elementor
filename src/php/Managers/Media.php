<?php

namespace Arts\ColorSwitcher\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Base\Manager as BaseManager;
use Arts\ColorSwitcher\Controls\AltMedia;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Kit;

class Media extends BaseManager {

	/**
	 * Scopes every Alt rule to the applied theme. A prefix before {{WRAPPER}}
	 * is legal and precedented — core ships `body.rtl {{WRAPPER}}`.
	 *
	 * Deliberately the state attribute and not the scalar: a scrub never flips
	 * it, so an image holds through a scrubbing zone and swaps only on a
	 * settled transition. Fading with the scalar needs a second painting
	 * surface and is a different feature.
	 */
	const ALT_SCOPE = 'html[data-arts-cs="alt"] ';

	/**
	 * @param \Elementor\Controls_Manager $controls_manager The registering manager.
	 */
	public function register_control( $controls_manager ): void {
		$controls_manager->register( new AltMedia() );
	}

	/**
	 * Re-type every image media control registered in the section that is
	 * closing.
	 *
	 * Elementor has no per-control registration hook — no `after_add_control`,
	 * and neither Controls_Stack::add_control() nor
	 * Controls_Manager::add_control_to_stack() fires a filter over a control's
	 * args. Reaching every control of a type therefore means scanning each
	 * section as it closes, which is what arts/enhanced-url-control already
	 * does in production for URL controls.
	 *
	 * `before_section_end` rather than `after_section_end`: current_section is
	 * nulled before the after_ actions fire, and staying inside the section
	 * keeps this identical in shape to that package.
	 *
	 * Gated on an Alt swatch existing, the same way the head script is: with
	 * no Alt palette the control could never fire, so it is not offered.
	 *
	 * The kit is skipped outright, and not as a nicety. Its tabs are
	 * Sub_Controls_Stack proxies that forward end_controls_section() to the
	 * kit itself, so a tab closing fires this with the KIT as $element, still
	 * inside its own register_controls(). Reading the gate there re-enters
	 * Base_Object::ensure_settings(), which assigns its cache only once
	 * get_init_settings() RETURNS — so the nested read recomputes from a
	 * half-registered control list and caches that partial snapshot on the
	 * kit for the rest of the request. It cannot recurse forever (open_stack()
	 * marks the stack before register_controls() runs), but a kit whose
	 * settings are silently missing everything registered after Site Identity
	 * is not a trade worth making for an Alt picker on the Site Logo.
	 *
	 * @param \Elementor\Controls_Stack $element    The registering stack.
	 * @param string                    $section_id The section being closed.
	 * @param array<string, mixed>      $args       The section's args.
	 */
	public function upgrade_media_controls( $element, $section_id, $args ): void {
		if ( $element instanceof Kit ) {
			return;
		}

		if ( null === $this->managers || ! $this->managers->kit->has_alt_colors() ) {
			return;
		}

		foreach ( $this->section_controls( $element, $section_id ) as $control_id => $control ) {
			if ( ( $control['type'] ?? '' ) !== Controls_Manager::MEDIA ) {
				continue;
			}

			if ( ! $this->accepts_images( $control['media_types'] ?? null ) ) {
				continue;
			}

			$element->update_control(
				$control_id,
				array(
					'type'         => AltMedia::TYPE,
					// update_control() merges TOP-LEVEL keys only, so this has
					// to be the complete rebuilt array — the same rule
					// Managers\Kit::rebuild_fields() works under.
					'selectors'    => $this->alt_selectors( $control ),
					// Keeps the control in the `controls` bucket now that it
					// carries selectors. Without it a control that had none —
					// the Image widget's own — becomes a style control on
					// frontend requests, where parse_dynamic_settings()
					// iterates get_controls() and a dynamic-tagged image would
					// stop resolving. Core marks its own background `color`
					// field the same way, for the same reason.
					'control_type' => 'content',
				)
			);
		}
	}

	/**
	 * The Alt rules for one control, derived from the control itself.
	 *
	 * Two branches, and this plugin names a CSS property in only one of them:
	 *
	 * - The control already declares something using {{URL}} — a background
	 *   image, or whatever else a widget invented. Mirror that declaration
	 *   verbatim under the Alt scope with the placeholder swapped. Nothing
	 *   here needs to know it was `background-image`, and {{SELECTOR}} is
	 *   already resolved to a real selector by the time a group control's
	 *   field reaches the stack.
	 * - The control declares nothing, which means the widget prints an <img>.
	 *   Replace what that <img> shows. CSS `content` on a replaced element has
	 *   been universal since Firefox 63.
	 *
	 * Originals stay FIRST in the returned array, and that is not cosmetic:
	 * when a placeholder resolves to '' with no || fallback,
	 * Files\CSS\Base::add_control_rules() throws and RETURNS, abandoning the
	 * control's remaining selectors. An unset Alt costs the widget nothing
	 * only because its own rules are already written by the time ours bails.
	 *
	 * @param array<mixed> $control The control as registered.
	 * @return array<string, string>
	 */
	private function alt_selectors( array $control ): array {
		$selectors = array();

		if ( isset( $control['selectors'] ) && is_array( $control['selectors'] ) ) {
			foreach ( $control['selectors'] as $selector => $declaration ) {
				if ( is_string( $selector ) && is_string( $declaration ) ) {
					$selectors[ $selector ] = $declaration;
				}
			}
		}

		$placeholder = '{{' . strtoupper( AltMedia::KEY_URL ) . '}}';
		$mirrored    = array();

		foreach ( $selectors as $selector => $declaration ) {
			if ( false === strpos( $declaration, '{{URL}}' ) ) {
				continue;
			}

			$mirrored[ self::ALT_SCOPE . $selector ] = str_replace( '{{URL}}', $placeholder, $declaration );
		}

		if ( empty( $mirrored ) ) {
			$mirrored[ self::ALT_SCOPE . '{{WRAPPER}} img' ] = 'content: url("' . $placeholder . '");';
		}

		return array_merge( $selectors, $mirrored );
	}

	/**
	 * The controls registered in one section, from BOTH stack buckets.
	 *
	 * Not Controls_Stack::get_section_controls(), and not get_controls():
	 * add_control_to_stack() files a control carrying `selectors` into
	 * `style_controls` rather than `controls` whenever
	 * Performance::should_optimize_controls() is true, and get_controls()
	 * merges that bucket back only while Performance::is_use_style_controls()
	 * is true — which is only during a CSS file rebuild. They are independent
	 * flags, so on a frontend request a bare read is blind to exactly the
	 * controls this most needs to see: `background_image` already carries
	 * selectors, so it is always in the second bucket there. Reading the raw
	 * stack makes the scan independent of both flags and of whether the stack
	 * was first registered inside the CSS build or before it.
	 *
	 * @param \Elementor\Controls_Stack $element    The registering stack.
	 * @param string                    $section_id The section being closed.
	 * @return array<string, array<mixed>>
	 */
	private function section_controls( $element, $section_id ): array {
		$stack = \Elementor\Plugin::$instance->controls_manager->get_element_stack( $element );

		if ( ! is_array( $stack ) ) {
			return array();
		}

		$buckets = array();

		foreach ( array( 'controls', 'style_controls' ) as $bucket ) {
			if ( isset( $stack[ $bucket ] ) && is_array( $stack[ $bucket ] ) ) {
				$buckets += $stack[ $bucket ];
			}
		}

		$controls = array();

		foreach ( $buckets as $control_id => $control ) {
			if ( ! is_string( $control_id ) || ! is_array( $control ) ) {
				continue;
			}

			if ( ( $control['section'] ?? '' ) === $section_id ) {
				$controls[ $control_id ] = $control;
			}
		}

		return $controls;
	}

	/**
	 * Control_Media defaults `media_types` to `['image']` in its own
	 * get_default_settings(), and the stored args only carry the key when the
	 * registering widget overrode it — so an absent one means images.
	 *
	 * @param mixed $media_types The control's `media_types` arg, if it set one.
	 */
	private function accepts_images( $media_types ): bool {
		if ( null === $media_types ) {
			return true;
		}

		return is_array( $media_types ) && in_array( 'image', $media_types, true );
	}
}
