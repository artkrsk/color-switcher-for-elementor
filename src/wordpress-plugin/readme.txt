=== Arts Color Switcher for Elementor – Dark Mode & Color Switching on Scroll ===
Contributors: artemsemkin
Donate link: https://buymeacoffee.com/artemsemkin
Tags: dark mode, dark mode toggle, night mode, elementor, scroll effects
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0
GitHub Plugin URI: https://github.com/artkrsk/color-switcher-for-elementor/

Dark mode toggle and scroll-triggered color switching for Elementor. Every Global Color gets an Alt value; the whole page follows – images too.

== Description ==

Scroll into a section and the page changes its colors – background, headings, body text, buttons, borders, icons, all together, like the lights dimming in a room. The same mechanism doubles as a real dark mode for Elementor: drop in the toggle widget and visitors switch between two palettes you designed, not one a filter guessed by inverting your site.

It works by extending something you already use. Every Global Color in Site Settings gets a second "Alt" swatch next to it – the same color picker, one row, no new screen. Fill it in for the colors that should change, mark the sections that should switch on scroll, place the toggle where visitors should find it. And there's no Pro version: this is the whole thing.

Two things to know before you install. This changes what your visitors see – it is not a dark skin for wp-admin. And it turns the colors you linked to Global Colors: that linkage is the whole mechanism, so a hex typed straight into a widget stays where it is. Sites built the way Elementor intends have little to do; sites full of hand-typed colors have some linking to do first.

= Nothing to rebuild =

Your widgets already reference Global Colors. That's the entire integration – the plugin re-points those same colors, so anything linked to them turns automatically, including sections you never touched. A color with no Alt value keeps its original, which means you can adopt this on an existing site one color at a time.

= Change colors on scroll =

A marked section shows the alternative palette while it is on screen, and the page returns to its own palette by itself once the section has passed – nothing has to be marked as "the normal one".

One control decides where that happens: a range over the viewport with two handles. Put both handles together and the switch is instant; move them apart and the colors interpolate continuously with scroll position across that band. The default – both at the top – turns the page once the section has scrolled up to fill the screen, the way it reads in the portfolio themes this effect comes from. A section holds the page for as long as its own height, so sections shorter than the screen want their handles moved down. Whether a section switches at all is per-device, so an effect can run on desktop and stay off on phones.

= Works inside horizontal scroll sections =

A section pinned by Arts Horizontal Scroll travels sideways while its vertical position stands still, so marking one of its panels works the way you would expect. The Viewport control keeps its meaning too – it just reads across the screen instead of down it: how far the panel travels before the colors change.

= A dark mode toggle, four shapes =

Drop the Color Switcher widget anywhere – or use the `[arts_color_switcher_toggle]` shortcode in a menu or footer – and visitors pick a side: an icon, a switch, a row of buttons, or a dropdown. Their choice is remembered, and it travels with them from page to page.

Everything but the switch can offer two states or three, and the third is "follow the system": a visitor who picks it keeps tracking their device's light and dark setting for as long as they leave it there – change the system setting and the site changes with it. Two states can still be undone: press once to pin a palette, press again to release it, and the visitor ends up exactly where they started, which a toggle that can only ever store a choice cannot do.

= Dark pages, light pages, or follow the device =

Each page opens one of three ways, set under Page Settings. Left alone, it follows the visitor's device – the same dark mode setting their phone binds to their own sunrise and sunset. Or you can pin it: Default colors for a page that must stay light for everyone, Alt colors for one that must start dark. A pinned page is rendered server-side, so there is no flash of the wrong colors and it works with JavaScript disabled.

A marked section always shows the opposite of whatever the page starts in, so on a dark page the marked sections are the light interludes. That holds for a visitor whose device turned the page dark too – the contrast survives wherever the page begins.

= Dark mode logos and images =

Color is only half of a dark page. A logo drawn for a light background disappears on a dark one, and no palette can rescue it – that needs a different file.

So every image control grows a second picker. Open the image you already chose and there is a "Choose Alt Image" button beside it: point it at the white version of your logo and that version appears whenever the alternative palette is on. Background images work the same way – a section's, a widget's, and the hover state of either. Nothing moves to a new screen, and an image without an Alt simply keeps what it has.

= Built on modern CSS =

Colors interpolate in the OKLab color space through CSS custom properties, so midpoints look right instead of muddy. Scroll-bound switching uses native CSS scroll-driven animations where the browser supports them – no JavaScript runs per frame – and falls back to a scroll-timeline polyfill in Firefox. The editor shows the real thing: scroll the canvas and the colors turn exactly as they do on the frontend. In browsers with customizable selects (Chrome, Edge), the Dropdown toggle's options open as a styled, animated list you can design from the widget; everywhere else the platform's own picker keeps working.

== Installation ==

1. Install and activate the plugin. The free Elementor plugin is the only requirement.
2. Open Site Settings → Global Colors and fill in the "Alt" swatch next to any color.
3. Edit a page, select a Container, and set "Flip colors in this section" to Yes under Color Switcher.
4. Scroll the canvas – the page turns as the section comes into view.
5. For a dark mode toggle, drop the Color Switcher widget into your header or footer.
6. To swap a logo or a background image too, open its image control and use "Choose Alt Image".

== Frequently Asked Questions ==

= Does it need Elementor Pro? =

No – the free Elementor is enough.

= Does this add a dark mode toggle? =

Yes. Drop the Color Switcher widget anywhere – or the `[arts_color_switcher_toggle]` shortcode in a menu or footer – and pick one of four shapes: an icon, a switch, a row of buttons, or a dropdown. A visitor's choice is remembered across pages, and until they make one the page opens on whatever their device asks for – or on the palette you pinned, if you pinned one.

= How is this different from other dark mode plugins? =

