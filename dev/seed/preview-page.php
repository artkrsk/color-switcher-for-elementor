<?php
/**
 * The wp.org Live Preview page — the shop window, not the harness.
 *
 * `demo-page.php` is the mechanism fixture the e2e suite pins (element ids,
 * zone counts, a 50% scrub handle). This one has one job instead: convince a
 * stranger who pressed "Live Preview" that the plugin is worth installing. The
 * two are deliberately separate files so neither constrains the other.
 *
 * Built from Elementor containers and free widgets only — no Custom CSS (Pro)
 * and no HTML widget. Every colour is a Global Color, which is not a
 * restriction here but the whole argument: link a colour, and it turns.
 *
 * Idempotent: re-running replaces the page, re-applies the palette, and reuses
 * the SVG attachments rather than accumulating them.
 *
 * Runs in two contexts:
 *   wp eval-file dev/seed/preview-page.php --user=1     (dev site)
 *   the blueprint's runPHP step                          (Playground, wp.org)
 */

// No WP_CLI guard: Playground executes this through runPHP, where WP_CLI is
// undefined — the guard other seeds carry is exactly what made the shipped
// blueprint land on a 404.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use Elementor\Plugin as Elementor;

// Single source of truth for the page id: the blueprint's landingPage is
// generated from this define, so the seeded page and the URL cannot drift.
define( 'ACS_PREVIEW_DEMO_PAGE_ID', 9931 );

// runPHP has no session, and Document::save() refuses without one — the kit
// silently keeps its stock palette and the whole demo renders inert. Under
// `wp eval-file --user=1` a user is already set and this is a no-op.
if ( ! get_current_user_id() ) {
	wp_set_current_user( 1 );
}

// Elementor otherwise hijacks the first request with its onboarding wizard,
// which in Playground is the visitor's first impression of the plugin.
update_option( 'elementor_onboarded', true );
delete_transient( 'elementor_activation_redirect' );

/**
 * The palette: six roles, each with the Alt value this plugin exists to add.
 * Lifted from the plugin's own wp.org icon so the listing and the preview read
 * as one product.
 */
$acspv_colors = array(
	array(
		'_id'       => 'acssurf',
		'title'     => 'Surface',
		'color'     => '#EDEDF2',
		'color_alt' => '#17171C',
	),
	array(
		'_id'       => 'acspnl',
		'title'     => 'Panel',
		'color'     => '#FFFFFF',
		'color_alt' => '#20202A',
	),
	array(
		'_id'       => 'acsink',
		'title'     => 'Ink',
		'color'     => '#23232B',
		'color_alt' => '#F4F4F7',
	),
	array(
		'_id'       => 'acsmut',
		'title'     => 'Muted',
		'color'     => '#6E6E7C',
		'color_alt' => '#9B9BAC',
	),
	array(
		'_id'       => 'acsacc',
		'title'     => 'Accent',
		'color'     => '#4F46E5',
		'color_alt' => '#14B8A6',
	),
	array(
		'_id'       => 'acsline',
		'title'     => 'Hairline',
		'color'     => '#D9D9E1',
		'color_alt' => '#2F2F3B',
	),
);

/**
 * Global Fonts. The four system rows are overridden rather than custom rows
 * added, so anything on the page that never binds a font still inherits these.
 * Both families are in Elementor's Google list (includes/fonts.php), and it is
 * the KIT's stylesheet that carries the literal family and therefore enqueues
 * them — a widget bound through __globals__ only ever emits a var().
 */
$acspv_fonts = array(
	array(
		'_id'                    => 'primary',
		'title'                  => 'Primary',
		'typography_typography'  => 'custom',
		'typography_font_family' => 'Bricolage Grotesque',
		'typography_font_weight' => '600',
	),
	array(
		'_id'                    => 'secondary',
		'title'                  => 'Secondary',
		'typography_typography'  => 'custom',
		'typography_font_family' => 'Bricolage Grotesque',
		'typography_font_weight' => '600',
	),
	array(
		'_id'                    => 'text',
		'title'                  => 'Text',
		'typography_typography'  => 'custom',
		'typography_font_family' => 'Instrument Sans',
		'typography_font_weight' => '400',
	),
	array(
		'_id'                    => 'accent',
		'title'                  => 'Accent',
		'typography_typography'  => 'custom',
		'typography_font_family' => 'Instrument Sans',
		'typography_font_weight' => '500',
	),
);

$acspv_kit    = Elementor::$instance->kits_manager->get_active_kit();
$acspv_kit_id = (int) $acspv_kit->get_main_id();

if ( ! $acspv_kit_id ) {
	Elementor::$instance->kits_manager->create_new_kit( 'Color Switcher Preview' );
	$acspv_kit    = Elementor::$instance->kits_manager->get_active_kit();
	$acspv_kit_id = (int) $acspv_kit->get_main_id();
}

// Upsert rather than replace: Elementor's own four stock colours are referenced
// by the theme and by widget defaults, and dropping them breaks unrelated CSS.
$acspv_kit_settings = get_post_meta( $acspv_kit_id, '_elementor_page_settings', true );
$acspv_kit_settings = is_array( $acspv_kit_settings ) ? $acspv_kit_settings : array();
$acspv_existing     = isset( $acspv_kit_settings['custom_colors'] ) && is_array( $acspv_kit_settings['custom_colors'] )
	? $acspv_kit_settings['custom_colors']
	: array();

foreach ( $acspv_colors as $acspv_row ) {
	$acspv_found = false;

	foreach ( $acspv_existing as $acspv_i => $acspv_old ) {
		if ( is_array( $acspv_old ) && isset( $acspv_old['_id'] ) && $acspv_old['_id'] === $acspv_row['_id'] ) {
			$acspv_existing[ $acspv_i ] = $acspv_row;
			$acspv_found                = true;
			break;
		}
	}

	if ( ! $acspv_found ) {
		$acspv_existing[] = $acspv_row;
	}
}

$acspv_kit_settings['custom_colors']         = $acspv_existing;
$acspv_kit_settings['system_typography']     = $acspv_fonts;
$acspv_kit_settings['default_generic_fonts'] = 'sans-serif';

update_post_meta( $acspv_kit_id, '_elementor_page_settings', wp_slash( $acspv_kit_settings ) );

