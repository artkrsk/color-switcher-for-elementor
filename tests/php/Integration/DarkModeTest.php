<?php

namespace Arts\ColorSwitcher\Tests\Integration;

use Arts\ColorSwitcher\Managers\Documents;
use Arts\ColorSwitcher\Managers\Toggle;
use Arts\ColorSwitcher\Widgets\Skins;

class DarkModeTest extends TestCase {

	/**
	 * The control carries three values because the visitor's device can decide:
	 * "let it" (Auto) and "no, light for everyone" (Default) are different
	 * instructions, and an author needs both. A third value existed briefly
	 * once before and was cut along with the site-wide default it served —
	 * this one earns its place differently, by being the only way to overrule
	 * a device.
	 *
	 * Auto keeps the empty string so it stays the stored default on every
	 * untouched document and nothing needs migrating.
	 */
	public function test_page_theme_offers_auto_default_and_alt(): void {
		$document = \Elementor\Plugin::$instance->documents->get( self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		$this->assertNotNull( $document );

		$controls = $document->get_controls();
		$this->assertIsArray( $controls );
		$this->assertArrayHasKey( Documents::CONTROL_PAGE_THEME, $controls );

		$control = $this->array_value( $controls[ Documents::CONTROL_PAGE_THEME ] );

		$this->assertSame( Documents::THEME_AUTO, $control['default'] ?? null );
		$this->assertSame( '', Documents::THEME_AUTO, 'Auto must stay the empty string or every untouched page changes meaning.' );
	}

	public function test_toggle_widget_is_registered(): void {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'arts-color-switcher-toggle' );

		$this->assertNotNull( $widget );
		$this->assertSame( 'arts-color-switcher-toggle', $widget->get_name() );
	}

	/**
	 * Text the widget renders — a caption beside an icon, or the words on the
	 * Buttons skin's options — needs the same typography controls any text
	 * widget would offer.
	 */
	public function test_toggle_offers_label_typography(): void {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'arts-color-switcher-toggle' );
		$this->assertNotNull( $widget );

		// Group controls carry selectors, so Optimized Control Loading files
		// them into the style stack — invisible to get_controls() without this
		// flag, the same way the kit's own repeater fields are.
		\Elementor\Core\Frontend\Performance::set_use_style_controls( true );
		$controls = $widget->get_controls();
		\Elementor\Core\Frontend\Performance::set_use_style_controls( false );

