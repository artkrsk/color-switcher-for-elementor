<?php

namespace Arts\ColorSwitcher\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Managers\Assets;
use Arts\ColorSwitcher\Managers\Toggle as ToggleManager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;

class Toggle extends Widget_Base {

	private const BUTTON = '{{WRAPPER}} .arts-cs-toggle';

	private const ICON = '{{WRAPPER}} .arts-cs-toggle__icon';

	private const LABEL = '{{WRAPPER}} .arts-cs-toggle__label, {{WRAPPER}} .arts-cs-toggle__select';

	private const OPTION = '{{WRAPPER}} .arts-cs-toggle__option';

	/**
	 * The Dropdown's open list, in browsers with customizable selects. Kept in
	 * their own constants, never comma-joined into BOX/LABEL/OPTION: a browser
	 * without `::picker()` drops a whole selector list on the one unknown
	 * entry, which would take the legacy rules down with it.
	 */
	private const PICKER = '{{WRAPPER}} .arts-cs-toggle__select::picker(select)';

	private const LIST_OPTION = '{{WRAPPER}} .arts-cs-toggle__select option';

	/**
	 * The box each skin draws, whichever one that is: the Switch's track, the
	 * Joined buttons' own root, the Dropdown's select. Only one of the three
	 * exists in any rendered control, so one selector list serves them all.
	 */
	private const BOX = '{{WRAPPER}} .arts-cs-toggle__track, {{WRAPPER}} .arts-cs-toggle_joined, {{WRAPPER}} .arts-cs-toggle__select';

	private const BOX_HOVER = '{{WRAPPER}} .arts-cs-toggle_switch:hover .arts-cs-toggle__track, {{WRAPPER}} .arts-cs-toggle_joined:hover, {{WRAPPER}} .arts-cs-toggle__select:hover';

	/** Skins with a box to colour. The Icon skin is bare by design. */
	private const BOX_SKINS = array( ToggleManager::SKIN_SWITCH, ToggleManager::SKIN_BUTTONS, ToggleManager::SKIN_DROPDOWN );

	/**
	 * Skins on the segmented geometry — a track whose padding the sliding
	 * mark's width is derived from. The Dropdown has no track, so widening
	 * this would hand it a dead Track Padding control.
	 */
	private const ACTIVE_SKINS = array( ToggleManager::SKIN_SWITCH, ToggleManager::SKIN_BUTTONS );

	/**
	 * Skins with a visible mark on the chosen part: the Switch's knob, the
	 * pinned button's fill, the checked row of the Dropdown's list. All read
	 * the same `--arts-cs-toggle-active`, so one Indicator control turns them.
	 */
	private const INDICATOR_SKINS = array( ToggleManager::SKIN_SWITCH, ToggleManager::SKIN_BUTTONS, ToggleManager::SKIN_DROPDOWN );

	/** Skins that can offer three states. A switch has only two positions. */
	private const STATE_SKINS = array( ToggleManager::SKIN_ICON, ToggleManager::SKIN_BUTTONS, ToggleManager::SKIN_DROPDOWN );

	/** Skins whose icons are the control, so there is nothing to switch off. */
	private const ALWAYS_ICON_SKINS = array( ToggleManager::SKIN_ICON, ToggleManager::SKIN_SWITCH );

	/** Skins that draw one control, which a caption can sit beside. */
	private const CAPTION_SKINS = array( ToggleManager::SKIN_ICON, ToggleManager::SKIN_SWITCH );

	/**
	 * Skins that print a word per state. The Icon and Switch skins do not, so
	 * they are not offered the label controls: what those would change there is
	 * the tooltip and what a screen reader announces, neither of which an
	 * author can see themselves changing. Both still get the translated
	 * defaults from the renderer.
	 */
	private const LABEL_SKINS = array( ToggleManager::SKIN_BUTTONS, ToggleManager::SKIN_DROPDOWN );

	/**
	 * Every shape is a skin, so there is no unskinned rendering to offer and
	 * no "Default" entry to explain in the dropdown.
	 *
	 * @var bool
	 */
	protected $_has_template_content = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/** @var ToggleManager|null Elementor constructs widgets itself, so the
	 * dependencies are handed to the class at registration rather than to an
	 * instance. */
	private static $renderer = null;

	/** @var \Arts\ColorSwitcher\Managers\Kit|null */
	private static $kit = null;

	public function get_name(): string {
		return 'arts-color-switcher-toggle';
	}

	public function get_title(): string {
		return esc_html__( 'Color Switcher', 'color-switcher-for-elementor' );
	}

	public function get_icon(): string {
		return 'eicon-toggle';
	}