if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
	\Elementor\Core\Files\CSS\Post::create( $acspv_kit_id )->delete();
}

/**
 * Writes an SVG into uploads and hands back its URL.
 *
 * Deliberately URL-only, with no attachment: the Alt control falls back to the
 * stored URL when there is no id (the same shape `alt-images-page.php` uses and
 * the e2e suite proves), and Elementor's image widget prints a plain <img> for
 * an id-less value. Registering attachments would drag in SVG mime handling and
 * hand-written metadata for no visible gain on a page whose job is to look
 * right, not to exercise a code path.
 *
 * @return string Public URL of the written file.
 */
$acspv_asset = static function ( string $filename, string $markup ): string {
	$acspv_dir = wp_upload_dir();

	if ( ! empty( $acspv_dir['error'] ) ) {
		return '';
	}

	$acspv_path = trailingslashit( $acspv_dir['path'] ) . $filename;

	if ( false === file_put_contents( $acspv_path, $markup ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return '';
	}

	return trailingslashit( $acspv_dir['url'] ) . $filename;
};

// Artwork is drawn twice on purpose: an image cannot interpolate, so the pair
// swaps at the settled ends rather than morphing. That is the plugin's own
// documented behaviour, and it is what an author has to design for.
$acspv_still = static function ( string $bg, string $acc, string $ink, string $body ): string {
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 150" width="200" height="150">'
		. '<rect width="200" height="150" fill="%1$s"/>%2$s</svg>',
		$bg,
		str_replace( array( '{ACC}', '{INK}' ), array( $acc, $ink ), $body )
	);
};

$acspv_body_a = '<circle cx="70" cy="75" r="40" fill="{ACC}"/><rect x="106" y="35" width="62" height="80" rx="10" fill="{INK}"/>';
$acspv_body_b = '<path d="M22 128 100 22l78 106Z" fill="{INK}"/><circle cx="100" cy="94" r="24" fill="{ACC}"/>';
$acspv_body_c = '<path d="M30 118V52a34 34 0 0 1 34-34v100Z" fill="{ACC}"/><rect x="104" y="18" width="30" height="100" rx="15" fill="{INK}"/><rect x="150" y="48" width="30" height="70" rx="15" fill="{ACC}"/>';

/**
 * The plugin's mark, frozen into its two states.
 *
 * `.wordpress-org/icon.svg` animates: it cycles between a light and a dark
 * interior on a 5.6s loop, which is right for a listing tile and wrong for a
 * site header, where it reads as a logo switching itself for no reason. The
 * geometry here is that file's, with the keyframes' two endpoints pinned as
 * static fills — so the pair the animation moves between becomes the pair the
 * Alt-image swap moves between, and the mark now follows the page instead of
 * ignoring it.
 */
$acspv_mark = static function ( string $page, string $ink, string $acc, string $trk, string $knob, int $shift ): string {
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256" role="img" aria-label="Arts Color Switcher for Elementor">'
		. '<defs><linearGradient id="p" x1="0" y1="256" x2="256" y2="0" gradientUnits="userSpaceOnUse">'
		. '<stop offset="0" stop-color="#333333"/><stop offset="1" stop-color="#111111"/></linearGradient>'
		. '<clipPath id="w"><rect x="26" y="42" width="204" height="172" rx="26"/></clipPath></defs>'
		. '<rect width="256" height="256" rx="54" fill="url(#p)"/>'
		. '<rect x="0.75" y="0.75" width="254.5" height="254.5" rx="53.25" fill="none" stroke="#FFFFFF" stroke-opacity="0.06"/>'
		. '<g clip-path="url(#w)">'
		. '<rect x="26" y="42" width="204" height="172" fill="%1$s"/>'
		. '<rect x="46" y="66" width="88" height="14" rx="7" fill="%2$s"/>'
		. '<rect x="158" y="62" width="44" height="22" rx="11" fill="%4$s"/>'
		. '<circle cx="%6$d" cy="73" r="8" fill="%5$s"/>'
		. '<rect x="46" y="100" width="164" height="56" rx="12" fill="%3$s"/>'
		. '<rect x="46" y="172" width="112" height="9" rx="4" fill="%2$s"/>'
		. '<rect x="46" y="190" width="86" height="9" rx="4" fill="%2$s"/>'
		. '</g>'
		. '<rect x="26" y="42" width="204" height="172" rx="26" fill="none" stroke="#FFFFFF" stroke-opacity="0.12"/></svg>',
		$page,
		$ink,
		$acc,
		$trk,
		$knob,
		169 + ( 22 * $shift )
	);
};

$acspv_url = array(
	'logo'     => $acspv_asset( 'acs-preview-logo.svg', $acspv_mark( '#EDEDF2', '#23232B', '#4F46E5', '#23232B', '#EDEDF2', 0 ) ),
	'logo_alt' => $acspv_asset( 'acs-preview-logo-alt.svg', $acspv_mark( '#17171C', '#FFFFFF', '#0D9488', '#4F46E5', '#FFFFFF', 1 ) ),
	'a'        => $acspv_asset( 'acs-preview-a.svg', $acspv_still( '#E5E5EE', '#4F46E5', '#23232B', $acspv_body_a ) ),
	'a_alt'    => $acspv_asset( 'acs-preview-a-alt.svg', $acspv_still( '#1C1C24', '#14B8A6', '#F4F4F7', $acspv_body_a ) ),
	'b'        => $acspv_asset( 'acs-preview-b.svg', $acspv_still( '#E5E5EE', '#4F46E5', '#23232B', $acspv_body_b ) ),
	'b_alt'    => $acspv_asset( 'acs-preview-b-alt.svg', $acspv_still( '#1C1C24', '#14B8A6', '#F4F4F7', $acspv_body_b ) ),
	'c'        => $acspv_asset( 'acs-preview-c.svg', $acspv_still( '#E5E5EE', '#4F46E5', '#23232B', $acspv_body_c ) ),
	'c_alt'    => $acspv_asset( 'acs-preview-c-alt.svg', $acspv_still( '#1C1C24', '#14B8A6', '#F4F4F7', $acspv_body_c ) ),
);

/* ------------------------------------------------------------------------- *
 * Value-shape helpers. Elementor's control families each take a different
 * array shape, and mixing them up fails silently — the rule is simply not
 * emitted, with no error anywhere.
 * ------------------------------------------------------------------------- */

