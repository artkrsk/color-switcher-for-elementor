# Arts Color Switcher for Elementor

[![Tests](https://img.shields.io/github/actions/workflow/status/artkrsk/color-switcher-for-elementor/test.yml?style=flat-square&logo=githubactions&logoColor=white&label=tests)](https://github.com/artkrsk/color-switcher-for-elementor/actions/workflows/test.yml)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759b?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
<!-- Once live on wp.org, add the self-updating listing badges:
[![Version](https://img.shields.io/wordpress/plugin/v/color-switcher-for-elementor?style=flat-square)](https://wordpress.org/plugins/color-switcher-for-elementor/)
[![Installs](https://img.shields.io/wordpress/plugin/installs/color-switcher-for-elementor?style=flat-square)](https://wordpress.org/plugins/color-switcher-for-elementor/)
[![Rating](https://img.shields.io/wordpress/plugin/rating/color-switcher-for-elementor?style=flat-square)](https://wordpress.org/plugins/color-switcher-for-elementor/reviews/)
And the Codecov badge once coverage upload is enabled. -->

Arts Color Switcher for Elementor — part of the free plugin collection at [artemsemkin.com/plugins/color-switcher-for-elementor/](https://artemsemkin.com/plugins/color-switcher-for-elementor/).

## Install

From [WordPress.org](https://wordpress.org/plugins/color-switcher-for-elementor/), or grab the zip from [Releases](https://github.com/artkrsk/color-switcher-for-elementor/releases).

Requires WordPress 6.0+, PHP 8.0+, and [Elementor](https://wordpress.org/plugins/elementor/) 3.25+.

## Alt images

Raster images ride the same switch as the colors. Every image `MEDIA` control on the site is re-typed to `arts_cs_media`, which grows a "Choose Alt Image" button inside the stock control — the Alt file is stored as extra sub-keys of that control's own value and emitted through Elementor's `selectors`, so nothing filters HTML and nothing touches the DOM at runtime.

A control that already declares `{{URL}}` (a background image, a video fallback) has that declaration mirrored under `html[data-arts-cs="alt"]`; one that declares nothing prints an `<img>`, so it gets `content: url(...)` instead. Single-image widgets and background images are the scope — galleries and carousels hold many images behind one control and are deliberately out.

This adds **nothing** to the table below: no new global, no new event, no new attribute. The contract stays at 1.

## Integration contract

Contract version: **1** (bumped on breaking changes to this table, independently of semver; `window.ArtsColorSwitcher.contract` reports the running build's version). Everything below is a stable public surface for themes and plugins — dark-mode toggles, AJAX routers, anything reacting to theme state:

| Surface | Where | Meaning |
|---|---|---|
| `data-arts-cs` | attribute on `<html>` | Current binary theme: `"alt"` while the Alt palette is active, absent otherwise. Server-rendered for the page baseline (no flash), owned by the runtime after boot. |
| `data-arts-cs-baseline` | attribute on the Elementor document wrapper | The page's authored baseline (`auto`/`default`/`alt`) — what the runtime re-reads after an AJAX container swap. `auto` means the author deferred to the visitor's device, and is why this carries three values rather than a theme: "follow the device" and "light for everyone" are different instructions. |
| `data-arts-cs-pref` | attribute on `<html>` | The visitor's stored choice (`system`/`default`/`alt`), absent when nothing is stored. Mirrors the cookie, written before first paint by the head script, so a control's active state can be styled without waiting for scripts. |
| `--arts-cs-p` | custom property on `<body>` | Live interpolation progress `0..1` between Default and Alt, including mid-scrub values. |
| `--arts-cs-duration` / `--arts-cs-ease` | custom properties on `body` | Morph timing. Defaults (`0.4s` / `ease`) live in the stylesheet as fallbacks; set the vars in your own CSS to override. |
| `arts-cs:change` | CustomEvent on `document` | Fired after every applied state change; `detail: { theme, source }` with source `baseline`/`zone`/`api`. |
| `arts-cs-morphing` | class on `<html>` | Present for the duration of a coordinator-driven morph. |
| `window.ArtsColorSwitcher` | global | `contract`, `refresh()`, `set(theme)`, `setPreference(pref)`, `getPreference()`, `getTheme()`, `getProgress()`, `destroy()`. `set()` changes the runtime baseline and persists nothing. A zone in view shows the opposite of the baseline, so it inverts along with a `set()` call rather than ignoring it. |
| `arts_cs_pref` | cookie | The visitor's stored choice: `system`, `default` or `alt`. Written only by `setPreference()` and by the plugin's own controls; its presence is what makes a visitor's choice outrank both the page's authored baseline and the visitor's own device. Deleting it hands authority back down that chain — to the page's author where they pinned a palette, to the device where they left the page on `auto`. |
| `[arts_color_switcher_toggle]` | shortcode | Renders any skin outside Elementor: `skin` (`icon`/`switch`/`buttons`/`dropdown`), `mode` (`binary`/`cycle` — every skin but the Switch, which has no third position and is always binary), `caption`, `name`, `label_system`, `label_default`, `label_alt` (empty prints no word, including an empty `<option>` in the Dropdown, but never drops the option — how many states a control offers is `mode` alone), and for the Buttons skin `style` (`joined`/`separate`) and `icons` (`yes`/`no`). |

## Development

```bash
git clone https://github.com/artkrsk/color-switcher-for-elementor.git
cd color-switcher-for-elementor
pnpm install && composer install
cp .env.example .env   # set DEV_TARGET to your Local site's plugin dir
```

| Command | What |
|---|---|
| `pnpm dev:plugin` | watch-compile + mirror the plugin to `DEV_TARGET` |
| `pnpm build` | release build into `dist/` |
| `pnpm test` / `pnpm test:coverage` | Vitest |
| `pnpm release <patch\|minor\|major>` | bump, stamp, validate changelog, commit, tag |

Everything else (lint, typecheck, phpstan, phpcs, knip, fallow, blueprint, doctor) runs via `pnpm exec` — see the [tooling docs](https://github.com/artkrsk/wp-plugin-tooling).

## Release

Hand-write the readme.txt changelog entry, `pnpm release patch`, push the tag. CI validates and ships.

## License

GPL-3.0-or-later.