	/** @return array<int, string> */
	public function get_categories(): array {
		return array( 'general' );
	}

	/** @return array<int, string> */
	public function get_keywords(): array {
		return array( 'dark', 'mode', 'theme', 'toggle', 'switch', 'color' );
	}

	/** @return array<int, string> */
	public function get_style_depends(): array {
		return array( Assets::HANDLE );
	}

	/** @return array<int, string> */
	public function get_script_depends(): array {
		return array( Assets::HANDLE );
	}

	public static function bootstrap( ToggleManager $renderer, \Arts\ColorSwitcher\Managers\Kit $kit ): void {
		self::$renderer = $renderer;
		self::$kit      = $kit;
	}

	protected function register_skins(): void {
		$this->add_skin( new Skins\Skin_Icon( $this ) );
		$this->add_skin( new Skins\Skin_Switch( $this ) );
		$this->add_skin( new Skins\Skin_Buttons( $this ) );
		$this->add_skin( new Skins\Skin_Dropdown( $this ) );
	}

	/**
	 * The four ways the Buttons skin says "this option is the chosen one", or
	 * their negation for hover. A correlation between the attribute on `<html>`
	 * and the option's own, which CSS can state but not abbreviate — composed
	 * here rather than written out again for every part and every state.
	 *
	 * The last rule is the unchosen visitor: with three options System stands
	 * for them, and with two there is no System option to match, so nothing is
	 * selected — which is exactly right.
	 *
	 * @param bool   $pinned True for the chosen option, false for hover on any other.
	 * @param string $part   A descendant to append, or '' for the option itself.
	 */
	private static function option_states( bool $pinned, string $part = '' ): string {
		$suffix = '' === $part ? '' : ' ' . $part;
		$rules  = array();

		foreach ( array( 'system', 'default', 'alt', 'system' ) as $index => $state ) {
			$match = sprintf( "[data-arts-cs-set='%s']", $state );

			$rules[] = sprintf(
				'%s {{WRAPPER}} .arts-cs-toggle__option%s%s%s',
				3 === $index ? 'html:not([data-arts-cs-pref])' : sprintf( "html[data-arts-cs-pref='%s']", $state ),
				$pinned ? $match : ':not(' . $match . ')',
				$pinned ? '' : ':hover',
				$suffix
			);
		}

		return implode( ', ', $rules );
	}

	/** Hovered, but never what is already chosen. The Switch is absent: its hover paint is _switch.scss's arithmetic blend. */
	private static function icon_hover(): string {
		return implode(
			', ',
			array(
				'{{WRAPPER}} .arts-cs-toggle_icon:hover .arts-cs-toggle__icon',
				self::option_states( false, '.arts-cs-toggle__icon' ),
			)
		);
	}

	/** The pinned option's icon. The Switch is absent: its active paint is _switch.scss's arithmetic blend. */
	private static function icon_active(): string {
		return self::option_states( true, '.arts-cs-toggle__icon' );
	}

	/**
	 * The Switch's icon paint is arithmetic on the scalar, never a selector on
	 * the html attribute: a scrub deliberately never flips the attribute, so
	 * paint keyed to it names the wrong icon for the whole band a scrub holds
	 * — the knob rides `--arts-cs-p`, and the colours ride with it. The three
	 * colour controls therefore emit plain VARS on the Switch root and the
	 * stylesheet states the blends (_switch.scss): a value template around
	 * `{{VALUE}}` cannot carry them, because a control linked to a Global
	 * Color bypasses the template — `add_control_rules()`, reached via
	 * `add_control_style_rules()`, rewrites everything between `:` and `;` to
	 * the bare global var (core/files/css/base.php) — while a `property: value` shape
	 * survives the rewrite with its property name intact.
	 */
	private const SWITCH_ROOT = '{{WRAPPER}} .arts-cs-toggle_switch';

	/**
	 * A caption has no chosen state of its own, so it simply follows the
	 * control it sits beside; only the Buttons skin's words are per option.
	 */
	private static function label_hover(): string {
		return implode(
			', ',
			array(
				'{{WRAPPER}} .arts-cs-toggle_icon:hover .arts-cs-toggle__label',
				'{{WRAPPER}} .arts-cs-toggle_switch:hover .arts-cs-toggle__label',
				'{{WRAPPER}} .arts-cs-toggle__select:hover',
				self::option_states( false, '.arts-cs-toggle__label' ),
			)
		);
	}

	private static function label_active(): string {
		return self::option_states( true, '.arts-cs-toggle__label' );
	}