/** Box shape: padding, margin, border-width, border-radius. */
function acspv_box( string $top, string $right, string $bottom, string $left, string $unit = 'px' ): array {
	return array(
		'unit'     => $unit,
		'top'      => $top,
		'right'    => $right,
		'bottom'   => $bottom,
		'left'     => $left,
		'isLinked' => false,
	);
}

/** Gap shape — column/row, not a box and not a slider. */
function acspv_gap( int $px ): array {
	return array(
		'unit'     => 'px',
		'column'   => (string) $px,
		'row'      => (string) $px,
		'isLinked' => true,
	);
}

/** Slider shape. The vestigial `sizes` key is what Elementor itself writes. */
function acspv_size( float $size, string $unit = 'px' ): array {
	return array(
		'unit'  => $unit,
		'size'  => $size,
		'sizes' => array(),
	);
}

/**
 * The custom unit passes an arbitrary CSS value straight through, which is how
 * the design's clamp() type scale and ch-based measures ship with no Custom CSS.
 */
function acspv_css( string $value ): array {
	return array(
		'unit' => 'custom',
		'size' => $value,
	);
}

/* ------------------------------------------------------------------------- *
 * Element helpers. Each exists to defeat one Elementor default that would
 * otherwise leak into the design.
 * ------------------------------------------------------------------------- */

/** Deterministic ids, so a re-seed produces the same tree and diffs cleanly. */
function acspv_id(): string {
	static $acspv_n = 0;
	++$acspv_n;

	return sprintf( 'acspv%03d', $acspv_n );
}

/**
 * Merges settings while keeping `__globals__` a union rather than letting the
 * later array replace the whole map — one element carries exactly one
 * `__globals__`, so a naive array_merge silently drops every earlier binding.
 */
function acspv_merge( array $base, array $extra ): array {
	$acspv_globals = array_merge(
		isset( $base['__globals__'] ) ? $base['__globals__'] : array(),
		isset( $extra['__globals__'] ) ? $extra['__globals__'] : array()
	);

	$acspv_merged = array_merge( $base, $extra );

	if ( $acspv_globals ) {
		$acspv_merged['__globals__'] = $acspv_globals;
	}

	return $acspv_merged;
}

/**
 * Structural container. The explicit zero padding and gap are load-bearing:
 * the kit ships a default container padding and a 20px --widgets-spacing that
 * would otherwise appear between every element on the page.
 */
function acspv_row( array $settings, array $children = array() ): array {
	$acspv_settings = acspv_merge(
		array(
			'content_width' => 'full',
			'padding'       => acspv_box( '0', '0', '0', '0' ),
			'flex_gap'      => acspv_gap( 0 ),
		),
		$settings
	);

	// Elementor's own mobile rule is `.e-con.e-flex { --width: 100% }`, which
	// silently overrides a declared width below 768px — a fixed-size circle
	// keeps its min-height, stretches to the viewport, and renders as an
	// ellipse. A per-breakpoint value outranks it, so a width declared once is
	// honoured everywhere.
	if ( isset( $acspv_settings['width'] ) ) {
		foreach ( array( 'width_tablet', 'width_mobile' ) as $acspv_bp ) {
			if ( ! isset( $acspv_settings[ $acspv_bp ] ) ) {
				$acspv_settings[ $acspv_bp ] = $acspv_settings['width'];
			}
		}
	}

	return array(
		'id'       => acspv_id(),
		'elType'   => 'container',
		'settings' => $acspv_settings,
		'elements' => $children,
	);
}

/** A page section: boxed to the measure, with the vertical rhythm. */
function acspv_section( string $anchor, array $settings, array $children ): array {
	return acspv_row(
		acspv_merge(
			array(
				'_element_id'    => $anchor,
				'content_width'  => 'boxed',
				'boxed_width'    => acspv_size( 1120 ),
				'overflow'       => 'hidden',
				'flex_direction' => 'column',
				'padding'        => acspv_box( '110', '24', '110', '24' ),
				'padding_tablet' => acspv_box( '80', '24', '80', '24' ),
				'padding_mobile' => acspv_box( '56', '20', '56', '20' ),
			),
			$settings
		),
		$children
	);
}

/** The 50/50 the design uses three times. Grid, so both halves share a row. */
function acspv_split( array $left, array $right, int $gap = 72 ): array {
	return acspv_row(
		array(
			'container_type'           => 'grid',
			'grid_columns_grid'        => array(
				'unit' => 'fr',
				'size' => 2,
			),
			'grid_columns_grid_tablet' => array(
				'unit' => 'fr',
				'size' => 1,
			),
			'grid_rows_grid'           => acspv_css( 'auto' ),
			'grid_gaps'                => acspv_gap( $gap ),
			'grid_gaps_tablet'         => acspv_gap( 40 ),
			'grid_gaps_mobile'         => acspv_gap( 28 ),
			'grid_align_items'         => 'center',
		),
		array( $left, $right )
	);
}

function acspv_widget( string $type, array $settings ): array {
	return array(
		'id'         => acspv_id(),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
		'elements'   => array(),
	);
}

/** Display type. Sizes carry clamp() through the custom unit. */
function acspv_heading( string $text, string $tag, string $size, array $extra = array() ): array {
	return acspv_widget(
		'heading',
		acspv_merge(
			array(
				'title'                     => $text,
				'header_size'               => $tag,
				'typography_typography'     => 'custom',
				'typography_font_size'      => acspv_css( $size ),
				'typography_line_height'    => acspv_css( '1.08' ),
				'typography_letter_spacing' => acspv_size( -0.022, 'em' ),
				// Family named directly rather than bound to the Global Font.
				// A global-linked typography group rewrites EVERY field in the
				// group to that global's vars, so binding it here would throw
				// away the size and line-height below and leave the browser
				// defaults. The kit still carries the families, which is what
				// enqueues the webfonts.
				'typography_font_family'    => 'Bricolage Grotesque',
				'__globals__'               => array( 'title_color' => 'globals/colors?id=acsink' ),
			),
			$extra
		)
	);
}

