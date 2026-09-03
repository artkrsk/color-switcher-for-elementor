<?php

namespace Arts\ColorSwitcher\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\ColorSwitcher\Base\Manager as BaseManager;

class Assets extends BaseManager {

	const HANDLE          = 'arts-color-switcher';
	const HANDLE_EDITOR   = 'arts-color-switcher-editor';
	const HANDLE_POLYFILL = 'scroll-timeline-polyfill';

	public function register_frontend(): void {
		wp_register_style(
			self::HANDLE,
			$this->asset_url( 'color-switcher-for-elementor.css' ),
			array(),
			$this->asset_version( 'color-switcher-for-elementor.css' )
		);

		// The polyfill handle is a hard dependency: it orders the loader ahead
		// of us and publishes the readiness promise the scroll-bound tier
		// awaits. Shared across Arts plugins — first registration wins.
		wp_register_script(
			self::HANDLE,
			$this->asset_url( 'color-switcher-for-elementor.js' ),
			array( self::HANDLE_POLYFILL ),
			$this->asset_version( 'color-switcher-for-elementor.js' ),
			true
		);
	}

	/**
	 * Unconditional, deliberately. Deciding cost a json_decode plus a full
	 * re-encode of the document data on every request — more work than the
	 * ~4KB gzipped it withheld, and the containment check it could afford
	 * matched three quarters of documents that had no zone at all. The bundle
	 * is inert without zones: boot attaches nothing when no element carries
	 * one, and the stylesheet is variable plumbing the kit CSS needs anyway
	 * wherever a page sets a non-default baseline.
	 */
	public function enqueue_frontend(): void {
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Our stylesheet never goes through the polyfill's CSS transpiler: the
	 * scroll-bound tier drives WAAPI directly against the polyfill surface.
	 *
	 * @param array<int, string> $handles Style handles to skip.
	 * @return array<int, string>
	 */
	public function skip_polyfill_transpiling( $handles ): array {
		if ( ! is_array( $handles ) ) {
			$handles = array();
		}

		$handles[] = self::HANDLE;

		return $handles;
	}

	/**
	 * The pre-paint reconciler.
	 *
	 * The server renders only the cacheable half of the state — the page's
	 * authored baseline and the site default — because a cookie-derived
	 * attribute baked into a full-page cache would serve one visitor's theme
	 * to everyone. The visitor's own layer is therefore applied here, before
	 * the first paint, where no cache can capture it.
	 *
	 * Printed raw rather than through `wp_add_inline_script()`: a src-less
	 * handle is the tidier idiom, but it puts this in the script queue where
	 * an optimizer can aggregate or defer it, and a deferred pre-paint script
	 * is precisely the flash it exists to prevent. The two attributes ask the
	 * common optimizers to leave it alone for the same reason.
	 *
	 * Printed only where the feature is configured, so an unconfigured site
	 * carries no head script. (It only ever READS the cookie; the sole writer
	 * is a toggle press.)
	 */
	public function print_head_script(): void {
		if ( null === $this->managers || ! $this->managers->kit->has_alt_colors() ) {
			return;
		}

		$cookie    = Documents::COOKIE_PREFERENCE;
		$attribute = Documents::ATTR_PREFERENCE;

		// Whether this page defers to the visitor's device, inlined because the
		// script runs in <head> where the document wrapper — and so
		// `data-arts-cs-baseline` — does not exist yet. Page-dependent but
		// visitor-independent, so a shared cache entry stays correct.
		$auto = Documents::THEME_AUTO === $this->managers->documents->get_current_page_theme() ? '1' : '0';

		// Small enough to read at a glance, stable enough not to earn a build
		// step — and inlining it removes a runtime file read that could fail
		// silently and take the no-flash guarantee with it. Concatenated plain
		// strings rather than a heredoc: Plugin Check rejects heredoc syntax
		// outright (PluginCheck.CodeAnalysis.Heredoc).
		$script =
			"(function(){var d=document.documentElement;var m=document.cookie.match(/(?:^|;\\s*){$cookie}=([^;]*)/);var p=m&&m[1];\n" .
			"var s=!!(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);\n" .
			"if(p==='system'||p==='default'||p==='alt'){\n" .
			"d.setAttribute('{$attribute}',p);\n" .
			"if(p==='alt'||(p==='system'&&s)){d.setAttribute('data-arts-cs','alt');}\n" .
			"else{d.removeAttribute('data-arts-cs');}return;}\n" .
			"if({$auto}&&s){d.setAttribute('data-arts-cs','alt');}})();";

		printf(
			'<script id="arts-cs-head-js" data-no-optimize="1" data-cfasync="false">%s</script>',
			$script // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	public function enqueue_editor_js(): void {
		wp_enqueue_script(
			self::HANDLE_EDITOR,
			$this->asset_url( 'color-switcher-for-elementor-editor.js' ),
			array(),
			$this->asset_version( 'color-switcher-for-elementor-editor.js' ),
			true
		);
	}

	/**
	 * Panel-side CSS for the injected Alt swatch. Small enough to inline —
	 * no separate editor stylesheet build.
	 */
	public function enqueue_editor_panel_css(): void {
		$css = '
			.elementor-repeater-row-controls .elementor-control-color_alt { order: 10; flex: 0 0 auto; width: auto; margin-left: 3px; }
			.elementor-control-color_alt .e-global-colors__color-value { display: none; }
			.elementor-control-color_alt .elementor-control-title { display: none; }
			.elementor-control-color_alt .elementor-control-input-wrapper { width: auto; min-width: 0; }
			.elementor-repeater-row-controls .elementor-control-title .elementor-control-input-wrapper { min-width: 60px; }
			.elementor-control-color_alt .pickr { margin: 0; position: relative; }
			.elementor-control-color_alt .pickr::after {
				content: "";
				position: absolute;
				inset: 0;
				padding: 1px;
				background: conic-gradient(from 0deg, #ff1b6b, #7928ca, #45caff, #ffd700, #ff1b6b);
				-webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
				-webkit-mask-composite: xor;
				mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
				mask-composite: exclude;
				pointer-events: none;
			}
			.elementor-control-arts_cs_viewport { padding-bottom: 36px; }
			/* Declared on the elements themselves, not on a control-root class:
			   Elementor composes that root as elementor-control-{NAME}, and only
			   suffixes the TYPE onto elementor-control-type-{type} — so scoping
			   this to the type name silently matched nothing and every var()
			   below resolved to an invalid value. */
			.arts-cs-alt__choose, .arts-cs-alt__remove {
				--arts-cs-alt-gradient: conic-gradient(from 0deg, #ff1b6b, #7928ca, #45caff, #ffd700, #ff1b6b);
			}
			/* Only the choose tool needs a containing block for its border
			   overlay. The remove button already has one — it borrows the stock
			   remove rule, which is position:absolute — and declaring `relative`
			   there outranked it (same specificity, later stylesheet), dropping
			   the button back into flow, pushing the preview down and carrying
			   its own offset sideways out of view. */
			.arts-cs-alt__choose { position: relative; }
			.arts-cs-alt__choose::after, .arts-cs-alt__remove::after {
				content: "";
				position: absolute;
				inset: 0;
				padding: 1px;
				border-radius: inherit;
				background: var(--arts-cs-alt-gradient);
				-webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
				-webkit-mask-composite: xor;
				mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
				mask-composite: exclude;
				pointer-events: none;
			}
			/* Only the set state is marked. The label states it too, so a second
			   dot for "unset" would be saying nothing the words do not. */
			.arts-cs-alt__choose_set::before {
				content: "";
				flex: 0 0 auto;
				width: 6px;
				height: 6px;
				margin-inline-end: 6px;
				border-radius: 50%;
				background: var(--e-a-color-success, #17b26a);
			}
			.arts-cs-alt__remove { inset-inline-end: 38px; }
			.arts-cs-alt__remove::after { border-radius: var(--e-border-radius, 3px); }
		';

		wp_add_inline_style( 'elementor-editor', $css );
	}

	private function asset_url( string $file ): string {
		return untrailingslashit( $this->plugin_dir_url ) . '/libraries/color-switcher-for-elementor/' . $file;
	}

	/** filemtime suffix busts browser/proxy caches on every bundle change (dev syncs + plugin updates alike). */
	private function asset_version( string $file ): string {
		$mtime = filemtime( $this->plugin_dir_path . 'libraries/color-switcher-for-elementor/' . $file );

		return ARTS_COLOR_SWITCHER_PLUGIN_VERSION . '.' . ( false !== $mtime ? (string) $mtime : '0' );
	}
}
