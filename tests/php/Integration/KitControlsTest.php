<?php

namespace Arts\ColorSwitcher\Tests\Integration;

use Arts\ColorSwitcher\Managers\Kit;
use Elementor\Controls_Manager;

/**
 * The injection into Elementor's own Global Colors repeaters — the one place
 * the plugin rewrites core's controls rather than adding its own.
 */
class KitControlsTest extends TestCase {

	/** @return array<int, array<int, string>> */
	public function repeater_provider(): array {
		return array(
			array( 'system_colors' ),
			array( 'custom_colors' ),
		);
	}

	/**
	 * @dataProvider repeater_provider
	 */
	public function test_alt_field_is_injected( string $control_id ): void {
		$fields = $this->repeater_fields( $control_id );

		$this->assertArrayHasKey( Kit::FIELD_COLOR_ALT, $fields );

		$alt = $this->array_value( $fields[ Kit::FIELD_COLOR_ALT ] );
		$this->assertSame( Controls_Manager::COLOR, $alt['type'] );

		$selectors = $this->array_value( $alt['selectors'] ?? null );
		$this->assertSame(
			'--arts-cs-c-{{_id.VALUE}}-alt: {{VALUE}};',
			$selectors['{{WRAPPER}}'] ?? null
		);
	}

	/**
	 * The row view mounts its hex label and row tools into the LAST color
	 * control's wrapper — the Alt field must stay ahead of the native one or
	 * that furniture lands on our swatch.
	 *
	 * @dataProvider repeater_provider
	 */
	public function test_alt_field_precedes_the_native_color_field( string $control_id ): void {
		$keys = array_keys( $this->repeater_fields( $control_id ) );

		$this->assertLessThan(
			array_search( 'color', $keys, true ),
			array_search( Kit::FIELD_COLOR_ALT, $keys, true )
		);
	}

	/**
	 * @dataProvider repeater_provider
	 */
	public function test_native_color_field_emits_the_mix_layer( string $control_id ): void {
		$fields = $this->repeater_fields( $control_id );
		$color  = $this->array_value( $fields['color'] ?? null );

		$selectors = $this->array_value( $color['selectors'] ?? null );
		$rule      = $this->string_value( $selectors['{{WRAPPER}}'] ?? null );

		$this->assertStringContainsString( '--arts-cs-c-{{_id.VALUE}}: {{VALUE}}', $rule );
		$this->assertStringContainsString( '--e-global-color-{{_id.VALUE}}: color-mix(in oklab', $rule );
		$this->assertStringContainsString( 'var(--arts-cs-c-{{_id.VALUE}}-alt, var(--arts-cs-c-{{_id.VALUE}}))', $rule );
		$this->assertStringContainsString( 'calc(var(--arts-cs-p, 0) * 100%)', $rule );
	}

	/**
	 * Morph timing is a customization var, not a control: the stylesheet
	 * carries the defaults as var() fallbacks, and a theme overrides with one
	 * line of CSS. Nothing in the kit may emit the vars — that is the theme
	 * author's slot, and re-adding controls for them was decided against.
	 */
	public function test_kit_registers_no_timing_controls(): void {
		$controls = $this->kit_controls();

		$this->assertArrayNotHasKey( 'arts_cs_transition_duration', $controls );
		$this->assertArrayNotHasKey( 'arts_cs_transition_easing', $controls );
	}

	/**
	 * A saved Alt value must survive the real save pipeline — the injected
	 * sub-field is not part of core's own repeater schema.
	 */
	public function test_alt_value_survives_a_kit_save(): void {
		// Document::save() gates on edit capability — a logged-out request
		// silently no-ops, which would make this test pass for the wrong reason.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		$this->assertNotNull( $kit );

		$rows = array(
			array(
				'_id'                => 'acstest',
				'title'              => 'CS Test',
				'color'              => '#123456',
				Kit::FIELD_COLOR_ALT => '#ABCDEF',
			),
		);

		$kit->save( array( 'settings' => array( 'custom_colors' => $rows ) ) );

		$saved = get_post_meta( $kit->get_main_id(), '_elementor_page_settings', true );
		$this->assertIsArray( $saved );
		$this->assertSame( '#ABCDEF', $saved['custom_colors'][0][ Kit::FIELD_COLOR_ALT ] ?? null );
	}

	/** @return array<string, mixed> */
	private function repeater_fields( string $control_id ): array {
		$controls = $this->kit_controls();

		$this->assertArrayHasKey( $control_id, $controls );

		$control = $this->array_value( $controls[ $control_id ] );

		return $this->array_value( $control['fields'] ?? null );
	}
}
