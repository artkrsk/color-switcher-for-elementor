<?php

namespace Arts\ColorSwitcher\Tests\Integration;

use Arts\ColorSwitcher\Controls\AltMedia;
use Arts\ColorSwitcher\Managers\Kit;
use Arts\ColorSwitcher\Managers\Media;
use Elementor\Controls_Manager;
use Elementor\Plugin as ElementorPlugin;

/**
 * The Alt image control — the raster counterpart to the kit's Alt swatch.
 *
 * Every image media control on the site is re-typed to this one, so what is
 * worth pinning is the value shape and how an Alt resolves into CSS: a value
 * saved before the plugin existed must stay silent, and a set Alt must go
 * through the same attachment/size resolution the default image does.
 */
class AltMediaTest extends TestCase {

	/**
	 * Elementor caches the kit DOCUMENT, and WP's rollback between tests only
	 * restores the database — so an Alt swatch seeded here would otherwise
	 * survive in memory into every later test in the process, and the gate
	 * that reads it memoizes on top of that. Production never sees either:
	 * the document and the memo both live for one request.
	 *
	 * @var array<string, mixed>|null
	 */
	private $kit_settings_backup = null;

	public function set_up(): void {
		parent::set_up();

		$settings = $this->kit()->get_settings();

		$this->kit_settings_backup = is_array( $settings ) ? $settings : array();

		$this->flush_alt_colors_memo();
	}

	public function tear_down(): void {
		if ( null !== $this->kit_settings_backup ) {
			$this->kit()->set_settings( $this->kit_settings_backup );

			$this->kit_settings_backup = null;
		}

		$this->flush_alt_colors_memo();

		parent::tear_down();
	}

	public function test_control_type_is_registered(): void {
		$this->assertInstanceOf( AltMedia::class, $this->control() );
	}

	public function test_default_value_carries_both_alt_keys(): void {
		$default = $this->array_value( $this->control()->get_default_value() );

		// The parent's own keys have to survive the merge.
		$this->assertArrayHasKey( 'url', $default );
		$this->assertArrayHasKey( 'id', $default );

		$this->assertSame( '', $default[ AltMedia::KEY_URL ] ?? null );
		$this->assertSame( '', $default[ AltMedia::KEY_ID ] ?? null );
	}

	/**
	 * Control_Base_Multiple::get_style_value() reads sub-keys with a bare
	 * index, so every image saved before this plugin existed would warn on
	 * "Undefined array key" the moment its control carries an Alt selector.
	 * '' instead puts an absent Alt on Elementor's own no-value path, which
	 * is exactly what it should mean.
	 */
	public function test_style_value_of_a_legacy_value_is_empty_rather_than_a_warning(): void {
		$control = $this->control();

		$this->assertSame( '', $control->get_style_value( strtoupper( AltMedia::KEY_URL ), $this->legacy_value(), array() ) );
		$this->assertSame( '', $control->get_style_value( strtoupper( AltMedia::KEY_ID ), $this->legacy_value(), array() ) );
	}

	/** The parent's own placeholders must keep behaving exactly as before. */
	public function test_the_parents_own_placeholder_is_untouched(): void {
		$this->assertSame(
			'https://example.test/logo.png',
			$this->control()->get_style_value( 'URL', $this->legacy_value(), array() )
		);
	}

	/** An Alt inserted by URL carries no attachment, so it resolves as stored. */
	public function test_an_alt_url_without_an_attachment_resolves_as_stored(): void {
		$value = $this->legacy_value(
			array(
				AltMedia::KEY_URL => 'https://example.test/logo-alt.png',
				AltMedia::KEY_ID  => '',
			)
		);

		$this->assertSame(
			'https://example.test/logo-alt.png',
			$this->control()->get_style_value( strtoupper( AltMedia::KEY_URL ), $value, array() )
		);
	}

	/**
	 * A deleted attachment must degrade to the last known image rather than
	 * emptying the rule, which would silently revert to the default one.
	 */
	public function test_a_missing_attachment_falls_back_to_the_stored_url(): void {
		$value = $this->legacy_value(
			array(
				AltMedia::KEY_URL => 'https://example.test/logo-alt.png',
				AltMedia::KEY_ID  => 999999,
			)
		);

		$this->assertSame(
			'https://example.test/logo-alt.png',
			$this->control()->get_style_value( strtoupper( AltMedia::KEY_URL ), $value, array() )
		);
	}