Most dark mode plugins generate the dark version for you: a CSS filter or a script inverts whatever your site happens to be, and you exclude the parts it gets wrong. This plugin generates nothing. You design the second palette yourself, in the same Global Colors picker you designed the first one in, and only what you linked changes. That is more work than a filter on day one, and it is the reason the result looks designed rather than inverted ever after. It is also why the switch is cheap: the colors are computed in CSS, nothing rewrites your HTML, and the same mechanism turns whole sections as visitors scroll – which a filter cannot do at all.

= What happens to colors I didn't set an Alt value for? =

They stay exactly as they are. Partial adoption is expected – set alternatives only for the colors that should change.

= What will not follow the switch? =

Colors that are not Global Colors: a hex typed into a widget, an inline `style` attribute, or a color supplied by a dynamic tag. Embedded content you do not control – YouTube, maps, third-party forms – keeps its own colors, and WordPress itself renders oEmbeds on a white background regardless of your theme. Printing resets the colors to your default palette, because light text on paper does not print; Alt images are not reset, so a page printed while the alternative palette is on keeps its alternative images.

Images do follow, but only where you hand them something to follow with: pick an Alt image on the control and it swaps. Inline SVG drawn with `currentColor` follows for free and needs no second file at all, which is usually the right answer for a single-color logo. Anything that has to re-theme itself in JavaScript can listen for the `arts-cs:change` event.

= Will hardcoded colors switch? =

No. Only colors linked to Elementor's Global Colors follow the switch, which is the same rule that makes the rest of Site Settings work. The same goes for a color supplied by a dynamic tag: Elementor resolves a dynamic tag to a literal value when the page renders, so those behave like a typed-in color rather than a linked one.

= Does it work with my other plugins? =

Anything that takes its colors from Global Colors follows the switch, whoever drew it – a cursor, a lightbox's chrome, a device mockup's frame. That is the whole point of routing through Elementor's own colors instead of inventing a palette.

Two specifics worth knowing. Sections pinned by Arts Horizontal Scroll are supported: mark a panel and the page turns as that panel crosses the screen. And if a plugin re-declares a Global Color on a smaller part of the page – Arts Header for Elementor does this for its sticky bar – that closer declaration wins there, so the pinned bar keeps the color it was given instead of following the page.

= Does it work with Elementor's new editor? =

Not with its new elements yet, and it is worth knowing why before you build a page out of them. Elementor's atomic elements do not read Global Colors at all – they take their colors from a separate list of variables that is stored and named its own way. Alt values live on Global Colors, so an element built the new way has nothing to follow and keeps its colors while the rest of the page turns.

Everything built the way Elementor has always worked is unaffected, and the two can share a page. Elementor still ships the new system as alpha and the way it stores those variables has already changed once, so we would rather wait for it to settle than guess at it and break your site later.

= Can my logo change with dark mode? =

Yes. Every image control gains a "Choose Alt Image" button next to the one you already use – pick the alternative file there and it appears whenever the other palette is on. Background images work the same way, hover states included.

The button shows up once at least one Global Color has an Alt value. Until then there is no second palette for an image to belong to, so there is nothing for the button to do.

Give the alternative the same dimensions as the original. The layout keeps the original's box, so a file with a different shape will be stretched into it.

If your logo is a single-color SVG you may not need this at all: an inline SVG drawn with `currentColor` already follows the palette the way text does.

= Do galleries and carousels swap their images? =

No. Those hold many images behind one control, so there is no way to say which alternative belongs to which. What this covers is single-image widgets – Image, Image Box, the Site Logo widget – and background images.

One place has no Alt picker: the logo in Site Settings → Site Identity. Use the Site Logo widget in your header template instead, which does.

= Can I schedule dark mode for night time? =

No, and not as an oversight. A schedule lives on the server, which has one clock and one timezone; the people reading your site do not. Night in Chicago is the middle of the afternoon in Manila, so a server-side schedule is simply wrong for most of your visitors most of the time.

Their own devices already solve this, and solve it properly: dark mode on a modern phone or laptop follows that person's real sunrise and sunset, wherever they happen to be. A visitor who picks "System" on the toggle gets exactly that.

= I styled the toggle with my own CSS. Will an update break it? =

Not if you style it through the documented hooks. Every skin's root carries the class `js-arts-cs-toggle` and a `data-arts-cs-toggle` attribute naming the skin, and `<html>` carries `data-arts-cs` for the current palette and `data-arts-cs-pref` for the visitor's stored choice. Those names are fixed and will not change. The inner markup is not a promise.

= Does it work behind a page cache? =

Yes. A visitor's palette choice lives in a cookie, and a small inline script applies it to `<html>` before first paint. The cached HTML is identical for every visitor – Cloudflare, object caching, whatever you run – and each visitor still sees their own choice with no flash of the wrong colors.

= Can several sections switch on one page? =

Yes – mark as many as you like. Each one turns the page while it is on screen and hands the colors back when it leaves. To keep a run of sections switched together, mark the container that holds them instead of each one. Sections that spread their change across a scroll distance take turns: only one can be doing so at any moment, so if two of them are on screen together the later one in the page decides.

== Screenshots ==

1. Every Elementor Global Color gains an Alt swatch in Site Settings – the second palette dark mode and the scroll effect both switch to.
2. Dark mode logos and images: each image control gains an Alt picker, background images and hover states included.
3. Mark any section to switch colors on scroll. The Viewport range decides where – handles together for an instant flip, apart to interpolate with scroll position.
4. The Color Switcher widget is a dark mode toggle in four shapes: an icon, a switch, a row of buttons, or a dropdown.
5. Style the toggle in the panel: layout, two states or three, labels, icons, and colors you link to your Global Colors.

== Changelog ==

= 1.0.0 =
Initial release.