/** Muted body copy, held to a readable measure. */
function acspv_body( string $text, string $size = '1.0625rem', array $extra = array() ): array {
	return acspv_widget(
		'heading',
		acspv_merge(
			array(
				'title'                  => $text,
				'header_size'            => 'p',
				'typography_typography'  => 'custom',
				'typography_font_size'   => acspv_css( $size ),
				'typography_line_height' => acspv_css( '1.65' ),
				'_element_width'         => 'initial',
				'_element_custom_width'  => acspv_css( 'min(56ch, 100%)' ),
				'typography_font_family' => 'Instrument Sans',
				'__globals__'            => array( 'title_color' => 'globals/colors?id=acsmut' ),
			),
			$extra
		)
	);
}

/** A solid block of one Global Color — the hero graphic is made of these. */
function acspv_shape( string $color, array $settings ): array {
	return acspv_row(
		acspv_merge(
			array(
				'background_background' => 'classic',
				'__globals__'           => array( 'background_color' => 'globals/colors?id=' . $color ),
			),
			$settings
		)
	);
}

/* ------------------------------------------------------------------------- *
 * The page
 * ------------------------------------------------------------------------- */

// Header. `arts_header_position` of '' is Top (fixed overlay) — the empty
// string is a real value here, not "unset". `arts_header_sticky_global_colors`
// is deliberately left empty: a global remapped there stops responding to Alt
// for as long as the header is stuck, which is the one documented conflict
// between these two plugins.
$acspv_header = acspv_row(
	array(
		'_title'                => 'Header',
		'flex_direction'        => 'row',
		'flex_justify_content'  => 'space-between',
		'flex_align_items'      => 'center',
		'flex_gap'              => acspv_gap( 28 ),
		'flex_wrap_mobile'      => 'nowrap',
		'padding'               => acspv_box( '14', '32', '14', '32' ),
		'padding_mobile'        => acspv_box( '10', '14', '10', '14' ),
		'background_background' => 'classic',
		'border_border'         => 'solid',
		'border_width'          => acspv_box( '0', '0', '1', '0' ),
		'arts_header_enabled'   => 'yes',
		'arts_header_position'  => '',
		'arts_header_on_scroll' => 'sticky',
		'__globals__'           => array(
			'background_color' => 'globals/colors?id=acspnl',
			'border_color'     => 'globals/colors?id=acsline',
		),
	),
	array(
		acspv_row(
			array(
				'flex_direction'   => 'row',
				'flex_align_items' => 'center',
				'flex_gap'         => acspv_gap( 10 ),
				'width'            => acspv_css( 'auto' ),
				'flex_wrap_mobile' => 'nowrap',
			),
			array(
				acspv_widget(
					'image',
					array(
						'image'          => array(
							'url'             => $acspv_url['logo'],
							'id'              => '',
							'arts_cs_alt_url' => $acspv_url['logo_alt'],
							'arts_cs_alt_id'  => '',
						),
						'image_size'     => 'full',
						'width'          => acspv_size( 30 ),
						'_element_width' => 'auto',
					)
				),
				acspv_heading(
					'Arts Color Switcher for Elementor',
					'p',
					'0.9375rem',
					array(
						'typography_font_weight'      => '600',
						'typography_font_size_mobile' => acspv_size( 11 ),
						'typography_line_height'      => acspv_css( '1.2' ),
						'typography_letter_spacing'   => acspv_size( -0.01, 'em' ),
						'_element_width'              => 'auto',
					)
				),
			)
		),

		// Free Elementor has no menu widget. Icon List is the closest real one:
		// an actual <ul>, inline layout, and — the part that matters here —
		// separate resting and hover colours, both bindable to Global Colors.
		acspv_widget(
			'icon-list',
			array(
				'view'                        => 'inline',
				'link_click'                  => 'inline',
				'space_between'               => acspv_size( 30 ),
				'icon_list'                   => array(
					array(
						'_id'           => 'acsnav1',
						'text'          => 'Toggle',
						'link'          => array(
							'url'         => '#acs-toggle',
							'is_external' => '',
							'nofollow'    => '',
						),
						'selected_icon' => array(
							'value'   => '',
							'library' => '',
						),
					),
					array(
						'_id'           => 'acsnav2',
						'text'          => 'Colors',
						'link'          => array(
							'url'         => '#acs-colors',
							'is_external' => '',
							'nofollow'    => '',
						),
						'selected_icon' => array(
							'value'   => '',
							'library' => '',
						),
					),
					array(
						'_id'           => 'acsnav3',
						'text'          => 'Images',
						'link'          => array(
							'url'         => '#acs-images',
							'is_external' => '',
							'nofollow'    => '',
						),
						'selected_icon' => array(
							'value'   => '',
							'library' => '',
						),
					),
					array(
						'_id'           => 'acsnav4',
						'text'          => 'On scroll',
						'link'          => array(
							'url'         => '#acs-scroll',
							'is_external' => '',
							'nofollow'    => '',
						),
						'selected_icon' => array(
							'value'   => '',
							'library' => '',
						),
					),
				),
				'hide_mobile'                 => 'hidden-mobile',
				'icon_typography_typography'  => 'custom',
				'icon_typography_font_family' => 'Instrument Sans',
				'icon_typography_font_weight' => '500',
				'icon_typography_font_size'   => acspv_size( 15 ),
				'text_color_hover_transition' => acspv_size( 0.2, 's' ),
				'_element_width'              => 'auto',
				'__globals__'                 => array(
					'text_color'       => 'globals/colors?id=acsmut',
					'text_color_hover' => 'globals/colors?id=acsacc',
				),
			)
		),

		// Buttons skin, three states, no words. The plugin ships monitor/sun/moon
		// icons of its own, so clearing the labels is all "icons only" takes.
		acspv_widget(
			'arts-color-switcher-toggle',
			array(
				'_skin'          => 'buttons',
				'buttons_style'  => 'joined',
				'mode'           => 'cycle',
				'buttons_icons'  => 'yes',
				'label_system'   => '',
				'label_default'  => '',
				'label_alt'      => '',
				'_element_width' => 'auto',
				'__globals__'    => array(
					'icon_color'        => 'globals/colors?id=acsmut',
					'icon_color_hover'  => 'globals/colors?id=acsink',
					'icon_color_active' => 'globals/colors?id=acspnl',
					'indicator_color'   => 'globals/colors?id=acsacc',
				),
			)
		),
	)
);

