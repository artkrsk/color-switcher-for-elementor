<?php

namespace Arts\ColorSwitcher\Tests\Integration;

/**
 * The fixtures every Integration test builds on. Nothing here asserts
 * anything about the plugin — these only reach past Elementor's own ceremony
 * to the kit, elements and documents, failing the calling test by name when
 * that ceremony does not hold up.
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * A real site always has a kit; a fresh test DB does not, and Elementor
	 * itself reads the active kit while registering Container's own controls
	 * (kit.php reads $this->post->post_status) — so without this, core fatals
	 * before any assertion of ours runs.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! \Elementor\Plugin::$instance->kits_manager->get_active_kit()->get_main_id() ) {
			\Elementor\Plugin::$instance->kits_manager->create_new_kit( 'CS Tests' );
		}
	}

	/**
	 * The active kit's controls as the CSS generator reads them: Optimized
	 * Control Loading files every control carrying `selectors` into a separate
	 * style_controls stack, and get_controls() merges it back only under this
	 * flag.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function kit_controls(): array {
		\Elementor\Core\Frontend\Performance::set_use_style_controls( true );
		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		$this->assertNotNull( $kit );
		$controls = $kit->get_controls();
		\Elementor\Core\Frontend\Performance::set_use_style_controls( false );

		$this->assertIsArray( $controls );

		/** @var array<string, array<string, mixed>> $controls */
		return $controls;
	}

	/**
	 * Controls of a freshly instantiated element of the given type.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function element_controls( string $el_type ): array {
		\Elementor\Core\Frontend\Performance::set_use_style_controls( true );
		$element = \Elementor\Plugin::$instance->elements_manager->create_element_instance(
			array(
				'id'       => '1a2b3c4',
				'elType'   => $el_type,
				'settings' => array(),
				'elements' => array(),
			)
		);
		$this->assertNotNull( $element );
		$controls = $element->get_controls();
		\Elementor\Core\Frontend\Performance::set_use_style_controls( false );

		$this->assertIsArray( $controls );

		/** @var array<string, array<string, mixed>> $controls */
		return $controls;
	}

	/**
	 * @param mixed $value
	 * @return array<string, mixed>
	 */
	protected function array_value( $value ): array {
		$this->assertIsArray( $value );

		/** @var array<string, mixed> $value */
		return $value;
	}

	/** @param mixed $value */
	protected function string_value( $value ): string {
		$this->assertIsString( $value );

		return $value;
	}
}