	/**
	 * A library Alt goes through the attachment so it honours the same Image
	 * Size the default image does — Control_Media::get_style_value() does the
	 * same for its own URL, and rendering the pair at two resolutions is the
	 * bug this prevents.
	 */
	public function test_a_library_alt_resolves_through_the_attachment(): void {
		$attachment_id = $this->factory()->attachment->create_object(
			array(
				'file'           => 'logo-alt.png',
				'post_mime_type' => 'image/png',
			)
		);

		$value = $this->legacy_value(
			array(
				AltMedia::KEY_URL => 'https://example.test/stale.png',
				AltMedia::KEY_ID  => $attachment_id,
			)
		);

		$this->assertSame(
			wp_get_attachment_image_url( $attachment_id, 'full' ),
			$this->control()->get_style_value( strtoupper( AltMedia::KEY_URL ), $value, array() )
		);
	}

	public function test_image_widget_media_control_is_upgraded(): void {
		$this->seed_alt_color();

		$controls = $this->widget_controls( 'image' );

		$this->assertArrayHasKey( 'image', $controls );
		$this->assertSame( AltMedia::TYPE, $this->array_value( $controls['image'] )['type'] ?? null );
	}

	public function test_container_background_image_is_upgraded(): void {
		$this->seed_alt_color();

		$controls = $this->container_controls();

		$this->assertArrayHasKey( 'background_image', $controls );
		$this->assertSame( AltMedia::TYPE, $this->array_value( $controls['background_image'] )['type'] ?? null );
	}

	/**
	 * An Alt for a video or file picker is meaningless, and Elementor AI
	 * applies the same test before offering its own media button.
	 */
	public function test_a_non_image_media_control_is_left_alone(): void {
		$this->seed_alt_color();

		$controls  = $this->widget_controls( 'video' );
		$untouched = 0;

		foreach ( $controls as $control ) {
			if ( ! is_array( $control ) || Controls_Manager::MEDIA !== ( $control['type'] ?? '' ) ) {
				continue;
			}

			$media_types = $control['media_types'] ?? array( 'image' );

			if ( is_array( $media_types ) && ! in_array( 'image', $media_types, true ) ) {
				++$untouched;
			}
		}

		$this->assertGreaterThan( 0, $untouched, 'the fixture widget must actually carry a non-image media control' );
	}

	/**
	 * The whole feature is gated the way the head script is: a site that
	 * never configured an Alt swatch gets a control that could never fire,
	 * so it does not get the control at all.
	 */
	public function test_nothing_is_upgraded_without_an_alt_swatch(): void {
		$controls = $this->widget_controls( 'image' );

		$this->assertSame( Controls_Manager::MEDIA, $this->array_value( $controls['image'] )['type'] ?? null );
	}

	public function test_background_image_gains_a_mirrored_alt_rule(): void {
		$this->seed_alt_color();

		$selectors = $this->selectors_of( $this->container_controls(), 'background_image' );
		$original  = 'background-image: url("{{URL}}");';

		$this->assertContains( $original, $selectors, 'the widget keeps its own declaration' );
		$this->assertContains(
			str_replace( '{{URL}}', $this->alt_placeholder(), $original ),
			$selectors,
			'and gains the same declaration against the alt key'
		);

		$scoped = array_filter(
			array_keys( $selectors ),
			static fn( $selector ) => 0 === strpos( (string) $selector, Media::ALT_SCOPE )
		);
		$this->assertNotEmpty( $scoped );
	}

	/**
	 * Order is load-bearing. When a placeholder resolves to '' with no ||
	 * fallback, Files\CSS\Base::add_control_rules() throws and RETURNS,
	 * abandoning the control's remaining selectors — so the widget's own rules
	 * have to be written before ours.
	 */
	public function test_the_widgets_own_selectors_come_first(): void {
		$this->seed_alt_color();

		$keys      = array_keys( $this->selectors_of( $this->container_controls(), 'background_image' ) );
		$first_alt = null;
		$last_own  = null;

		foreach ( $keys as $index => $selector ) {
			if ( 0 === strpos( (string) $selector, Media::ALT_SCOPE ) ) {
				$first_alt = $first_alt ?? $index;
			} else {
				$last_own = $index;
			}
		}

		$this->assertNotNull( $first_alt );
		$this->assertNotNull( $last_own );
		$this->assertGreaterThan( $last_own, $first_alt );
	}