// Hero. The graphic on the right is built from containers rather than an image
// so that it morphs continuously with the scalar like everything else — an
// image could only swap at the settled ends.
$acspv_hero_card = acspv_row(
	array(
		'background_background' => 'classic',
		'border_border'         => 'solid',
		'border_width'          => acspv_box( '1', '1', '1', '1' ),
		'border_radius'         => acspv_box( '30', '30', '30', '30' ),
		'padding'               => acspv_box( '22', '22', '22', '22' ),
		'z_index'               => 5,
		'__globals__'           => array(
			'background_color' => 'globals/colors?id=acspnl',
			'border_color'     => 'globals/colors?id=acsline',
		),
	),
	array(
		acspv_row(
			array(
				'background_background' => 'classic',
				'border_radius'         => acspv_box( '18', '18', '18', '18' ),
				'padding'               => acspv_box( '22', '22', '26', '22' ),
				'flex_direction'        => 'column',
				'flex_gap'              => acspv_gap( 22 ),
				'__globals__'           => array( 'background_color' => 'globals/colors?id=acssurf' ),
			),
			array(
				// Title bar row: a dot, a bar, and a switch that is only ever a picture of one.
				acspv_row(
					array(
						'flex_direction'       => 'row',
						'flex_justify_content' => 'space-between',
						'flex_align_items'     => 'center',
						'flex_gap'             => acspv_gap( 12 ),
					),
					array(
						acspv_row(
							array(
								'flex_direction'   => 'row',
								'flex_align_items' => 'center',
								'flex_gap'         => acspv_gap( 10 ),
								'width'            => acspv_css( 'auto' ),
							),
							array(
								acspv_shape(
									'acsacc',
									array(
										'width'         => acspv_size( 18 ),
										'min_height'    => acspv_size( 18 ),
										'border_radius' => acspv_box( '50', '50', '50', '50', '%' ),
									)
								),
								acspv_shape(
									'acsink',
									array(
										'width'         => acspv_size( 104 ),
										'min_height'    => acspv_size( 12 ),
										'border_radius' => acspv_box( '6', '6', '6', '6' ),
									)
								),
							)
						),
						acspv_shape(
							'acsline',
							array(
								'width'            => acspv_size( 62 ),
								'min_height'       => acspv_size( 26 ),
								'border_radius'    => acspv_box( '13', '13', '13', '13' ),
								'padding'          => acspv_box( '4', '4', '4', '4' ),
								'flex_direction'   => 'row',
								'flex_align_items' => 'center',
							)
						),
					)
				),
				acspv_shape(
					'acsacc',
					array(
						'min_height'    => acspv_size( 112 ),
						'border_radius' => acspv_box( '16', '16', '16', '16' ),
					)
				),
				acspv_row(
					array(
						'flex_direction' => 'column',
						'flex_gap'       => acspv_gap( 14 ),
					),
					array(
						acspv_shape(
							'acsink',
							array(
								'width'         => acspv_size( 62, '%' ),
								'min_height'    => acspv_size( 12 ),
								'border_radius' => acspv_box( '6', '6', '6', '6' ),
							)
						),
						acspv_shape(
							'acsmut',
							array(
								'width'         => acspv_size( 44, '%' ),
								'min_height'    => acspv_size( 12 ),
								'border_radius' => acspv_box( '6', '6', '6', '6' ),
							)
						),
						acspv_shape(
							'acsline',
							array(
								'width'         => acspv_size( 27, '%' ),
								'min_height'    => acspv_size( 12 ),
								'border_radius' => acspv_box( '6', '6', '6', '6' ),
							)
						),
					)
				),
			)
		),
	)
);

$acspv_hero = acspv_section(
	'acs-top',
	array(
		'padding'        => acspv_box( '170', '24', '110', '24' ),
		'padding_tablet' => acspv_box( '140', '24', '80', '24' ),
		'padding_mobile' => acspv_box( '96', '20', '56', '20' ),
	),
	array(
		acspv_split(
			acspv_row(
				array(
					'flex_direction' => 'column',
					'flex_gap'       => acspv_gap( 22 ),
				),
				array(
					acspv_heading( 'A dark mode you actually designed.', 'h1', 'clamp(2.7rem, 5.6vw, 4.4rem)', array() ),
					acspv_body( 'Every Global Color gets a second swatch. Visitors move between the palette you built for day and the one you built for night — not one a filter guessed by inverting your site.', '1.1875rem', array( '_element_custom_width' => acspv_css( 'min(48ch, 100%)' ) ) ),
					acspv_widget(
						'button',
						array(
							'text'                   => 'Four ways to place it',
							'link'                   => array(
								'url'         => '#acs-toggle',
								'is_external' => '',
								'nofollow'    => '',
							),
							'button_type'            => '',
							'border_radius'          => acspv_box( '10', '10', '10', '10' ),
							'text_padding'           => acspv_box( '13', '20', '13', '20' ),
							'typography_typography'  => 'custom',
							'typography_font_size'   => acspv_size( 15 ),
							'typography_font_weight' => '500',
							'typography_font_family' => 'Instrument Sans',
							'background_background'  => 'classic',
							'_element_width'         => 'auto',
							'__globals__'            => array(
								'background_color'  => 'globals/colors?id=acsacc',
								'button_text_color' => 'globals/colors?id=acspnl',
								'button_background_hover_color' => 'globals/colors?id=acsink',
							),
						)
					),
				)
			),
			acspv_row(
				array( 'flex_direction' => 'column' ),
				array(
					acspv_shape(
						'acsacc',
						array(
							'position'              => 'absolute',
							'_offset_orientation_h' => 'end',
							'_offset_x_end'         => acspv_size( -26 ),
							'_offset_y'             => acspv_size( -34 ),
							'width'                 => acspv_size( 118 ),
							'min_height'            => acspv_size( 118 ),
							'border_radius'         => acspv_box( '50', '50', '50', '50', '%' ),
							'z_index'               => 0,
						)
					),
					$acspv_hero_card,
				)
			)
		),
	)
);

