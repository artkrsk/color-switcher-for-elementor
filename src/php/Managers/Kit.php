<?php

namespace Arts\ColorSwitcher\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Base\Manager as BaseManager;
use Elementor\Controls_Manager;

class Kit extends BaseManager {

	const FIELD_COLOR_ALT = 'color_alt';

	/** @var array<string, bool> Per-request memo: reading kit settings is not free. */
	private array $memo = array();

	/**
	 * Whether any Global Color actually carries an Alt value. The pre-paint
	 * head script and the Alt image controls are gated on this — a site that
	 * never configured a single Alt swatch ships neither — and the toggle
	 * widget uses it to explain itself in the editor rather than render a
	 * silent no-op.
	 */
	public function has_alt_colors(): bool {
		if ( isset( $this->memo['has_alt'] ) ) {
			return $this->memo['has_alt'];
		}

		$kit                   = $this->get_active_kit();
		$this->memo['has_alt'] = false;

		if ( ! $kit ) {
			return false;
		}

		foreach ( array( 'system_colors', 'custom_colors' ) as $repeater ) {
			$rows = $kit->get_settings( $repeater );

			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				if ( is_array( $row ) && ! empty( $row[ self::FIELD_COLOR_ALT ] ) ) {
					$this->memo['has_alt'] = true;

					return true;
				}
			}
		}

		return false;
	}

	/** @return \Elementor\Core\Kits\Documents\Kit|null */
	private function get_active_kit() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

		if ( ! $kit instanceof \Elementor\Core\Kits\Documents\Kit || ! $kit->get_main_id() ) {
			return null;
		}

		return $kit;
	}

	/**
	 * Add the Alt swatch to both Global Colors repeaters and re-target the
	 * native color field onto the color-mix layer.
	 *
	 * Fired by `elementor/element/kit/section_global_colors/before_section_end`
	 * with the registering Kit stack itself, while its Global Colors section is
	 * still open — so controls added here land at the bottom of that tab.
	 *
	 * @param \Elementor\Core\Kits\Documents\Kit $kit The registering kit stack.
	 */
	public function inject_global_colors_controls( $kit ): void {
		foreach ( array( 'system_colors', 'custom_colors' ) as $control_id ) {
			$control = $kit->get_controls( $control_id );

			if ( ! is_array( $control ) || empty( $control['fields'] ) || ! is_array( $control['fields'] ) ) {
				continue;
			}

			/** @var array<string, array<string, mixed>> $fields */
			$fields = $control['fields'];

			// update_control() merges top-level keys only: always pass the
			// complete rebuilt fields array, never a partial one.
			$kit->update_control(
				$control_id,
				array( 'fields' => $this->rebuild_fields( $fields ) )
			);
		}
	}

	/**
	 * Rebuild a Global Colors repeater fields array.
	 *
	 * The kit repeater row view mounts its live hex label and sort/remove
	 * tools into the LAST color control's wrapper, so the injected Alt field
	 * must be registered BEFORE the native `color` field (visual order is
	 * restored with flex `order` in the editor panel CSS).
	 *
	 * @param array<string, array<string, mixed>> $fields Native fields, keyed by name.
	 * @return array<string, array<string, mixed>>
	 */
	private function rebuild_fields( array $fields ): array {
		$rebuilt = array();

		foreach ( $fields as $key => $field ) {
			if ( 'color' === $key ) {
				$rebuilt[ self::FIELD_COLOR_ALT ] = $this->get_alt_field();

				// The native value feeds `--arts-cs-c-{id}`, and the public
				// `--e-global-color-{id}` becomes the scalar-driven mix of the
				// default/alt pair. Consumers never change; a missing Alt falls
				// back to the default value.
				$field['selectors'] = array(
					'{{WRAPPER}}' => '--arts-cs-c-{{_id.VALUE}}: {{VALUE}}; --e-global-color-{{_id.VALUE}}: color-mix(in oklab, var(--arts-cs-c-{{_id.VALUE}}), var(--arts-cs-c-{{_id.VALUE}}-alt, var(--arts-cs-c-{{_id.VALUE}})) calc(var(--arts-cs-p, 0) * 100%));',
				);
			}

			$rebuilt[ $key ] = $field;
		}

		return $rebuilt;
	}

	/** @return array<string, mixed> */
	private function get_alt_field(): array {
		return array(
			'name'       => self::FIELD_COLOR_ALT,
			'label'      => esc_html__( 'Alt', 'color-switcher-for-elementor' ),
			'show_label' => false,
			'type'       => Controls_Manager::COLOR,
			'global'     => array( 'active' => false ),
			'selectors'  => array(
				'{{WRAPPER}}' => '--arts-cs-c-{{_id.VALUE}}-alt: {{VALUE}};',
			),
		);
	}
}