	/**
	 * A condition is satisfied when ANY of its terms is.
	 *
	 * @param array<string, mixed> ...$terms
	 * @return array<string, mixed>
	 */
	private static function any( array ...$terms ): array {
		return array(
			'relation' => 'or',
			'terms'    => $terms,
		);
	}

	/**
	 * A condition is satisfied only when EVERY term is.
	 *
	 * @param array<string, mixed> ...$terms
	 * @return array<string, mixed>
	 */
	private static function all( array ...$terms ): array {
		return array(
			'relation' => 'and',
			'terms'    => $terms,
		);
	}

	/**
	 * One comparison. The operator key is OMITTED when none is given rather
	 * than written out as `=`: `Conditions::check()` reads
	 * `! empty( $term['operator'] )` and falls through to `compare()`'s
	 * `default`, a strict `===`, so the two are the same test in PHP — but the
	 * editor evaluates these in JavaScript as well, and one shape for both to
	 * agree about is one fewer thing that can drift.
	 *
	 * @param string|array<string> $value
	 * @return array<string, mixed>
	 */
	private static function term( string $name, $value, string $operator = '' ): array {
		$term = array( 'name' => $name );

		if ( '' !== $operator ) {
			$term['operator'] = $operator;
		}

		$term['value'] = $value;

		return $term;
	}

	/**
	 * The Buttons skin draws icons only while it is asked to.
	 *
	 * @return array<string, mixed>
	 */
	private static function buttons_with_icons(): array {
		return self::all(
			self::term( '_skin', ToggleManager::SKIN_BUTTONS ),
			self::term( 'buttons_icons', '', '!==' )
		);
	}

	/**
	 * A caption is optional, so the section that styles it appears only where
	 * there is text on screen to style.
	 *
	 * @return array<string, mixed>
	 */
	private static function label_conditions(): array {
		return self::any(
			self::term( '_skin', self::LABEL_SKINS, 'in' ),
			self::all(
				self::term( '_skin', self::CAPTION_SKINS, 'in' ),
				self::term( 'caption', '', '!==' )
			)
		);
	}

	/**
	 * The Separate buttons layout has no track — it is the claim it makes.
	 *
	 * @return array<string, mixed>
	 */
	private static function track_conditions(): array {
		return self::any(
			self::term( '_skin', array( ToggleManager::SKIN_SWITCH, ToggleManager::SKIN_DROPDOWN ), 'in' ),
			self::all(
				self::term( '_skin', ToggleManager::SKIN_BUTTONS ),
				self::term( 'buttons_style', ToggleManager::BUTTONS_JOINED )
			)
		);
	}

	/**
	 * Where one part is chosen and the rest are not, stated as `conditions`
	 * rather than `condition`.
	 *
	 * `get_section_args()` copies a section's `condition` onto every control in
	 * it, and `handle_control_position()` merges the two with
	 * `array_replace_recursive` — which merges lists BY INDEX. A two-skin list
	 * inside a three-skin section therefore keeps the section's third entry and
	 * silently widens. `conditions` is never copied into a section's args, so
	 * it arrives intact.
	 *
	 * @return array<string, mixed>
	 */
	private static function icon_active_conditions(): array {
		return self::any(
			self::term( '_skin', ToggleManager::SKIN_SWITCH ),
			self::buttons_with_icons()
		);
	}

	/**
	 * Icons are drawn by three skins, and the Buttons skin can switch them off.
	 *
	 * @return array<string, mixed>
	 */
	private static function icon_conditions(): array {
		return self::any(
			self::term( '_skin', self::ALWAYS_ICON_SKINS, 'in' ),
			self::buttons_with_icons()
		);
	}