/** One skin cell: its name, the live control, and where you would put it. */
function acspv_skin( string $name, string $skin, string $note, array $extra = array() ): array {
	return acspv_row(
		array(
			'background_background' => 'classic',
			'border_border'         => 'solid',
			'border_width'          => acspv_box( '1', '1', '1', '1' ),
			'border_radius'         => acspv_box( '14', '14', '14', '14' ),
			'padding'               => acspv_box( '26', '24', '26', '24' ),
			'flex_direction'        => 'column',
			'flex_align_items'      => 'flex-start',
			'flex_gap'              => acspv_gap( 18 ),
			'__globals__'           => array(
				'background_color' => 'globals/colors?id=acspnl',
				'border_color'     => 'globals/colors?id=acsline',
			),
		),
		array(
			acspv_body(
				$name,
				'0.6875rem',
				array(
					'typography_font_weight'    => '600',
					'typography_text_transform' => 'uppercase',
					'typography_letter_spacing' => acspv_size( 0.12, 'em' ),
				)
			),
			acspv_widget(
				'arts-color-switcher-toggle',
				acspv_merge(
					array(
						'_skin'          => $skin,
						'_element_width' => 'auto',
						'__globals__'    => array(
							'icon_color'         => 'globals/colors?id=acsmut',
							'icon_color_hover'   => 'globals/colors?id=acsink',
							'icon_color_active'  => 'globals/colors?id=acspnl',
							'indicator_color'    => 'globals/colors?id=acsacc',
							'label_color'        => 'globals/colors?id=acsmut',
							'label_color_active' => 'globals/colors?id=acspnl',
							// The open list is drawn by the platform, so the chosen
							// row takes the indicator fill and needs its own text
							// colour or it renders dark-on-accent.
							'list_color'         => 'globals/colors?id=acsink',
							'list_color_active'  => 'globals/colors?id=acspnl',
							'list_background'    => 'globals/colors?id=acspnl',
						),
					),
					$extra
				)
			),
			acspv_body( $note, '0.875rem' ),
		)
	);
}

$acspv_skins = acspv_section(
	'acs-toggle',
	array(),
	array(
		acspv_split(
			acspv_row(
				array(
					'container_type'           => 'grid',
					'grid_columns_grid'        => array(
						'unit' => 'fr',
						'size' => 2,
					),
					'grid_columns_grid_mobile' => array(
						'unit' => 'fr',
						'size' => 1,
					),
					'grid_rows_grid'           => acspv_css( 'auto' ),
					'grid_gaps'                => acspv_gap( 14 ),
					'grid_gaps_mobile'         => acspv_gap( 12 ),
				),
				array(
					acspv_skin( 'Icon', 'icon', 'A header corner, where there is room for one control.' ),
					acspv_skin( 'Switch', 'switch', 'Reads as a setting. At home in a footer or a preferences row.' ),
					acspv_skin(
						'Buttons',
						'buttons',
						'Joined or separate, words or icons or both.',
						array(
							'mode'                        => 'cycle',
							'buttons_style'               => 'joined',
							'buttons_icons'               => '',
							// "System" cannot shrink below its own content, so it
							// overflows its 1fr column while the travelling mark
							// stays a uniform 1/N and clips the word. "Auto" is the
							// width class of the other two, so the columns equalise
							// and the mark lines up with them.
							'label_system'                => 'Auto',
							'label_typography_typography' => 'custom',
							'label_typography_font_size'  => acspv_size( 13 ),
							'option_padding'              => acspv_box( '6', '12', '6', '12' ),
						)
					),
					acspv_skin( 'Dropdown', 'dropdown', 'The platform’s own picker — the safe choice on mobile.', array( 'mode' => 'cycle' ) ),
				)
			),
			acspv_row(
				array(
					'flex_direction' => 'column',
					'flex_gap'       => acspv_gap( 20 ),
				),
				array(
					acspv_heading( 'Four shapes, one behaviour.', 'h2', 'clamp(2rem, 3.6vw, 3rem)', array() ),
					acspv_body( 'Widget or shortcode — it drops into a header, a footer, or a menu. Leave a visitor on System and the site keeps tracking their own device.', '1.1875rem' ),
				)
			)
		),
	)
);

/** A feature card: icon in its own column, text in the other. */
function acspv_card( string $icon, string $title, string $note ): array {
	return acspv_row(
		array(
			'background_background' => 'classic',
			'border_border'         => 'solid',
			'border_width'          => acspv_box( '1', '1', '1', '1' ),
			'border_radius'         => acspv_box( '14', '14', '14', '14' ),
			'padding'               => acspv_box( '22', '22', '22', '22' ),
			'flex_direction'        => 'row',
			'flex_align_items'      => 'flex-start',
			'flex_gap'              => acspv_gap( 16 ),
			'__globals__'           => array(
				'background_color' => 'globals/colors?id=acspnl',
				'border_color'     => 'globals/colors?id=acsline',
			),
		),
		array(
			acspv_widget(
				'icon',
				array(
					'selected_icon'  => array(
						'value'   => $icon,
						'library' => 'fa-solid',
					),
					'view'           => 'default',
					'size'           => acspv_size( 20 ),
					'_element_width' => 'auto',
					'__globals__'    => array( 'primary_color' => 'globals/colors?id=acsacc' ),
				)
			),
			acspv_row(
				array(
					'flex_direction' => 'column',
					'flex_gap'       => acspv_gap( 6 ),
				),
				array(
					acspv_heading( $title, 'h3', '1.0625rem', array( 'typography_line_height' => acspv_css( '1.35' ) ) ),
					acspv_body( $note, '0.9375rem' ),
				)
			),
		)
	);
}

$acspv_colors_section = acspv_section(
	'acs-colors',
	array(),
	array(
		acspv_split(
			acspv_row(
				array(
					'flex_direction' => 'column',
					'flex_gap'       => acspv_gap( 20 ),
				),
				array(
					acspv_heading( 'Nothing to rebuild. Your widgets already reference the colors.', 'h2', 'clamp(2rem, 3.6vw, 3rem)', array() ),
					acspv_body( 'This menu, the buttons, the borders, the icons — none configured twice. They point at Global Colors, and the plugin re-points those.', '1.1875rem' ),
				)
			),
			acspv_row(
				array(
					'flex_direction' => 'column',
					'flex_gap'       => acspv_gap( 14 ),
				),
				array(
					acspv_card( 'fas fa-adjust', 'Two swatches, one row', 'An Alt value sits beside every Global Color in Site Settings. Same picker, same screen.' ),
					acspv_card( 'fas fa-sliders-h', 'Adopt it one color at a time', 'A color with no Alt keeps its original, so a live site can go dark over a week, not a weekend.' ),
					acspv_card( 'fas fa-bolt', 'No flash on load', 'Open a page set to dark and it arrives dark. No white blink before the colors catch up.' ),
				)
			)
		),
	)
);