		$this->assertIsArray( $controls );
		$this->assertArrayHasKey( 'label_typography_typography', $controls );
		$this->assertArrayHasKey( 'label_typography_font_size', $controls );
	}

	public function test_shortcode_is_registered(): void {
		$this->assertTrue( shortcode_exists( Toggle::SHORTCODE ) );
	}

	/** Every shape is a skin, so there is no unskinned rendering to fall back to. */
	public function test_toggle_registers_every_skin(): void {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'arts-color-switcher-toggle' );
		$this->assertNotNull( $widget );

		$this->assertSame(
			array( Toggle::SKIN_ICON, Toggle::SKIN_SWITCH, Toggle::SKIN_BUTTONS, Toggle::SKIN_DROPDOWN ),
			array_keys( $widget->get_skins() )
		);
	}

	/**
	 * `role="switch"` is announced inconsistently enough that it is worth only
	 * where the control looks like a switch. An icon is a button, and reports
	 * itself as pressed.
	 */
	public function test_only_the_switch_skin_claims_switch_semantics(): void {
		$icon = do_shortcode( '[' . Toggle::SHORTCODE . ']' );

		$this->assertStringContainsString( 'aria-pressed=', $icon );
		$this->assertStringNotContainsString( 'role="switch"', $icon );

		$switch = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="switch"]' );

		$this->assertStringContainsString( 'role="switch"', $switch );
		$this->assertStringContainsString( 'aria-checked=', $switch );
		$this->assertStringNotContainsString( 'aria-pressed=', $switch );
	}

	/** A knob has two positions and nowhere to stand for "follow the system". */
	public function test_the_switch_skin_refuses_a_third_state(): void {
		$markup = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="switch" mode="cycle"]' );

		$this->assertStringContainsString( 'data-arts-cs-mode="binary"', $markup );
		$this->assertStringNotContainsString( 'arts-cs-toggle__icon_system', $markup );
	}

	/** Both icons ship; CSS decides which is visible, so the swap costs no JS. */
	public function test_toggle_ships_both_icons(): void {
		$markup = do_shortcode( '[' . Toggle::SHORTCODE . ']' );

		$this->assertStringContainsString( 'arts-cs-toggle__icon_default', $markup );
		$this->assertStringContainsString( 'arts-cs-toggle__icon_alt', $markup );
	}

	/**
	 * How many states a control offers is stated, not inferred. It was derived
	 * from whether the System option had a word, which held while a word was
	 * all an option could be — and stopped holding the moment icons made an
	 * icon-only option legitimate, because clearing the label to drop the text
	 * deleted the option with it.
	 */
	public function test_the_state_count_is_stated( ): void {
		$three = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="buttons" mode="cycle"]' );

		$this->assertSame( 3, substr_count( $three, 'data-arts-cs-set=' ) );
		$this->assertStringContainsString( 'data-arts-cs-mode="cycle"', $three );

		$two = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="buttons"]' );

		$this->assertSame( 2, substr_count( $two, 'data-arts-cs-set=' ) );
		$this->assertStringNotContainsString( 'data-arts-cs-set="system"', $two );

		// And a System option with no word of its own is still an option: its
		// icon is what identifies it.
		$iconOnly = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="buttons" mode="cycle" label_system=""]' );

		$this->assertStringContainsString( 'data-arts-cs-set="system"', $iconOnly );
		$this->assertStringContainsString( 'arts-cs-toggle__icon_system', $iconOnly );

		$dropdown = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="dropdown" mode="cycle"]' );

		$this->assertSame( 3, substr_count( $dropdown, '<option value=' ) );
	}

	/** A switch has two positions and nowhere to put a third. */
	public function test_only_the_switch_is_not_asked_how_many_states_it_has(): void {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'arts-color-switcher-toggle' );
		$this->assertNotNull( $widget );

		$controls = $widget->get_controls();
		$this->assertIsArray( $controls );

		$mode = $this->array_value( $controls['mode'] ?? array() );

		$this->assertSame(
			array( Toggle::SKIN_ICON, Toggle::SKIN_BUTTONS, Toggle::SKIN_DROPDOWN ),
			$mode['condition']['_skin'] ?? null
		);
	}

	/**
	 * The server never states the visitor's own state: the page may be cached
	 * and served to everyone. Nothing rendered is pressed, and CSS decides what
	 * looks active from the attribute the head script stamps.
	 */
	public function test_the_buttons_skin_presses_nothing_it_cannot_know(): void {
		$markup = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="buttons" mode="cycle"]' );

		$this->assertSame( 3, substr_count( $markup, 'aria-pressed="false"' ) );
		$this->assertStringNotContainsString( 'aria-pressed="true"', $markup );
	}

	public function test_the_dropdown_skin_renders_a_native_select(): void {
		$markup = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="dropdown" mode="cycle"]' );

		$this->assertStringContainsString( '<select class="arts-cs-toggle__select"', $markup );
		$this->assertSame( 3, substr_count( $markup, '<option value=' ) );
		$this->assertStringNotContainsString( 'selected', $markup );
	}

	/**
	 * The words a control shows and announces come from here, translated,
	 * rather than from the script where they were hardcoded English.
	 */
	public function test_toggle_carries_the_words_the_runtime_announces(): void {
		$markup = do_shortcode( '[' . Toggle::SHORTCODE . ' mode="cycle"]' );

		$this->assertStringContainsString( 'data-arts-cs-label-system="System"', $markup );
		$this->assertStringContainsString( 'data-arts-cs-label-default="Light"', $markup );
		$this->assertStringContainsString( 'data-arts-cs-label-alt="Dark"', $markup );
		$this->assertStringContainsString( 'data-arts-cs-name="Color theme"', $markup );

		$authored = do_shortcode( '[' . Toggle::SHORTCODE . ' label_alt="Night"]' );

		$this->assertStringContainsString( 'data-arts-cs-label-alt="Night"', $authored );
		// And it is the name, since an icon-only control has no visible text.
		$this->assertStringContainsString( 'aria-label="Night"', $authored );
	}

	/**
	 * Style controls are grouped by the part they belong to, and each part owns
	 * its own Normal / Hover / Active colours. Declared on the widget rather
	 * than in the skins: `Skin_Base::start_controls_tabs()` assembles a `_skin`
	 * condition into a local it never passes on, so a group per skin would
	 * leave empty tab wrappers in the panel.
	 */
	public function test_style_is_grouped_by_part(): void {
		$controls = $this->editor_controls();

		foreach ( array( 'section_style_general', 'section_style_icon', 'section_style_label', 'section_style_track', 'section_style_options', 'section_style_list', 'section_style_indicator' ) as $section ) {
			$this->assertArrayHasKey( $section, $controls );
		}

		// Each part's colours sit in that part's own tabs group.
		$groups = array(
			'tab_icon_normal'   => 'tabs_icon_colors',
			'tab_icon_hover'    => 'tabs_icon_colors',
			'tab_icon_active'   => 'tabs_icon_colors',
			'tab_label_normal'  => 'tabs_label_colors',
			'tab_label_active'  => 'tabs_label_colors',
			'tab_track_normal'  => 'tabs_track_colors',
			'tab_track_hover'   => 'tabs_track_colors',
			'tab_list_normal'   => 'tabs_list_colors',
			'tab_list_hover'    => 'tabs_list_colors',
			'tab_list_active'   => 'tabs_list_colors',
		);

		foreach ( $groups as $tab => $wrapper ) {
			$this->assertArrayHasKey( $tab, $controls );
			$this->assertSame( $wrapper, $this->array_value( $controls[ $tab ] )['tabs_wrapper'] ?? null );
		}

		// Active means nothing where one control IS the whole thing, and
		// nothing at all once the Buttons skin has switched its icons off.
		// Stated as `conditions`, which a section does not merge into —
		// `condition` would come back widened by the section's own skin list.
		$active = wp_json_encode( $this->array_value( $controls['tab_icon_active'] )['conditions'] ?? array() );

		$this->assertStringContainsString( Toggle::SKIN_SWITCH, (string) $active );
		$this->assertStringContainsString( 'buttons_icons', (string) $active );
		$this->assertStringNotContainsString( Toggle::SKIN_DROPDOWN, (string) $active );
	}

	/**
	 * A condition is repeated on every control, not just its section: the
	 * section's decides what an author sees, the control's decides whether any
	 * CSS is generated at all — `get_style_controls()` filters through
	 * `is_control_visible()`. A control that only carried the section's would
	 * quietly emit rules for skins it does not belong to.
	 */
	public function test_every_style_control_carries_its_own_condition(): void {
		$controls = $this->editor_controls();

		$expected = array(
			'icon_padding'             => Toggle::SKIN_SWITCH,
			'label_color_active'       => Toggle::SKIN_BUTTONS,
			'track_background'         => array( Toggle::SKIN_SWITCH, Toggle::SKIN_BUTTONS, Toggle::SKIN_DROPDOWN ),
			'track_border_color_hover' => array( Toggle::SKIN_SWITCH, Toggle::SKIN_BUTTONS, Toggle::SKIN_DROPDOWN ),
			'option_padding'           => array( Toggle::SKIN_BUTTONS, Toggle::SKIN_DROPDOWN ),
			// A scalar inside the widened Options section on purpose: it
			// REPLACES the section's list in the by-index merge, and this pin
			// is the regression test for that behaviour.
			'option_radius'            => Toggle::SKIN_BUTTONS,
			'indicator_color'          => array( Toggle::SKIN_SWITCH, Toggle::SKIN_BUTTONS, Toggle::SKIN_DROPDOWN ),
			'list_background'          => Toggle::SKIN_DROPDOWN,
			'list_color_active'        => Toggle::SKIN_DROPDOWN,
		);

		foreach ( $expected as $id => $skins ) {
			$this->assertArrayHasKey( $id, $controls );
			$this->assertSame( $skins, $this->array_value( $controls[ $id ] )['condition']['_skin'] ?? null, $id );
		}

		// Spacing is stated as a negation so a future skin is not silently
		// excluded; the Dropdown is the one skin where a gap has nothing to
		// sit between.
		$this->assertSame( Toggle::SKIN_DROPDOWN, $this->array_value( $controls['gap'] )['condition']['_skin!'] ?? null );

		// The rest are nested ORs, because what they style is optional: a
		// caption an author may not have written, and icons the Buttons skin
		// can switch off. Both are `conditions`, which a section never merges
		// into — `condition` would come back widened by the section's own list.
		foreach ( array( 'label_color', 'icon_size', 'icon_color', 'icon_color_hover', 'icon_color_active' ) as $id ) {
			$this->assertArrayHasKey( 'conditions', $this->array_value( $controls[ $id ] ), $id );
		}
	}

	/**
	 * The five nested `conditions` shapes, stated in full.
	 *
	 * Every other assertion in this file reads a `conditions` array through
	 * `wp_json_encode` and a substring, which cannot see term ORDER, a missing
	 * `operator`, or the `or`/`and` nesting — so a builder that dropped
	 * `'operator' => 'in'` would leave the suite green. Two of these five had
	 * no coverage of any kind before this test: the Track section, and the
	 * System Icon control.
	 *
	 * An absent `operator` is deliberate and load-bearing, not an omission:
	 * `Conditions::check()` reads `! empty( $term['operator'] )` and falls
	 * through to `compare()`'s `default` — a strict `===`. Writing `'='` there
	 * instead would be equivalent in PHP but is a second shape for the editor's
	 * JavaScript to agree about, so the arrays stay exactly as they are.
	 */
	public function test_every_nested_condition_states_its_full_shape(): void {
		$controls = $this->editor_controls();

		$icons_on = array(
			'name'     => 'buttons_icons',
			'operator' => '!==',
			'value'    => '',
		);

		$buttons = array(
			'name'  => '_skin',
			'value' => Toggle::SKIN_BUTTONS,
		);

		$cycles = array(
			'name'  => 'mode',
			'value' => Toggle::MODE_CYCLE,
		);

		$expected = array(
			// A caption is optional, so the section appears where the Buttons
			// and Dropdown skins print words, or where the other two were
			// given one.
			'section_style_label' => array(
				'relation' => 'or',
				'terms'    => array(
					array(
						'name'     => '_skin',
						'operator' => 'in',
						'value'    => array( Toggle::SKIN_BUTTONS, Toggle::SKIN_DROPDOWN ),
					),
					array(
						'relation' => 'and',
						'terms'    => array(
							array(
								'name'     => '_skin',
								'operator' => 'in',
								'value'    => array( Toggle::SKIN_ICON, Toggle::SKIN_SWITCH ),
							),
							array(
								'name'     => 'caption',
								'operator' => '!==',
								'value'    => '',
							),
						),
					),
				),
			),
			// Separate has no track — it is the claim the layout makes.
			'section_style_track' => array(
				'relation' => 'or',
				'terms'    => array(
					array(
						'name'     => '_skin',
						'operator' => 'in',
						'value'    => array( Toggle::SKIN_SWITCH, Toggle::SKIN_DROPDOWN ),
					),
					array(
						'relation' => 'and',
						'terms'    => array(
							$buttons,
							array(
								'name'  => 'buttons_style',
								'value' => Toggle::BUTTONS_JOINED,
							),
						),
					),
				),
			),
			// An icon that is the whole control has no "other" to be
			// distinguished from, so the Icon skin gets no Active tab.
			'tab_icon_active'     => array(
				'relation' => 'or',
				'terms'    => array(
					array(
						'name'  => '_skin',
						'value' => Toggle::SKIN_SWITCH,
					),
					array(
						'relation' => 'and',
						'terms'    => array( $buttons, $icons_on ),
					),
				),
			),
			'section_style_icon'  => array(
				'relation' => 'or',
				'terms'    => array(
					array(
						'name'     => '_skin',
						'operator' => 'in',
						'value'    => array( Toggle::SKIN_ICON, Toggle::SKIN_SWITCH ),
					),
					array(
						'relation' => 'and',
						'terms'    => array( $buttons, $icons_on ),
					),
				),
			),
			// The one control that also asks about the state count: there is
			// no System icon to choose when there is no System state.
			'icon_system'         => array(
				'relation' => 'or',
				'terms'    => array(
					array(
						'relation' => 'and',
						'terms'    => array(
							array(
								'name'  => '_skin',
								'value' => Toggle::SKIN_ICON,
							),
							$cycles,
						),
					),
					array(
						'relation' => 'and',
						'terms'    => array( $buttons, $cycles, $icons_on ),
					),
				),
			),
		);

		foreach ( $expected as $id => $conditions ) {
			$this->assertArrayHasKey( $id, $controls, $id );
			$this->assertSame( $conditions, $this->array_value( $controls[ $id ] )['conditions'] ?? null, $id );
		}
	}

	/**
	 * The List controls reach the Dropdown's open list through the
	 * customizable-select selectors, which live in their own keys: a browser
	 * without
	 * `::picker()` drops a whole selector list on the one unknown entry, so
	 * joining them into a legacy key would take its rules down too. The one
	 * shared key (`option_padding`) joins only plain element selectors. And
	 * hover never repaints what is already chosen — `:not(:checked)`.
	 */
	public function test_list_controls_state_their_modern_selectors_alone(): void {
		$controls = $this->editor_controls();

		$this->assertStringContainsString( '::picker(select)', (string) wp_json_encode( $this->array_value( $controls['list_background'] )['selectors'] ?? array() ) );
		$this->assertStringContainsString( 'option:not(:checked):hover', (string) wp_json_encode( $this->array_value( $controls['list_color_hover'] )['selectors'] ?? array() ) );

		$padding = (string) wp_json_encode( $this->array_value( $controls['option_padding'] )['selectors'] ?? array() );

		$this->assertStringContainsString( '.arts-cs-toggle__option', $padding );
		$this->assertStringContainsString( '.arts-cs-toggle__select option', $padding );
	}

	/**
	 * Switching the icons off in the Buttons skin takes everything that styles
	 * them with it — the pickers on the Content tab AND the whole Icons section
	 * under Style, which otherwise sat there offering to size and colour
	 * something that is not rendered.
	 */
	public function test_hidden_icons_take_their_controls_with_them(): void {
		$controls = $this->editor_controls();

		foreach ( array( 'icon_default', 'icon_alt', 'section_style_icon', 'icon_size', 'icon_color' ) as $id ) {
			$this->assertArrayHasKey( $id, $controls );

			$conditions = $this->array_value( $controls[ $id ] )['conditions'] ?? array();

			$this->assertStringContainsString( 'buttons_icons', (string) wp_json_encode( $conditions ), $id );
		}
	}

	/**
	 * A tab and a section carry no selectors, so under Optimized Control
	 * Loading they are filed as style controls and `get_controls()` never
	 * returns them. The editor's conditions are the ones that matter here.
	 *
	 * @return array<string, mixed>
	 */
	private function editor_controls(): array {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'arts-color-switcher-toggle' );
		$this->assertNotNull( $widget );

		$frontend = new \ReflectionProperty( \Elementor\Core\Frontend\Performance::class, 'is_frontend' );
		$frontend->setAccessible( true );
		$was = $frontend->getValue();
		$frontend->setValue( null, false );

		\Elementor\Plugin::$instance->controls_manager->delete_stack( $widget );
		$controls = $widget->get_controls();

		$frontend->setValue( null, $was );

		$this->assertIsArray( $controls );

		return $controls;
	}

	/**
	 * The words are a default an author can delete, not a floor the renderer
	 * puts back — on the Dropdown as much as anywhere. What is ANNOUNCED still
	 * falls back, since an unnamed control is unusable and no author can see
	 * that happening.
	 */
	public function test_a_cleared_label_prints_nothing_and_is_still_named(): void {
		$buttons = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="buttons" label_alt=""]' );

		$this->assertStringNotContainsString( '<span class="arts-cs-toggle__label">Dark</span>', $buttons );
		$this->assertStringContainsString( 'aria-label="Dark"', $buttons );

		$dropdown = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="dropdown" label_alt=""]' );

		$this->assertStringContainsString( '<option value="alt"></option>', $dropdown );
		$this->assertStringContainsString( '<option value="default">Light</option>', $dropdown );
	}

	/** Icons off with no words of their own would leave a button with nothing in it. */
	public function test_the_buttons_skin_never_renders_an_empty_option(): void {
		$markup = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="buttons" icons="no" label_alt="" label_default=""]' );

		$this->assertStringNotContainsString( 'arts-cs-toggle__icon', $markup );
		$this->assertStringContainsString( '>Light</span>', $markup );
		$this->assertStringContainsString( '>Dark</span>', $markup );
	}

	/** An unknown skin renders something rather than nothing. */
	public function test_an_unknown_skin_falls_back_to_the_icon(): void {
		$markup = do_shortcode( '[' . Toggle::SHORTCODE . ' skin="nope"]' );

		$this->assertStringContainsString( 'data-arts-cs-toggle="icon"', $markup );
	}
}
