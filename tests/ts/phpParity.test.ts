import { readdirSync, readFileSync } from 'node:fs'
import {
  ALT_KEY_ID,
  ALT_KEY_URL,
  ALT_SETTING_LABELS,
  ATTR_BASELINE,
  ATTR_PREFERENCE,
  ATTR_STATE,
  CLASS_MORPHING,
  CONTROL_TYPE_ALT_MEDIA,
  COOKIE_PREFERENCE,
  SKINS,
  VAR_DURATION,
  VAR_PROGRESS
} from '@ts/constants'
import { describe, expect, it } from 'vitest'

/**
 * The cross-language invariants nothing else can catch. Three languages state
 * the same handful of names with no build step between them: PHP registers the
 * controls and renders the attributes, SCSS keys off them, TS reads both.
 * PHPStan is type analysis and cannot see a value drift; the rest of the suite
 * never opens a .php or .scss file.
 *
 * Parsed by regex rather than executed — booting WordPress for a dozen strings
 * would cost the suite a PHP runtime.
 */
const ELEMENTS_PHP = readFileSync('src/php/Managers/Elements.php', 'utf8')
const DOCUMENTS_PHP = readFileSync('src/php/Managers/Documents.php', 'utf8')
const KIT_PHP = readFileSync('src/php/Managers/Kit.php', 'utf8')
const EDITOR_TS = readFileSync('src/ts/editor/index.ts', 'utf8')
const SETTINGS_TS = readFileSync('src/ts/constants/SETTINGS.ts', 'utf8')
const TYPES_TS = readFileSync('src/ts/types/TTheme.ts', 'utf8')
const BOOT_TS = readFileSync('src/ts/boot.ts', 'utf8')
const COORDINATOR_TS = readFileSync('src/ts/core/coordinator.ts', 'utf8')
// The stylesheet is split into per-concern partials; the invariants live in
// whichever partial owns them, so read the folder rather than the entry.
const STYLESHEET = readdirSync('src/styles')
  .map((file) => readFileSync(`src/styles/${file}`, 'utf8'))
  .join('\n')
const ASSETS_PHP = readFileSync('src/php/Managers/Assets.php', 'utf8')
const ALT_MEDIA_PHP = readFileSync('src/php/Controls/AltMedia.php', 'utf8')
const MEDIA_PHP = readFileSync('src/php/Managers/Media.php', 'utf8')
const TOGGLE_PHP = readFileSync('src/php/Managers/Toggle.php', 'utf8')

/** The literal of a `const NAME = 'value';` class constant. */
const phpConst = (source: string, name: string): string => {
  const match = source.match(new RegExp(`const ${name}\\s*=\\s*'([^']+)'`))

  if (!match?.[1]) {
    throw new Error(`no constant ${name} found`)
  }

  return match[1]
}