/** One swapping still. Both files are drawn by hand above, light and dark. */
function acspv_still_widget( string $url, string $alt_url ): array {
	return acspv_row(
		array(
			'border_border' => 'solid',
			'border_width'  => acspv_box( '1', '1', '1', '1' ),
			'border_radius' => acspv_box( '14', '14', '14', '14' ),
			'overflow'      => 'hidden',
			'__globals__'   => array( 'border_color' => 'globals/colors?id=acsline' ),
		),
		array(
			acspv_widget(
				'image',
				array(
					'image'      => array(
						'url'             => $url,
						'id'              => '',
						'arts_cs_alt_url' => $alt_url,
						'arts_cs_alt_id'  => '',
					),
					'image_size' => 'full',
					'width'      => acspv_size( 100, '%' ),
				)
			),
		)
	);
}

$acspv_images = acspv_section(
	'acs-images',
	array( 'flex_gap' => acspv_gap( 44 ) ),
	array(
		acspv_row(
			array(
				'flex_direction'   => 'column',
				'flex_align_items' => 'center',
				'flex_gap'         => acspv_gap( 18 ),
			),
			array(
				acspv_heading(
					'Give an image a second file and it switches with the rest.',
					'h2',
					'clamp(2rem, 3.6vw, 3rem)',
					array(
						'align'                 => 'center',
						'_element_width'        => 'initial',
						// px, not ch: the wrapper this lands on inherits the body
						// font-size, so a ch measure on a display face is ~4x tight.
						'_element_custom_width' => acspv_css( 'min(660px, 100%)' ),
					)
				),
				acspv_body(
					'Screenshots on white, diagrams, product shots, a logo drawn in black — artwork made for a light page rarely survives a dark one. Each takes its own night version.',
					'1.1875rem',
					array(
						'align'                 => 'center',
						'_element_custom_width' => acspv_css( 'min(54ch, 100%)' ),
					)
				),
			)
		),
		acspv_row(
			array(
				'container_type'           => 'grid',
				'grid_columns_grid'        => array(
					'unit' => 'fr',
					'size' => 3,
				),
				'grid_columns_grid_mobile' => array(
					'unit' => 'fr',
					'size' => 1,
				),
				'grid_rows_grid'           => acspv_css( 'auto' ),
				'grid_gaps'                => acspv_gap( 22 ),
			),
			array(
				acspv_still_widget( $acspv_url['a'], $acspv_url['a_alt'] ),
				acspv_still_widget( $acspv_url['b'], $acspv_url['b_alt'] ),
				acspv_still_widget( $acspv_url['c'], $acspv_url['c_alt'] ),
			)
		),
	)
);

/**
 * A shape drifting through the closing section. Absolute positioning is free
 * on containers (`position`, unprefixed — the underscore-prefixed twin is the
 * widget key), so these need no CSS and, being Global Colors, they morph along
 * with the page rather than sitting inert.
 */
function acspv_drift( string $color, string $side, float $x, float $y, int $size, bool $ring = false ): array {
	// Sized for a 1440px canvas; at 390px the same circles swamp the column and
	// cut through the headline, so each one steps down with the breakpoint.
	$acspv_tablet = (int) round( $size * 0.7 );
	$acspv_mobile = (int) round( $size * 0.42 );

	$acspv_settings = array(
		'position'              => 'absolute',
		'_offset_orientation_h' => 'end' === $side ? 'end' : 'start',
		'_offset_y'             => acspv_size( $y, '%' ),
		'width'                 => acspv_size( $size ),
		'width_tablet'          => acspv_size( $acspv_tablet ),
		'width_mobile'          => acspv_size( $acspv_mobile ),
		'min_height'            => acspv_size( $size ),
		'min_height_tablet'     => acspv_size( $acspv_tablet ),
		'min_height_mobile'     => acspv_size( $acspv_mobile ),
		'border_radius'         => acspv_box( '50', '50', '50', '50', '%' ),
		'z_index'               => 0,
	);

	$acspv_settings[ 'end' === $side ? '_offset_x_end' : '_offset_x' ] = acspv_size( $x, '%' );

	if ( $ring ) {
		// A ring is the same circle with its fill dropped and a thick border —
		// no extra element, and the border takes a Global Color too.
		return acspv_row(
			acspv_merge(
				$acspv_settings,
				array(
					'border_border'       => 'solid',
					'border_width'        => acspv_box( '13', '13', '13', '13' ),
					'border_width_mobile' => acspv_box( '6', '6', '6', '6' ),
					'__globals__'         => array( 'border_color' => 'globals/colors?id=' . $color ),
				)
			)
		);
	}

	return acspv_shape( $color, $acspv_settings );
}

