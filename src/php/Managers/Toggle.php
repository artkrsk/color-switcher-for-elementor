<?php

namespace Arts\ColorSwitcher\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Base\Manager as BaseManager;

/**
 * The toggle's markup, in one place because two things render it — the
 * Elementor widget and the shortcode — and they must not drift. A skin is
 * therefore a shape this class knows how to draw, not something the widget
 * owns, which is what lets `[arts_color_switcher_toggle skin="buttons"]`
 * exist at all.
 *
 * Nothing rendered here states the visitor's own state. The page may come
 * from a cache that knows nothing about them, so which option looks active is
 * decided in CSS off `<html>` — stamped before first paint by the head script
 * — and the runtime only corrects what CSS cannot reach: ARIA, and a select's
 * value.
 */
class Toggle extends BaseManager {

	const MODE_BINARY = 'binary';
	const MODE_CYCLE  = 'cycle';

	const SKIN_ICON     = 'icon';
	const SKIN_SWITCH   = 'switch';
	const SKIN_BUTTONS  = 'buttons';
	const SKIN_DROPDOWN = 'dropdown';

	const BUTTONS_JOINED   = 'joined';
	const BUTTONS_SEPARATE = 'separate';

	const SHORTCODE = 'arts_color_switcher_toggle';

	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	/**
	 * @param array<string> $atts    Shortcode attributes.
	 * @param string|null   $content Enclosed content, unused.
	 * @param string        $tag     The shortcode tag, unused.
	 */
	public function render_shortcode( $atts, $content = null, $tag = '' ): string {
		$resolved = shortcode_atts(
			array(
				'skin'          => self::SKIN_ICON,
				'mode'          => self::MODE_BINARY,
				'caption'       => '',
				'name'          => '',
				'label_system'  => esc_html__( 'System', 'color-switcher-for-elementor' ),
				'label_default' => esc_html__( 'Light', 'color-switcher-for-elementor' ),
				'label_alt'     => esc_html__( 'Dark', 'color-switcher-for-elementor' ),
				'style'         => self::BUTTONS_JOINED,
				'icons'         => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		$resolved['show_icons'] = 'no' !== $resolved['icons'];

		return $this->render( $resolved );
	}

	/**
	 * @param array<string, mixed> $args skin, mode, labels, and optional icon HTML.
	 */
	public function render( array $args ): string {
		$args = $this->resolve( $args );

		if ( self::SKIN_BUTTONS === $args['skin'] ) {
			return $this->render_buttons( $args );
		}

		if ( self::SKIN_DROPDOWN === $args['skin'] ) {
			return $this->render_dropdown( $args );
		}

		return $this->render_button( $args );
	}

	/**
	 * Fills in every value the renderers read, so a shortcode with no
	 * attributes and a widget with every control set arrive the same shape.
	 *
	 * @param array<string, mixed> $args
	 * @return array{skin: string, mode: string, caption: string, name: string, labels: array<string, string>, names: array<string, string>, icons: array<string, string>, style: string, show_icons: bool}
	 */
	private function resolve( array $args ): array {
		$skin  = is_string( $args['skin'] ?? null ) ? $args['skin'] : self::SKIN_ICON;
		$style = is_string( $args['style'] ?? null ) ? $args['style'] : '';

		if ( ! in_array( $skin, array( self::SKIN_ICON, self::SKIN_SWITCH, self::SKIN_BUTTONS, self::SKIN_DROPDOWN ), true ) ) {
			$skin = self::SKIN_ICON;
		}

		$text = function ( $value, string $fallback ): string {
			return is_string( $value ) && '' !== $value ? $value : $fallback;
		};

		$labels = array(
			'system'  => $text( $args['label_system'] ?? '', '' ),
			'default' => $text( $args['label_default'] ?? '', '' ),
			'alt'     => $text( $args['label_alt'] ?? '', '' ),
		);

		$mode = self::MODE_CYCLE === ( $args['mode'] ?? '' ) ? self::MODE_CYCLE : self::MODE_BINARY;

		// Every skin but the Switch is asked how many states it has, because a
		// switch has no third position to put one in. Deriving it from whether
		// the System option had a word was elegant while a word was all an
		// option could be, and stopped being so the moment icons made an
		// icon-only option legitimate: clearing the label to drop the text
		// deleted the option instead.
		if ( self::SKIN_SWITCH === $skin ) {
			$mode = self::MODE_BINARY;
		}

		return array(
			'skin'       => $skin,
			'mode'       => $mode,
			'caption'    => $text( $args['caption'] ?? '', '' ),
			'name'       => $text( $args['name'] ?? '', esc_html__( 'Color theme', 'color-switcher-for-elementor' ) ),
			// What is printed: the author's word, or nothing at all.
			'labels'     => $labels,
			// What is announced — and what the Dropdown has no alternative to,
			// since an option with no text is not a control.
			'names'      => array(
				'system'  => $text( $labels['system'], esc_html__( 'System', 'color-switcher-for-elementor' ) ),
				'default' => $text( $labels['default'], esc_html__( 'Light', 'color-switcher-for-elementor' ) ),
				'alt'     => $text( $labels['alt'], esc_html__( 'Dark', 'color-switcher-for-elementor' ) ),
			),
			'icons'      => array(
				'system'  => $text( $args['icon_system'] ?? '', $this->default_icon( 'system' ) ),
				'default' => $text( $args['icon_default'] ?? '', $this->default_icon( 'default' ) ),
				'alt'     => $text( $args['icon_alt'] ?? '', $this->default_icon( 'alt' ) ),
			),
			'style'      => self::BUTTONS_SEPARATE === $style ? self::BUTTONS_SEPARATE : self::BUTTONS_JOINED,
			'show_icons' => ! array_key_exists( 'show_icons', $args ) || (bool) $args['show_icons'],
		);
	}

	/**
	 * The states a control offers, in the order it offers them.
	 *
	 * @return array<int, string>
	 */
	private function states( string $mode ): array {
		return self::MODE_CYCLE === $mode
			? array( 'system', 'default', 'alt' )
			: array( 'default', 'alt' );
	}

	/**
	 * The Icon and Switch skins: one control that carries the state itself.
	 *
	 * `role="switch"` goes only where the control looks like a switch — it is
	 * announced inconsistently enough that a pressed button is the safer
	 * reading of an icon. Both are named after the palette they turn on rather
	 * than after the act of switching, so "Dark, pressed" says something.
	 *
	 * @param array{skin: string, mode: string, caption: string, name: string, labels: array<string, string>, names: array<string, string>, icons: array<string, string>, style: string, show_icons: bool} $args
	 */
	private function render_button( array $args ): string {
		$is_switch = self::SKIN_SWITCH === $args['skin'];
		$cycles    = self::MODE_CYCLE === $args['mode'];

		$attributes = array_merge(
			$this->root_attributes( $args ),
			array( 'type' => 'button' )
		);

		if ( $is_switch ) {
			$attributes['role']         = 'switch';
			$attributes['aria-checked'] = 'false';
		} elseif ( ! $cycles ) {
			$attributes['aria-pressed'] = 'false';
		}

		// A visible caption is the accessible name already; naming it twice
		// would announce something other than what is on screen.
		if ( '' === $args['caption'] ) {
			$name = $cycles
				? $args['name'] . ': ' . $args['names']['system']
				: $args['names']['alt'];

			$attributes['aria-label'] = $name;
			$attributes['title']      = $name;
		}

		$icons = $cycles ? $this->icon( 'system', $args ) : '';

		foreach ( array( 'default', 'alt' ) as $state ) {
			$icons .= $this->icon( $state, $args );
		}

		if ( $is_switch ) {
			$icons = sprintf(
				'<span class="arts-cs-toggle__track">%s<span class="arts-cs-toggle__knob"></span></span>',
				$icons // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		return sprintf(
			'<button%1$s>%2$s%3$s</button>',
			$this->attributes( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$icons, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$this->caption( $args['caption'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * The Buttons skin: every option visible at once, so nothing has to be
	 * cycled through to be discovered. With two options and no stored choice
	 * NEITHER is pressed — that empty state is how two controls express the
	 * third state, and pressing the pressed one returns to it.
	 *
	 * `aria-pressed` buttons rather than a radiogroup: a radio group has to
	 * have a selection, and this one deliberately may not — which is also why
	 * the Joined layout's mark can be hidden rather than parked somewhere.
	 *
	 * @param array{skin: string, mode: string, caption: string, name: string, labels: array<string, string>, names: array<string, string>, icons: array<string, string>, style: string, show_icons: bool} $args
	 */
	private function render_buttons( array $args ): string {
		$states = $this->states( $args['mode'] );

		$attributes = array_merge(
			$this->root_attributes( $args ),
			array(
				'role'       => 'group',
				'aria-label' => $args['name'],
			)
		);

		if ( self::BUTTONS_JOINED === $args['style'] ) {
			// The one number the stylesheet cannot work out for itself. Every
			// other dimension of the sliding mark is derived from it, the
			// track's padding and the gap, which is what keeps a three-option
			// track as symmetric as a two-option one.
			$attributes['style'] = '--arts-cs-seg-count: ' . count( $states ) . ';';
		}

		$options = '';

		foreach ( $states as $state ) {
			$icon  = $args['show_icons'] ? $this->icon( $state, $args ) : '';
			$label = $this->caption( $args['labels'][ $state ] );

			// Icons off and no word of their own would leave a button with
			// nothing in it, which is the one arrangement this cannot render.
			if ( '' === $icon && '' === $label ) {
				$label = $this->caption( $args['names'][ $state ] );
			}

			$options .= sprintf(
				'<button type="button" class="arts-cs-toggle__option" data-arts-cs-set="%1$s" aria-pressed="false"%2$s>%3$s%4$s</button>',
				esc_attr( $state ),
				'' === $label ? sprintf( ' aria-label="%s"', esc_attr( $args['names'][ $state ] ) ) : '',
				$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		return sprintf(
			'<div%1$s>%2$s%3$s</div>',
			$this->attributes( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			// Joined is one object, and the mark travelling to the pinned
			// option is what says so. Separate has nothing to slide within.
			self::BUTTONS_JOINED === $args['style'] ? '<span class="arts-cs-toggle__knob"></span>' : '',
			$options // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * The Dropdown skin. A native select, for the platform's own keyboard and
	 * touch handling — the cost is that CSS cannot set its value, so this is
	 * the one control the runtime has to correct after paint. Its options print
	 * the author's words like everything else, so clearing one empties the
	 * option rather than quietly refilling it.
	 *
	 * @param array{skin: string, mode: string, caption: string, name: string, labels: array<string, string>, names: array<string, string>, icons: array<string, string>, style: string, show_icons: bool} $args
	 */
	private function render_dropdown( array $args ): string {
		$options = '';

		foreach ( $this->states( $args['mode'] ) as $state ) {
			$options .= sprintf(
				'<option value="%1$s">%2$s</option>',
				esc_attr( $state ),
				esc_html( $args['labels'][ $state ] )
			);
		}

		return sprintf(
			'<div%1$s><select class="arts-cs-toggle__select" aria-label="%2$s">%3$s</select></div>',
			$this->attributes( $this->root_attributes( $args ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( $args['name'] ),
			$options // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * What every skin's root carries: the shape, the state count, and the
	 * words the runtime announces — which come from here so they are the
	 * author's and the translator's, never the script's.
	 *
	 * @param array{skin: string, mode: string, style: string, names: array<string, string>, name: string} $args
	 * @return array<string, string>
	 */
	private function root_attributes( array $args ): array {
		$classes = array(
			'arts-cs-toggle',
			'arts-cs-toggle_' . $args['skin'],
			'js-arts-cs-toggle',
		);

		if ( self::SKIN_BUTTONS === $args['skin'] ) {
			$classes[] = 'arts-cs-toggle_' . $args['style'];
		}

		return array(
			'class'                      => implode( ' ', $classes ),
			'data-arts-cs-toggle'        => $args['skin'],
			'data-arts-cs-mode'          => $args['mode'],
			'data-arts-cs-name'          => $args['name'],
			'data-arts-cs-label-system'  => $args['names']['system'],
			'data-arts-cs-label-default' => $args['names']['default'],
			'data-arts-cs-label-alt'     => $args['names']['alt'],
		);
	}

	/** @param array<string, string> $attributes */
	private function attributes( array $attributes ): string {
		$rendered = '';

		foreach ( $attributes as $name => $value ) {
			$rendered .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		return $rendered;
	}

	private function caption( string $text ): string {
		return '' === $text
			? ''
			: sprintf( '<span class="arts-cs-toggle__label">%s</span>', esc_html( $text ) );
	}

	/**
	 * Every state's icon ships and CSS decides which is visible, so the swap
	 * costs no JS and cannot lag the theme it describes.
	 *
	 * @param array{icons: array<string, string>} $args
	 */
	private function icon( string $state, array $args ): string {
		return sprintf(
			'<span class="arts-cs-toggle__icon arts-cs-toggle__icon_%s" aria-hidden="true">%s</span>',
			esc_attr( $state ),
			$args['icons'][ $state ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/** Sun, moon and a monitor, inline so the shortcode needs no config. */
	private function default_icon( string $state ): string {
		if ( 'system' === $state ) {
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>';
		}

		if ( 'alt' === $state ) {
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>';
		}

		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
	}
}