	/** No declaration of its own means the widget prints an <img>. */
	public function test_a_control_with_no_selectors_gets_the_content_swap(): void {
		$this->seed_alt_color();

		$selectors = $this->selectors_of( $this->widget_controls( 'image' ), 'image' );
		$key       = Media::ALT_SCOPE . '{{WRAPPER}} img';

		$this->assertArrayHasKey( $key, $selectors );
		$this->assertSame(
			'content: url("' . $this->alt_placeholder() . '");',
			$this->string_value( $selectors[ $key ] )
		);
	}

	/**
	 * Adding selectors to a control that had none would otherwise make it a
	 * style control, moving it out of the `controls` bucket on frontend
	 * requests — where parse_dynamic_settings() iterates get_controls(), so a
	 * dynamic-tagged image would stop resolving. Core marks its own background
	 * `color` field the same way.
	 */
	public function test_retyped_controls_stay_content_controls(): void {
		$this->seed_alt_color();

		foreach ( array( $this->container_controls(), $this->widget_controls( 'image' ) ) as $controls ) {
			foreach ( $controls as $control_id => $control ) {
				if ( ! is_array( $control ) || AltMedia::TYPE !== ( $control['type'] ?? '' ) ) {
					continue;
				}

				$this->assertSame( 'content', $control['control_type'] ?? null, (string) $control_id );
			}
		}
	}

	/**
	 * The regression that matters most: a site full of images and no Alt
	 * anywhere must render exactly as it did before the plugin was installed.
	 *
	 * It holds only because of selector ORDER. An unset Alt makes its
	 * placeholder resolve to '', and Files\CSS\Base::add_control_rules() then
	 * throws and returns — abandoning whatever is left of that control's
	 * selectors. The widget's own rule survives because it was already written
	 * to the stylesheet by the time ours bails.
	 */
	public function test_a_background_with_no_alt_still_renders_its_own_image(): void {
		$this->seed_alt_color();

		$css = $this->generated_css( array( 'url' => 'https://example.test/hero.jpg' ) );

		$this->assertStringContainsString( 'https://example.test/hero.jpg', $css );
		$this->assertStringNotContainsString( 'data-arts-cs', $css );
	}

	public function test_a_background_with_an_alt_renders_both(): void {
		$this->seed_alt_color();

		$css = $this->generated_css(
			array(
				'url'             => 'https://example.test/hero.jpg',
				AltMedia::KEY_URL => 'https://example.test/hero-alt.jpg',
			)
		);

		$this->assertStringContainsString( 'https://example.test/hero.jpg', $css );
		$this->assertStringContainsString( 'https://example.test/hero-alt.jpg', $css );
		$this->assertStringContainsString( 'data-arts-cs', $css );
	}

	/**
	 * The generated CSS for a page holding one container whose classic
	 * background carries the given media value.
	 *
	 * @param array<string, mixed> $image The background_image control value.
	 */
	private function generated_css( array $image ): string {
		// Document::save() gates on edit capability — a logged-out request
		// silently no-ops, which would make this pass for the wrong reason.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post_id  = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$document = ElementorPlugin::$instance->documents->get( $post_id );
		$this->assertNotNull( $document );

		$document->save(
			array(
				'elements' => array(
					array(
						'id'       => 'acsbg01',
						'elType'   => 'container',
						'settings' => array(
							'background_background' => 'classic',
							'background_image'      => array_merge( array( 'id' => '' ), $image ),
						),
						'elements' => array(),
					),
				),
			)
		);

		$css = \Elementor\Core\Files\CSS\Post::create( $post_id );
		$css->update();

		return $this->string_value( $css->get_content() );
	}

	private function alt_placeholder(): string {
		return '{{' . strtoupper( AltMedia::KEY_URL ) . '}}';
	}

	/**
	 * @param array<string, array<string, mixed>> $controls
	 * @return array<string, string>
	 */
	private function selectors_of( array $controls, string $control_id ): array {
		$this->assertArrayHasKey( $control_id, $controls );

		$selectors = $this->array_value( $this->array_value( $controls[ $control_id ] )['selectors'] ?? null );

		/** @var array<string, string> $selectors */
		return $selectors;
	}