// The closer. Tall on purpose: the morph is meant to be felt on the way in
// rather than noticed, so the viewport handles sit apart and it scrubs.
$acspv_closer = acspv_row(
	array(
		'_element_id'          => 'acs-scroll',
		'min_height'           => acspv_size( 210, 'vh' ),
		'min_height_tablet'    => acspv_size( 180, 'vh' ),
		'min_height_mobile'    => acspv_size( 150, 'vh' ),
		'overflow'             => 'hidden',
		'flex_direction'       => 'column',
		'flex_justify_content' => 'center',
		'padding'              => acspv_box( '0', '24', '0', '24' ),
		'arts_cs_enabled'      => 'switch',
		'arts_cs_viewport'     => array(
			'unit'  => '%',
			'sizes' => array(
				'start' => 0,
				'end'   => 100,
			),
		),
	),
	array(
		// Kept clear of the 30-70% band, where the centred text sits. They are
		// already behind it — a hit-test across the headline returns the H2 at
		// every point — but a saturated ring immediately behind a glyph reads
		// as a collision whatever the stacking order says, and on a phone the
		// column is narrow enough that there is nowhere for the eye to escape.
		acspv_drift( 'acsacc', 'start', -6, 4, 190 ),
		acspv_drift( 'acsink', 'end', 4, 13, 110 ),
		acspv_drift( 'acsacc', 'end', -4, 23, 230, true ),
		acspv_drift( 'acsmut', 'start', 5, 71, 90 ),
		acspv_drift( 'acsacc', 'start', -3, 79, 160 ),
		acspv_drift( 'acsline', 'end', 10, 87, 120 ),
		acspv_drift( 'acsink', 'start', 42, 94, 130 ),
		acspv_row(
			array(
				'content_width'    => 'boxed',
				'boxed_width'      => acspv_size( 640 ),
				'flex_direction'   => 'column',
				'flex_align_items' => 'center',
				'flex_gap'         => acspv_gap( 20 ),
				'z_index'          => 5,
			),
			array(
				acspv_heading(
					'A section can switch the page on its own, as you scroll into it.',
					'h2',
					'clamp(2rem, 3.6vw, 3rem)',
					array( 'align' => 'center' )
				),
				acspv_body(
					'A dark interlude in a light story — a case study, a gallery, a quote from a founder — and then back again.',
					'1.0625rem',
					array(
						'align'                 => 'center',
						'_element_custom_width' => acspv_css( 'min(46ch, 100%)' ),
					)
				),
				acspv_widget(
					'button',
					array(
						'text'                   => 'Download Plugin',
						'link'                   => array(
							'url'         => 'https://wordpress.org/plugins/color-switcher-for-elementor/',
							'is_external' => 'on',
							'nofollow'    => '',
						),
						'selected_icon'          => array(
							'value'   => 'fab fa-wordpress',
							'library' => 'fa-brands',
						),
						'icon_align'             => 'left',
						'icon_indent'            => acspv_size( 9 ),
						'align'                  => 'center',
						'border_radius'          => acspv_box( '10', '10', '10', '10' ),
						'text_padding'           => acspv_box( '14', '22', '14', '22' ),
						'typography_typography'  => 'custom',
						'typography_font_size'   => acspv_size( 15 ),
						'typography_font_weight' => '500',
						'typography_font_family' => 'Instrument Sans',
						'background_background'  => 'classic',
						'_element_width'         => 'auto',
						'__globals__'            => array(
							'background_color'  => 'globals/colors?id=acsacc',
							'button_text_color' => 'globals/colors?id=acspnl',
							'button_background_hover_color' => 'globals/colors?id=acsink',
						),
					)
				),
			)
		),
	)
);

$acspv_footer = acspv_row(
	array(
		'_title'           => 'Footer',
		'border_border'    => 'solid',
		'border_width'     => acspv_box( '1', '0', '0', '0' ),
		'padding'          => acspv_box( '46', '32', '52', '32' ),
		'flex_direction'   => 'column',
		'flex_align_items' => 'center',
		'flex_gap'         => acspv_gap( 8 ),
		'__globals__'      => array( 'border_color' => 'globals/colors?id=acsline' ),
	),
	array(
		acspv_heading( 'Arts Color Switcher for Elementor', 'h3', '1.0625rem', array( 'align' => 'center' ) ),
		acspv_body(
			'Designed by Artem Semkin',
			'0.8125rem',
			array(
				'align'       => 'center',
				'link'        => array(
					'url'         => 'https://artemsemkin.com',
					'is_external' => 'on',
					'nofollow'    => '',
				),
				'__globals__' => array( 'title_hover_color' => 'globals/colors?id=acsacc' ),
			)
		),
	)
);

$acspv_data = array( $acspv_header, $acspv_hero, $acspv_skins, $acspv_colors_section, $acspv_images, $acspv_closer, $acspv_footer );

/* ------------------------------------------------------------------------- *
 * Persist
 * ------------------------------------------------------------------------- */

$acspv_slug          = 'color-switcher-preview';
$acspv_existing_page = get_page_by_path( $acspv_slug, OBJECT, 'page' );

if ( $acspv_existing_page ) {
	wp_delete_post( $acspv_existing_page->ID, true );
}

$acspv_page_id = wp_insert_post(
	array(
		'import_id'   => ACS_PREVIEW_DEMO_PAGE_ID,
		'post_title'  => 'Color Switcher',
		'post_name'   => $acspv_slug,
		'post_type'   => 'page',
		'post_status' => 'publish',
	)
);

// Canvas twice on purpose: the page setting is what Elementor's own panel
// reads, `_wp_page_template` is what WordPress's template loader reads. Setting
// only one of them leaves the theme's header and footer on the page.
update_post_meta( $acspv_page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $acspv_page_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $acspv_page_id, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $acspv_page_id, '_elementor_data', wp_slash( wp_json_encode( $acspv_data, JSON_UNESCAPED_UNICODE ) ) );
update_post_meta(
	$acspv_page_id,
	'_elementor_page_settings',
	array(
		'template'              => 'elementor_canvas',
		'hide_title'            => 'yes',
		// The document's own surface. Without this the page keeps the theme's
		// white behind everything: harmless-looking in the light palette, and
		// in the dark one it strands near-white headings on a white page.
		'background_background' => 'classic',
		'__globals__'           => array( 'background_color' => 'globals/colors?id=acssurf' ),
	)
);
update_post_meta( $acspv_page_id, '_wp_page_template', 'elementor_canvas' );

// No `arts_cs_page_theme`: the preview opens in the visitor's own device theme,
// which is both the best first impression and a free demonstration of Auto.

update_option( 'page_on_front', $acspv_page_id );
update_option( 'show_on_front', 'page' );
update_option( 'blogname', 'Color Switcher' );
update_option( 'blogdescription', '' );

// Generated CSS never diffs itself, and the editor prefers a newer autosave
// over raw meta — a re-seed shows the previous page without both of these.
if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
	\Elementor\Core\Files\CSS\Post::create( $acspv_page_id )->delete();
}

delete_post_meta( $acspv_page_id, '_elementor_element_cache' );

foreach ( wp_get_post_revisions( $acspv_page_id, array( 'fields' => 'ids' ) ) as $acspv_revision_id ) {
	wp_delete_post_revision( $acspv_revision_id );
}

Elementor::$instance->files_manager->clear_cache();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::success( 'Preview page seeded: ' . get_permalink( $acspv_page_id ) );
}