describe('PHP ↔ TS ↔ SCSS parity', () => {
  /**
   * The cookie is the one piece of state written by TS and read by an inline
   * script PHP composes, with no import between them. A rename on either side
   * would silently stop a visitor's stored choice from ever being applied
   * before paint — the exact bug the pre-paint script exists to prevent.
   */
  it('names the preference cookie identically in PHP and TS', () => {
    expect(phpConst(DOCUMENTS_PHP, 'COOKIE_PREFERENCE')).toBe(COOKIE_PREFERENCE)
  })

  /** And the inline script must actually read the constant, not a copy of it. */
  it('composes the head script from that constant', () => {
    expect(ASSETS_PHP).toContain('Documents::COOKIE_PREFERENCE')
    expect(ASSETS_PHP).toContain('{$cookie}=')
  })

  it('zone setting keys match between the controls and the constants', () => {
    for (const constant of ['CONTROL_ENABLED', 'CONTROL_VIEWPORT']) {
      expect(SETTINGS_TS).toContain(`'${phpConst(ELEMENTS_PHP, constant)}'`)
    }
  })

  /**
   * The enabled control's "on" value is shared three ways: PHP writes it into
   * the rendered payload, boot greps that payload before attaching anything,
   * and the handler compares against it. A rename that missed one would leave
   * the plugin silently inert — so boot composes its matcher out of the
   * constants rather than restating the literal.
   */
  it('the enabled value matches between PHP, the constants and the boot gate', () => {
    const value = phpConst(ELEMENTS_PHP, 'ENABLED_SWITCH')

    expect(SETTINGS_TS).toContain(`'${value}'`)
    expect(BOOT_TS).toContain('SETTING_ENABLED')
    expect(BOOT_TS).toContain('ENABLED_SWITCH')
    // The point of the pair above: boot must never restate the literal.
    expect(BOOT_TS).not.toContain(`'${value}'`)
  })

  it('page theme values match the TS union', () => {
    expect(TYPES_TS).toContain(`'${phpConst(DOCUMENTS_PHP, 'THEME_ALT')}'`)
  })

  it('the page-theme setting key matches between PHP and the constants', () => {
    expect(SETTINGS_TS).toContain(`'${phpConst(DOCUMENTS_PHP, 'CONTROL_PAGE_THEME')}'`)
    // The editor hook consumes it by name rather than restating the literal.
    expect(EDITOR_TS).toContain('SETTING_PAGE_THEME')
  })

  it('PHP renders the state attribute the contract and stylesheet key off', () => {
    expect(DOCUMENTS_PHP).toContain(`${ATTR_STATE}="alt"`)
    expect(STYLESHEET).toContain(`html[${ATTR_STATE}='alt']`)
  })

  it('PHP renders the baseline attribute the runtime re-reads', () => {
    expect(DOCUMENTS_PHP).toContain(`'${ATTR_BASELINE}'`)
  })

  /**
   * The preference attribute is written by the head script, read by the
   * stylesheet to decide which of a control's options looks active, and
   * written again by TS on every change. Three languages, no import between
   * them: a drift on any side would leave a returning visitor's control
   * showing a state they are not in.
   */
  it('names the preference attribute identically in PHP, TS and SCSS', () => {
    expect(phpConst(DOCUMENTS_PHP, 'ATTR_PREFERENCE')).toBe(ATTR_PREFERENCE)
    expect(ASSETS_PHP).toContain('Documents::ATTR_PREFERENCE')
    expect(STYLESHEET).toContain(`html[${ATTR_PREFERENCE}=`)
    expect(STYLESHEET).toContain(`html:not([${ATTR_PREFERENCE}])`)
  })

  /**
   * Elementor suffixes `data-widget_type` with the skin id and fires the ready
   * hook under that name, so a skin PHP registers but TS does not know about
   * would render a control nothing ever attaches to.
   */
  it('skin ids match between the renderer and the constants', () => {
    const ids = ['SKIN_ICON', 'SKIN_SWITCH', 'SKIN_BUTTONS', 'SKIN_DROPDOWN'].map((name) =>
      phpConst(TOGGLE_PHP, name)
    )

    expect([...SKINS]).toEqual(ids)
    // boot builds the hook names from the list rather than restating them.
    expect(BOOT_TS).toContain('SKINS')
  })

  /** Both states of the two-state control, spelled the same on both sides. */
  it('mode values match between the renderer and the markup TS reads', () => {
    const toggle = readFileSync('src/ts/core/toggle.ts', 'utf8')

    expect(toggle).toContain(`'${phpConst(TOGGLE_PHP, 'MODE_CYCLE')}'`)
    expect(STYLESHEET).toContain(`[data-arts-cs-mode='${phpConst(TOGGLE_PHP, 'MODE_CYCLE')}']`)
    expect(STYLESHEET).toContain(`[data-arts-cs-mode='${phpConst(TOGGLE_PHP, 'MODE_BINARY')}']`)
  })

  it('the kit emits the scalar the stylesheet registers', () => {
    expect(KIT_PHP).toContain(`${VAR_PROGRESS},`)
    expect(STYLESHEET).toContain(`@property ${VAR_PROGRESS}`)
  })

  /**
   * Timing is a customization var, not a control: the stylesheet carries the
   * default as the var() fallback, and JS mirrors that same fallback for the
   * morph window. Nothing may emit the var itself — that is the theme
   * author's slot.
   */
  it('duration stays a var with agreeing fallbacks in CSS and JS', () => {
    expect(STYLESHEET).toContain(`var(${VAR_DURATION}, 0.4s)`)
    expect(COORDINATOR_TS).toContain('0.4')
    expect(KIT_PHP).not.toContain(`${VAR_DURATION}:`)
  })

  it('the morph class matches between the contract and the stylesheet', () => {
    expect(STYLESHEET).toContain(`html.${CLASS_MORPHING}`)
  })

  it('the timeline name matches between the driver and the stylesheet', () => {
    const driver = readFileSync('src/ts/core/scrollDriver.ts', 'utf8')
    const match = driver.match(/'view-timeline',\s*'(--[a-z-]+) block'/)

    expect(match?.[1]).toBeTruthy()
    expect(STYLESHEET).toContain(`timeline-scope: ${match?.[1]}`)
    expect(STYLESHEET).toContain(`animation-timeline: ${match?.[1]}`)
  })

  /**
   * The Alt image names, which nothing else pins. Two of them are worse to
   * get wrong than the rest of this file: the value sub-keys are stored
   * INSIDE other widgets' settings, so renaming one after release is a data
   * migration across every document rather than an edit.
   */
  it('the alt control type matches PHP', () => {
    expect(CONTROL_TYPE_ALT_MEDIA).toBe(phpConst(ALT_MEDIA_PHP, 'TYPE'))
  })

  it('both alt value sub-keys match PHP', () => {
    expect(ALT_KEY_URL).toBe(phpConst(ALT_MEDIA_PHP, 'KEY_URL'))
    expect(ALT_KEY_ID).toBe(phpConst(ALT_MEDIA_PHP, 'KEY_ID'))
  })

  /** The panel's words travel as a control arg, so the view can read them. */
  it('the label arg matches PHP', () => {
    expect(ALT_SETTING_LABELS).toBe(phpConst(ALT_MEDIA_PHP, 'SETTING_LABELS'))
  })

  /**
   * The one string CSS and PHP have to agree on: every alt rule is scoped to
   * the same state attribute the stylesheet keys off.
   */
  it('the alt scope uses the state attribute', () => {
    expect(MEDIA_PHP).toContain(`html[${ATTR_STATE}="alt"]`)
  })
})