	/**
	 * The kit's own media controls are deliberately left alone.
	 *
	 * Its tabs are Sub_Controls_Stack proxies that forward
	 * end_controls_section() to the kit, so the scan fires with the KIT as the
	 * registering stack, mid-registration. Reading the gate there re-enters
	 * Base_Object::ensure_settings(), which caches only once get_init_settings()
	 * returns — so the nested read would cache a half-registered snapshot of
	 * the kit's settings for the rest of the request.
	 */
	public function test_the_kits_own_media_controls_are_left_alone(): void {
		$this->seed_alt_color();

		$controls = $this->kit_controls();
		$media    = array();

		foreach ( $controls as $control_id => $control ) {
			if ( is_array( $control ) && Controls_Manager::MEDIA === ( $control['type'] ?? '' ) ) {
				$media[] = $control_id;
			}

			$this->assertNotSame( AltMedia::TYPE, $control['type'] ?? '', (string) $control_id );
		}

		$this->assertNotEmpty( $media, 'the kit must actually carry a media control for this to mean anything' );
	}

	/** Seeding the swatch is only half the job — the gate memoizes on top of it. */
	private function seed_alt_color(): void {
		$this->kit()->set_settings(
			'custom_colors',
			array(
				array(
					'_id'                => 'acsalt',
					'title'              => 'CS Alt',
					'color'              => '#111111',
					Kit::FIELD_COLOR_ALT => '#EEEEEE',
				),
			)
		);

		$this->flush_alt_colors_memo();
	}

	private function kit(): \Elementor\Core\Kits\Documents\Kit {
		$kit = ElementorPlugin::$instance->kits_manager->get_active_kit();

		$this->assertInstanceOf( \Elementor\Core\Kits\Documents\Kit::class, $kit );

		return $kit;
	}

	private function flush_alt_colors_memo(): void {
		$managers = new \ReflectionProperty( \Arts\ColorSwitcher\Base\Plugin::class, 'managers' );
		$managers->setAccessible( true );

		$container = $managers->getValue( \Arts\ColorSwitcher\Plugin::instance() );
		$this->assertNotNull( $container );

		$memo = new \ReflectionProperty( Kit::class, 'memo' );
		$memo->setAccessible( true );
		$memo->setValue( $container->kit, array() );
	}

	/** @return array<string, array<string, mixed>> */
	private function widget_controls( string $widget_type ): array {
		return $this->fresh_controls(
			array(
				'id'         => '9f8e7d6',
				'elType'     => 'widget',
				'widgetType' => $widget_type,
				'settings'   => array(),
				'elements'   => array(),
			)
		);
	}

	/** @return array<string, array<string, mixed>> */
	private function container_controls(): array {
		return $this->fresh_controls(
			array(
				'id'       => '1a2b3c4',
				'elType'   => 'container',
				'settings' => array(),
				'elements' => array(),
			)
		);
	}

	/**
	 * Controls of a freshly REGISTERED element, as the CSS generator reads
	 * them.
	 *
	 * Elementor caches a control stack per element TYPE for the life of the
	 * process, so without delete_stack() every test here would read whichever
	 * stack the first one happened to build — and the gate test, which needs
	 * registration to run against a kit with no Alt swatch, would silently
	 * assert against a stack built while one was seeded.
	 *
	 * @param array<string, mixed> $data The element data to instantiate.
	 * @return array<string, array<string, mixed>>
	 */
	private function fresh_controls( array $data ): array {
		$element = ElementorPlugin::$instance->elements_manager->create_element_instance( $data );
		$this->assertNotNull( $element );

		ElementorPlugin::$instance->controls_manager->delete_stack( $element );

		\Elementor\Core\Frontend\Performance::set_use_style_controls( true );
		$controls = $element->get_controls();
		\Elementor\Core\Frontend\Performance::set_use_style_controls( false );

		$this->assertIsArray( $controls );

		/** @var array<string, array<string, mixed>> $controls */
		return $controls;
	}

	/**
	 * A media value as Elementor stores one when the image was inserted by
	 * URL: a real url, no attachment, and none of our keys.
	 *
	 * @param array<string, mixed> $overrides Sub-keys to add or replace.
	 * @return array<string, mixed>
	 */
	private function legacy_value( array $overrides = array() ): array {
		return array_merge(
			array(
				'url'  => 'https://example.test/logo.png',
				'id'   => '',
				'size' => '',
			),
			$overrides
		);
	}

	private function control(): AltMedia {
		$control = ElementorPlugin::$instance->controls_manager->get_control( AltMedia::TYPE );

		$this->assertInstanceOf( AltMedia::class, $control );

		return $control;
	}
}