	/**
	 * The System icon exists only where there is a System state to show it
	 * for, which is a question about the state count as well as the skin.
	 *
	 * @return array<string, mixed>
	 */
	private static function system_icon_conditions(): array {
		$cycles = self::term( 'mode', ToggleManager::MODE_CYCLE );

		return self::any(
			self::all( self::term( '_skin', ToggleManager::SKIN_ICON ), $cycles ),
			self::all(
				self::term( '_skin', ToggleManager::SKIN_BUTTONS ),
				$cycles,
				self::term( 'buttons_icons', '', '!==' )
			)
		);
	}

	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_general_style();
		$this->register_icon_style();
		$this->register_label_style();
		$this->register_track_style();
		$this->register_option_style();
		$this->register_list_style();
		$this->register_indicator_style();
	}

	/**
	 * `_skin` is injected into the first section a widget opens, so the first
	 * control registered here is the one that sits directly under it.
	 */
	private function register_content_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => esc_html__( 'Toggle', 'color-switcher-for-elementor' ) )
		);

		$this->add_control(
			'buttons_style',
			array(
				'type'      => Controls_Manager::SELECT,
				'label'     => esc_html__( 'Layout', 'color-switcher-for-elementor' ),
				'default'   => ToggleManager::BUTTONS_JOINED,
				'options'   => array(
					ToggleManager::BUTTONS_JOINED   => esc_html__( 'Joined', 'color-switcher-for-elementor' ),
					ToggleManager::BUTTONS_SEPARATE => esc_html__( 'Separate', 'color-switcher-for-elementor' ),
				),
				'condition' => array( '_skin' => ToggleManager::SKIN_BUTTONS ),
			)
		);

		$this->add_control(
			'mode',
			array(
				'type'      => Controls_Manager::SELECT,
				'label'     => esc_html__( 'States', 'color-switcher-for-elementor' ),
				'default'   => ToggleManager::MODE_BINARY,
				'options'   => array(
					ToggleManager::MODE_BINARY => esc_html__( 'Default / Alt colors', 'color-switcher-for-elementor' ),
					ToggleManager::MODE_CYCLE  => esc_html__( 'System / Default / Alt colors', 'color-switcher-for-elementor' ),
				),
				'condition' => array( '_skin' => self::STATE_SKINS ),
			)
		);

		// Visitor-facing words. Real defaults rather than placeholders, because
		// a placeholder promises text the markup does not contain — and
		// clearing one of these then means what it says: no word is printed,
		// on any skin. Only what is ANNOUNCED keeps a built-in fallback, so an
		// icon with no words is still named.
		$this->add_control(
			'label_system',
			array(
				'type'      => Controls_Manager::TEXT,
				'label'     => esc_html__( 'System Label', 'color-switcher-for-elementor' ),
				'default'   => esc_html__( 'System', 'color-switcher-for-elementor' ),
				'condition' => array(
					'_skin' => self::LABEL_SKINS,
					'mode'  => ToggleManager::MODE_CYCLE,
				),
			)
		);

		$this->add_control(
			'label_default',
			array(
				'type'      => Controls_Manager::TEXT,
				'label'     => esc_html__( 'Default Colors Label', 'color-switcher-for-elementor' ),
				'default'   => esc_html__( 'Light', 'color-switcher-for-elementor' ),
				'condition' => array( '_skin' => self::LABEL_SKINS ),
			)
		);

		$this->add_control(
			'label_alt',
			array(
				'type'      => Controls_Manager::TEXT,
				'label'     => esc_html__( 'Alt Colors Label', 'color-switcher-for-elementor' ),
				'default'   => esc_html__( 'Dark', 'color-switcher-for-elementor' ),
				'condition' => array( '_skin' => self::LABEL_SKINS ),
			)
		);

		// Above the controls it gates, and gating them: dropping the text is
		// done by clearing a label, so this is all that is left to decide.
		$this->add_control(
			'buttons_icons',
			array(
				'type'         => Controls_Manager::SWITCHER,
				'label'        => esc_html__( 'Show Icons', 'color-switcher-for-elementor' ),
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => array( '_skin' => ToggleManager::SKIN_BUTTONS ),
			)
		);

		$this->add_control(
			'icon_system',
			array(
				'type'       => Controls_Manager::ICONS,
				'label'      => esc_html__( 'System Icon', 'color-switcher-for-elementor' ),
				'default'    => array(),
				'conditions' => self::system_icon_conditions(),
			)
		);

		$this->add_control(
			'icon_default',
			array(
				'type'       => Controls_Manager::ICONS,
				'label'      => esc_html__( 'Default Colors Icon', 'color-switcher-for-elementor' ),
				'default'    => array(),
				'conditions' => self::icon_conditions(),
			)
		);

		$this->add_control(
			'icon_alt',
			array(
				'type'       => Controls_Manager::ICONS,
				'label'      => esc_html__( 'Alt Colors Icon', 'color-switcher-for-elementor' ),
				'default'    => array(),
				'conditions' => self::icon_conditions(),
			)
		);

		$this->add_control(
			'caption',
			array(
				'type'      => Controls_Manager::TEXT,
				'label'     => esc_html__( 'Caption', 'color-switcher-for-elementor' ),
				'default'   => '',
				'condition' => array( '_skin' => self::CAPTION_SKINS ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Sections carry the same conditions as the controls inside them, for two
	 * different reasons: on the section it decides what an author sees, on the
	 * control it decides whether any CSS is generated at all —
	 * `get_style_controls()` filters through `is_control_visible()`.
	 */
	private function register_general_style(): void {
		// Not offered to the Dropdown: its root's only child is the select,
		// so a gap has nothing to sit between. The section goes with the
		// control — Spacing is its only member.
		$this->start_controls_section(
			'section_style_general',
			array(
				'label'     => esc_html__( 'General', 'color-switcher-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( '_skin!' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Spacing', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'vh', 'custom' ),
				// Also the segmented geometry's gap: the sliding mark travels
				// one column PLUS one gap, so a spacing the mark did not know
				// about would leave it landing short.
				'selectors'  => array( self::BUTTON => 'gap: {{SIZE}}{{UNIT}}; --arts-cs-seg-gap: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( '_skin!' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->end_controls_section();
	}

	private function register_icon_style(): void {
		$this->start_controls_section(
			'section_style_icon',
			array(
				'label'      => esc_html__( 'Icons', 'color-switcher-for-elementor' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'conditions' => self::icon_conditions(),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Size', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'vh', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 96,
					),
				),
				'selectors'  => array(
					self::BUTTON . ' svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					self::BUTTON . ' i'   => 'font-size: {{SIZE}}{{UNIT}};',
				),
				'conditions' => self::icon_conditions(),
			)
		);

		// The air around each icon, which is also what widens the column the
		// knob covers. One value, because the icons are square and an
		// asymmetric slot would put the knob off centre in its own track.
		$this->add_responsive_control(
			'icon_padding',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Padding', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ),
				'selectors'  => array( self::ICON => 'padding: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( '_skin' => ToggleManager::SKIN_SWITCH ),
			)
		);

		$this->start_controls_tabs( 'tabs_icon_colors' );

		$this->start_controls_tab(
			'tab_icon_normal',
			array( 'label' => esc_html__( 'Normal', 'color-switcher-for-elementor' ) )
		);

		// One colour, never a pair. Link it to a Global Color and its Alt
		// swatch turns it for free — a second "colour for the other theme"
		// control is the per-element two-value configuration this plugin
		// exists to remove.
		$this->add_control(
			'icon_color',
			array(
				'type'       => Controls_Manager::COLOR,
				'label'      => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors'  => array(
					// The Switch is excluded: its icons are painted by the
					// stylesheet's scalar blends, which a direct colour here
					// would outrank from {{WRAPPER}} specificity.
					'{{WRAPPER}} .arts-cs-toggle:not(.arts-cs-toggle_switch) .arts-cs-toggle__icon' => 'color: {{VALUE}};',
					self::SWITCH_ROOT => '--arts-cs-sw-icon: {{VALUE}};',
				),
				'conditions' => self::icon_conditions(),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_icon_hover',
			array( 'label' => esc_html__( 'Hover', 'color-switcher-for-elementor' ) )
		);

		$this->add_control(
			'icon_color_hover',
			array(
				'type'       => Controls_Manager::COLOR,
				'label'      => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors'  => array(
					self::icon_hover() => 'color: {{VALUE}};',
					self::SWITCH_ROOT  => '--arts-cs-sw-icon-hover: {{VALUE}};',
				),
				'conditions' => self::icon_conditions(),
			)
		);

		$this->end_controls_tab();

		// Only where one part is chosen and the rest are not. An icon that is
		// the whole control has no "other" to be distinguished from.
		$this->start_controls_tab(
			'tab_icon_active',
			array(
				'label'      => esc_html__( 'Active', 'color-switcher-for-elementor' ),
				'conditions' => self::icon_active_conditions(),
			)
		);

		$this->add_control(
			'icon_color_active',
			array(
				'type'       => Controls_Manager::COLOR,
				'label'      => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors'  => array(
					self::icon_active() => 'color: {{VALUE}};',
					self::SWITCH_ROOT   => '--arts-cs-sw-icon-active: {{VALUE}};',
				),
				'conditions' => self::icon_active_conditions(),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function register_label_style(): void {
		$this->start_controls_section(
			'section_style_label',
			array(
				'label'      => esc_html__( 'Label', 'color-switcher-for-elementor' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'conditions' => self::label_conditions(),
			)
		);

		// The label inherits the page font by default (`font: inherit` on the
		// button, so a theme's button styling cannot leak in) — this is the
		// author's way to depart from that. The select is styled with it
		// because it is the same words in the same control.
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'       => 'label_typography',
				'label'      => esc_html__( 'Typography', 'color-switcher-for-elementor' ),
				'selector'   => self::LABEL,
				'conditions' => self::label_conditions(),
			)
		);

		$this->start_controls_tabs( 'tabs_label_colors' );

		$this->start_controls_tab(
			'tab_label_normal',
			array( 'label' => esc_html__( 'Normal', 'color-switcher-for-elementor' ) )
		);

		// Its own colour, not the icons'. Sharing one made a Switch styled for
		// white icons print a white caption on a white page.
		$this->add_control(
			'label_color',
			array(
				'type'       => Controls_Manager::COLOR,
				'label'      => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors'  => array( self::LABEL => 'color: {{VALUE}};' ),
				'conditions' => self::label_conditions(),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_label_hover',
			array( 'label' => esc_html__( 'Hover', 'color-switcher-for-elementor' ) )
		);

		$this->add_control(
			'label_color_hover',
			array(
				'type'       => Controls_Manager::COLOR,
				'label'      => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors'  => array( self::label_hover() => 'color: {{VALUE}};' ),
				'conditions' => self::label_conditions(),
			)
		);

		$this->end_controls_tab();

		// A caption follows the control it sits beside, so only words that
		// belong to one option of several have a chosen state.
		$this->start_controls_tab(
			'tab_label_active',
			array(
				'label'     => esc_html__( 'Active', 'color-switcher-for-elementor' ),
				'condition' => array( '_skin' => ToggleManager::SKIN_BUTTONS ),
			)
		);

		$this->add_control(
			'label_color_active',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors' => array( self::label_active() => 'color: {{VALUE}};' ),
				'condition' => array( '_skin' => ToggleManager::SKIN_BUTTONS ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function register_track_style(): void {
		$this->start_controls_section(
			'section_style_track',
			array(
				'label'      => esc_html__( 'Track', 'color-switcher-for-elementor' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'conditions' => self::track_conditions(),
			)
		);

		// One value, and the sliding mark's inset is derived from it — an
		// asymmetric track could only put the mark off centre.
		$this->add_responsive_control(
			'track_padding',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Padding', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .arts-cs-toggle__track, {{WRAPPER}} .arts-cs-toggle_joined' => '--arts-cs-seg-pad: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( '_skin' => self::ACTIVE_SKINS ),
			)
		);

		// A select has no mark to keep centred, so its padding is the ordinary
		// four-sided one.
		$this->add_responsive_control(
			'dropdown_padding',
			array(
				'type'       => Controls_Manager::DIMENSIONS,
				'label'      => esc_html__( 'Padding', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .arts-cs-toggle__select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->add_responsive_control(
			'track_radius',
			array(
				'type'       => Controls_Manager::DIMENSIONS,
				'label'      => esc_html__( 'Border Radius', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					self::BOX => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( '_skin' => self::BOX_SKINS ),
			)
		);

		// The whole border, not just its colour: a hairline the author can only
		// make transparent is a hairline they cannot remove.
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'track_border',
				'selector'  => self::BOX,
				'condition' => array( '_skin' => self::BOX_SKINS ),
			)
		);

		$this->start_controls_tabs( 'tabs_track_colors' );

		$this->start_controls_tab(
			'tab_track_normal',
			array( 'label' => esc_html__( 'Normal', 'color-switcher-for-elementor' ) )
		);

		$this->add_control(
			'track_background',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Background', 'color-switcher-for-elementor' ),
				'selectors' => array( self::BOX => 'background-color: {{VALUE}};' ),
				'condition' => array( '_skin' => self::BOX_SKINS ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_track_hover',
			array( 'label' => esc_html__( 'Hover', 'color-switcher-for-elementor' ) )
		);

		$this->add_control(
			'track_background_hover',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Background', 'color-switcher-for-elementor' ),
				'selectors' => array( self::BOX_HOVER => 'background-color: {{VALUE}};' ),
				'condition' => array( '_skin' => self::BOX_SKINS ),
			)
		);

		// A group control carries no states, so the hovered border colour is
		// the one part of it that has to be stated separately.
		$this->add_control(
			'track_border_color_hover',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Border Color', 'color-switcher-for-elementor' ),
				'selectors' => array( self::BOX_HOVER => 'border-color: {{VALUE}};' ),
				'condition' => array( '_skin' => self::BOX_SKINS ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function register_option_style(): void {
		// The Dropdown qualifies too: in browsers with customizable selects
		// its real <option> rows take padding, and both selectors joined here
		// are plain elements, so the pair parses everywhere. The narrower
		// controls below keep their SCALAR `_skin` — a scalar replaces this
		// section's list cleanly in the by-index merge, where a shorter list
		// would silently widen.
		$this->start_controls_section(
			'section_style_options',
			array(
				'label'     => esc_html__( 'Options', 'color-switcher-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( '_skin' => array( ToggleManager::SKIN_BUTTONS, ToggleManager::SKIN_DROPDOWN ) ),
			)
		);

		$this->add_responsive_control(
			'option_padding',
			array(
				'type'       => Controls_Manager::DIMENSIONS,
				'label'      => esc_html__( 'Padding', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ),
				'selectors'  => array(
					self::OPTION . ', ' . self::LIST_OPTION => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( '_skin' => array( ToggleManager::SKIN_BUTTONS, ToggleManager::SKIN_DROPDOWN ) ),
			)
		);

		// The Separate layout has no track to put a border on, and its buttons
		// are independent things — so there the outline belongs to each of
		// them. When they sit flush (Spacing 0) those borders are also the
		// seams between neighbours, so each `+` sibling's left width collapses
		// to zero and the previous button's right border draws the single line
		// — 1px of border is 1px between items, never 2. The width field emits
		// that rule itself: `min({{LEFT}}, --arts-cs-seg-flush)` is 0 flush and
		// the full width at any spacing, and a plugin-stylesheet longhand could
		// never outrank the `{{WRAPPER}}`-scoped shorthand it must beat.
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'option_border',
				'selector'       => self::OPTION,
				'condition'      => array(
					'_skin'         => ToggleManager::SKIN_BUTTONS,
					'buttons_style' => ToggleManager::BUTTONS_SEPARATE,
				),
				'fields_options' => array(
					'width' => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
							'{{WRAPPER}} .arts-cs-toggle__option + .arts-cs-toggle__option' => 'border-left-width: min({{LEFT}}{{UNIT}}, var(--arts-cs-seg-flush));',
						),
					),
				),
			)
		);

		// Whichever box is the visible one: in Joined the mark, in Separate the
		// button itself — where an inner corner rounds only while there is
		// spacing to round into. Flush, the value squares off through the same
		// `min(value, --arts-cs-seg-flush)` the seams use, so the lines between
		// neighbours stay straight; only the group's outer corners, restated
		// plainly on `:first-child`/`:last-child`, hold the radius at any gap.
		// Dimensions run TOP RIGHT BOTTOM LEFT = the radius shorthand's
		// TL TR BR BL, which is how each corner reads below.
		$this->add_responsive_control(
			'option_radius',
			array(
				'type'       => Controls_Manager::DIMENSIONS,
				'label'      => esc_html__( 'Border Radius', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .arts-cs-toggle_separate .arts-cs-toggle__option' => 'border-top-left-radius: min({{TOP}}{{UNIT}}, var(--arts-cs-seg-flush)); border-top-right-radius: min({{RIGHT}}{{UNIT}}, var(--arts-cs-seg-flush)); border-bottom-right-radius: min({{BOTTOM}}{{UNIT}}, var(--arts-cs-seg-flush)); border-bottom-left-radius: min({{LEFT}}{{UNIT}}, var(--arts-cs-seg-flush));',
					'{{WRAPPER}} .arts-cs-toggle_separate .arts-cs-toggle__option:first-child' => 'border-top-left-radius: {{TOP}}{{UNIT}}; border-bottom-left-radius: {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .arts-cs-toggle_separate .arts-cs-toggle__option:last-child' => 'border-top-right-radius: {{RIGHT}}{{UNIT}}; border-bottom-right-radius: {{BOTTOM}}{{UNIT}};',
					'{{WRAPPER}} .arts-cs-toggle__knob' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( '_skin' => ToggleManager::SKIN_BUTTONS ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * The Dropdown's open list, where the browser supports customizable
	 * selects. Every colour is a single value an author can link to a Global
	 * Color — the Alt swatch turns it like everything else — and where the
	 * `::picker()` selectors do not parse, the rules simply never apply while
	 * the platform's own picker keeps working.
	 */
	private function register_list_style(): void {
		$this->start_controls_section(
			'section_style_list',
			array(
				'label'     => esc_html__( 'List', 'color-switcher-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		// Honest about the enhancement: these controls paint nothing where the
		// browser draws its own picker.
		$this->add_control(
			'list_support_hint',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Styled lists need a browser with customizable selects (Chrome, Edge). Other browsers show the platform\'s own picker.', 'color-switcher-for-elementor' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->add_responsive_control(
			'list_radius',
			array(
				'type'       => Controls_Manager::DIMENSIONS,
				'label'      => esc_html__( 'Border Radius', 'color-switcher-for-elementor' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					self::PICKER => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->add_control(
			'list_background',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Background', 'color-switcher-for-elementor' ),
				'selectors' => array( self::PICKER => 'background-color: {{VALUE}};' ),
				'condition' => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->start_controls_tabs( 'tabs_list_colors' );

		$this->start_controls_tab(
			'tab_list_normal',
			array( 'label' => esc_html__( 'Normal', 'color-switcher-for-elementor' ) )
		);

		// The rows do not inherit the Label colour: the SCSS default states
		// CanvasText on them (the page's currentcolor may be illegible on the
		// card), so their normal colour needs its own control.
		$this->add_control(
			'list_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors' => array( self::LIST_OPTION => 'color: {{VALUE}};' ),
				'condition' => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_list_hover',
			array( 'label' => esc_html__( 'Hover', 'color-switcher-for-elementor' ) )
		);

		// Hover never repaints what is already chosen, hence :not(:checked).
		$this->add_control(
			'list_color_hover',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors' => array( self::LIST_OPTION . ':not(:checked):hover' => 'color: {{VALUE}};' ),
				'condition' => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->add_control(
			'list_background_hover',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Background', 'color-switcher-for-elementor' ),
				'selectors' => array( self::LIST_OPTION . ':not(:checked):hover' => 'background-color: {{VALUE}};' ),
				'condition' => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_list_active',
			array( 'label' => esc_html__( 'Active', 'color-switcher-for-elementor' ) )
		);

		// Text only: the checked row's FILL is the Indicator, through the same
		// `--arts-cs-toggle-active` the knob and the pinned button read.
		$this->add_control(
			'list_color_active',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors' => array( self::LIST_OPTION . ':checked' => 'color: {{VALUE}};' ),
				'condition' => array( '_skin' => ToggleManager::SKIN_DROPDOWN ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function register_indicator_style(): void {
		$this->start_controls_section(
			'section_style_indicator',
			array(
				'label'     => esc_html__( 'Indicator', 'color-switcher-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( '_skin' => self::INDICATOR_SKINS ),
			)
		);

		// One declaration, wherever the layout puts the mark: the knob sliding
		// along the Switch, the mark crossing the Joined track, the pinned
		// button's own fill in Separate, or the checked row of the Dropdown's
		// list — the custom property inherits from the wrapper into them all.
		$this->add_control(
			'indicator_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_html__( 'Color', 'color-switcher-for-elementor' ),
				'selectors' => array( '{{WRAPPER}}' => '--arts-cs-toggle-active: {{VALUE}};' ),
				'condition' => array( '_skin' => self::INDICATOR_SKINS ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Every skin renders through here, so the shape a skin draws stays in the
	 * manager the shortcode also calls and the two cannot drift.
	 */
	public function render_skin( string $skin ): void {
		$settings = $this->get_settings_for_display();
		$settings = is_array( $settings ) ? $settings : array();

		$html = self::$renderer instanceof ToggleManager
			? self::$renderer->render(
				array(
					'skin'          => $skin,
					'mode'          => $settings['mode'] ?? ToggleManager::MODE_BINARY,
					// Only the Buttons skin can hide its icons; the rest have
					// nothing else to show.
					'show_icons'    => ToggleManager::SKIN_BUTTONS !== $skin || '' !== ( $settings['buttons_icons'] ?? 'yes' ),
					'caption'       => $settings['caption'] ?? '',
					'label_system'  => $settings['label_system'] ?? '',
					'label_default' => $settings['label_default'] ?? '',
					'label_alt'     => $settings['label_alt'] ?? '',
					'style'         => $settings[ $skin . '_style' ] ?? '',
					'icon_system'   => $this->icon_html( 'icon_system' ),
					'icon_default'  => $this->icon_html( 'icon_default' ),
					'icon_alt'      => $this->icon_html( 'icon_alt' ),
				)
			)
			: '';

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped field-by-field in ToggleManager::render().

		$this->render_unconfigured_hint();
	}

	private function icon_html( string $control ): string {
		$settings = $this->get_settings_for_display();
		$icon     = is_array( $settings ) ? ( $settings[ $control ] ?? array() ) : array();

		if ( ! is_array( $icon ) || empty( $icon['value'] ) ) {
			return '';
		}

		ob_start();
		Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );

		return (string) ob_get_clean();
	}

	/**
	 * A toggle on a site with no Alt colours is a silent no-op, which reads as
	 * broken rather than as minimal. Said in the canvas where the mistake is
	 * being made, rather than as a site-wide admin notice.
	 */
	private function render_unconfigured_hint(): void {
		if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		if ( ! self::$kit || self::$kit->has_alt_colors() ) {
			return;
		}

		printf(
			'<p class="arts-cs-toggle__hint elementor-alert elementor-alert-info">%s</p>',
			esc_html__( 'No Alt colors are set yet, so this toggle will not change anything. Add one next to any color in Site Settings → Global Colors.', 'color-switcher-for-elementor' )
		);
	}
}
