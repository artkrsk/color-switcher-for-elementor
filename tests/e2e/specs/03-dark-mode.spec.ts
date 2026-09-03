import { expect, test } from '@playwright/test'

/**
 * The visitor's own layer. Authority runs visitor, then author, then device:
 * the PRESENCE of a cookie transfers authority from the page's author to the
 * visitor, and a page whose author never chose defers to the device.
 *
 * These tests run under Playwright's default light colour scheme, so an
 * unpinned page resolves to the default palette exactly as it did when the
 * author's baseline won outright. The dark-scheme block at the bottom is what
 * covers the other half.
 *
 * The demo page authors no baseline, so it renders light here; the seeded
 * toggle page's first control is the shortcode-rendered one, so `.first()`
 * clicks exercise the path with no element_ready hook behind it.
 */

const DEMO = '/color-switcher-demo/'
const TOGGLE = '/color-switcher-toggle/'
const PINNED_LIGHT = '/color-switcher-pinned-light/'

const theme = async (page: import('@playwright/test').Page): Promise<string> =>
  page.evaluate(() => window.ArtsColorSwitcher?.getTheme() ?? 'missing')

const preference = async (page: import('@playwright/test').Page): Promise<string> =>
  page.evaluate(() => window.ArtsColorSwitcher?.getPreference() ?? 'missing')

test.describe('dark mode', () => {
  test('the toggle stores a choice that survives navigation', async ({ page }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    expect(await theme(page)).toBe('default')
    expect(await preference(page)).toBe('system')

    await page.locator('.js-arts-cs-toggle').first().click()
    await page.waitForTimeout(300)

    expect(await theme(page)).toBe('alt')
    expect(await preference(page)).toBe('alt')

    // A different page, and the choice comes with the visitor.
    await page.goto(DEMO)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)
    expect(await theme(page)).toBe('alt')
  })

  /**
   * The head script is the whole point of the cache-safe split: it corrects
   * the attribute before anything paints, so a stored choice never flashes
   * the other palette even on a page the server rendered light.
   */
  test('a stored choice is applied before first paint', async ({ page }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)
    await page.locator('.js-arts-cs-toggle').first().click()
    await page.waitForTimeout(300)

    // Read the attribute as early as the document allows on the next load.
    await page.goto(DEMO, { waitUntil: 'commit' })
    const atFirstOpportunity = await page.evaluate(() =>
      document.documentElement.getAttribute('data-arts-cs')
    )

    expect(atFirstOpportunity).toBe('alt')
  })

  /**
   * The two-state pattern the whole debate turns on: pressing once pins a
   * choice, pressing again releases it and the visitor is back exactly where
   * they started. Storing `system` on release would be a third state the
   * control cannot show. Where "back" lands depends on the page — the
   * author's palette where one is pinned, the device's where none is — and
   * under this file's light scheme both are the default palette.
   */
  test('a two-state control releases the choice it pinned', async ({ page }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    const toggle = page.locator('.js-arts-cs-toggle').first()

    await toggle.click()
    await page.waitForTimeout(300)
    expect(await theme(page)).toBe('alt')

    await toggle.click()
    await page.waitForTimeout(300)

    expect(await theme(page)).toBe('default')
    expect(await preference(page)).toBe('system')
    expect(await page.evaluate(() => document.cookie.includes('arts_cs_pref='))).toBe(false)
  })

  /**
   * Which option looks active is decided in CSS off an attribute the head
   * script stamps, not by a script that runs after Elementor boots — so a
   * returning visitor's control is already right in the first painted frame
   * rather than flicking into place.
   */
  test('a returning visitor sees the pinned option before scripts run', async ({ page }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)
    await page.locator('.js-arts-cs-toggle').first().click()
    await page.waitForTimeout(300)

    await page.goto(TOGGLE, { waitUntil: 'commit' })

    expect(
      await page.evaluate(() => document.documentElement.getAttribute('data-arts-cs-pref'))
    ).toBe('alt')
  })

  /**
   * Two options, three states: with nothing stored NEITHER is lit, because
   * what the visitor is seeing was not chosen by them — it is the author's
   * design, or their own device. Pressing the lit one returns to exactly that.
   */
  test('two buttons can show that nothing has been chosen', async ({ page }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    const group = page.locator('[data-arts-cs-toggle="buttons"][data-arts-cs-mode="binary"]')
    const dark = group.locator('[data-arts-cs-set="alt"]')
    const pressed = () =>
      group.evaluate((element) => element.querySelectorAll('[aria-pressed="true"]').length)

    expect(await pressed()).toBe(0)

    await dark.click()
    await page.waitForTimeout(300)
    expect(await pressed()).toBe(1)
    expect(await theme(page)).toBe('alt')

    await dark.click()
    await page.waitForTimeout(300)
    expect(await pressed()).toBe(0)
    expect(await theme(page)).toBe('default')
  })

  /**
   * Joined is one track with a mark that travels to the chosen option — the
   * whole distinction from Separate, which is a row of independent buttons
   * with nothing to slide within. The geometry is derived from the track's
   * padding, its gap and its option count, so this holds the derivation:
   * equal columns, a mark that is exactly one of them, and the same inset at
   * both ends of the journey.
   */
  test('the joined layout slides one column to the chosen option', async ({ page }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    const joined = page.locator('.arts-cs-toggle_joined')
    const measure = () =>
      joined.evaluate((element) => {
        const mark = element.querySelector('.arts-cs-toggle__knob') as HTMLElement
        const track = element.getBoundingClientRect()
        const box = mark.getBoundingClientRect()

        return {
          left: Math.round(box.left - track.left),
          right: Math.round(track.right - box.right),
          width: Math.round(box.width),
          columns: [...element.querySelectorAll('.arts-cs-toggle__option')].map((option) =>
            Math.round(option.getBoundingClientRect().width)
          )
        }
      })

    const first = await measure()

    // Equal columns whatever the words are, or one column of travel would
    // stop matching the columns it travels between.
    expect(new Set(first.columns).size).toBe(1)
    expect(first.width).toBe(first.columns[0])

    await joined.locator('[data-arts-cs-set="alt"]').click()
    await page.waitForTimeout(700)

    const last = await measure()

    // Three options, so the last one ends inset from its edge by exactly what
    // the first was inset from the other.
    expect(last.right).toBe(first.left)
    expect(last.left).toBeGreaterThan(first.left + first.width)

    // The layout with no track has nothing to move.
    expect(
      await page
        .locator('.arts-cs-toggle_separate')
        .evaluate((element) => element.querySelector('.arts-cs-toggle__knob'))
    ).toBeNull()
  })

  /** CSS cannot state a select's value, so this is the one skin JS corrects. */
  test('the dropdown shows the stored choice', async ({ page, browserName }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    const select = page.locator('.arts-cs-toggle__select')

    // The built stylesheet ships the customizable-select opt-in; Chromium is
    // the one tier that resolves it — Firefox dropping the declaration IS the
    // fallback, so the pin is Chromium-only.
    if (browserName === 'chromium') {
      expect(await select.evaluate((el) => getComputedStyle(el).appearance)).toBe('base-select')
    }

    await select.selectOption('alt')
    await page.waitForTimeout(300)
    expect(await theme(page)).toBe('alt')

    await page.reload()
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)
    await expect(select).toHaveValue('alt')
  })

  /** No cookie means the visitor has expressed nothing: the author decides. */
  test('an unchosen visitor gets the page as authored', async ({ page }) => {
    await page.goto(DEMO)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    expect(await preference(page)).toBe('system')
    expect(await theme(page)).toBe('default')
  })

  test('zones still run under a stored preference', async ({ page }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)
    await page.locator('.js-arts-cs-toggle').first().click()
    await page.waitForTimeout(300)

    await page.goto(DEMO)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)
    expect(await theme(page)).toBe('alt')

    // A marked section shows the opposite of whatever the page is set to, so
    // scroll storytelling reads correctly in either mode.
    await page.evaluate(() => {
      const marked = document.querySelector('[data-id="acsalt"]') as HTMLElement

      window.scrollTo({
        top: marked.getBoundingClientRect().top + window.scrollY,
        behavior: 'instant'
      })
    })
    await page.waitForTimeout(700)

    expect(await theme(page)).toBe('default')
  })
})

/**
 * Themes style bare <button> aggressively — Hello Elementor's reset paints
 * them #c36 with a filled hover, and `button:hover` (0,1,1) outranks a
 * single class. The doubled-class reset exists to win that fight; this holds
 * it in place against whatever theme the suite runs under.
 */
test('the toggle resists the theme button chrome, hovered included', async ({ page }) => {
  await page.goto(TOGGLE)
  await page.waitForFunction(() => !!window.ArtsColorSwitcher)

  const button = page.locator('.js-arts-cs-toggle').first()
  const styles = () =>
    button.evaluate((element) => {
      const computed = getComputedStyle(element)

      return {
        background: computed.backgroundColor,
        radius: computed.borderRadius,
        border: computed.borderStyle
      }
    })

  expect(await styles()).toEqual({
    background: 'rgba(0, 0, 0, 0)',
    radius: '0px',
    border: 'none'
  })

  await button.hover()
  await page.waitForTimeout(400)
  expect((await styles()).background).toBe('rgba(0, 0, 0, 0)')
})

/**
 * An inline-level button sits on the text baseline, so its line box reserves
 * descender space beneath it — height nobody set, under a widget whose
 * spacing controls are all zero.
 */
test('the toggle adds no height of its own', async ({ page }) => {
  await page.goto(TOGGLE)
  await page.waitForFunction(() => !!window.ArtsColorSwitcher)

  const spacing = (selector: string) =>
    page
      .locator(selector)
      .first()
      .evaluate((button) => {
        const container = button.closest('.elementor-widget-container') ?? button.parentElement
        const b = button.getBoundingClientRect()
        const c = (container as HTMLElement).getBoundingClientRect()

        return { above: Math.round(b.top - c.top), below: Math.round(c.bottom - b.bottom) }
      })

  // The widget renders the toggle alone, where the container's text strut is
  // pure leftover height — the 6px of "padding" nobody set.
  expect(await spacing('.elementor-widget-arts-color-switcher-toggle .js-arts-cs-toggle')).toEqual({
    above: 0,
    below: 0
  })

  // The shortcode is meant to sit inline beside menu or footer text, so it
  // keeps the host's line-height rather than flattening it. Deliberately
  // different, and asserted so nobody "fixes" it into the widget's rule.
  const inline = await spacing('.elementor-widget-shortcode .js-arts-cs-toggle')
  expect(inline.above + inline.below).toBeGreaterThan(0)
})

/**
 * The three-state cycle has to SHOW which state it is in. `system` on a light
 * OS and an explicit Default resolve to the same palette, so an icon keyed to
 * the theme would make them identical and the button would cycle blind.
 */
test('each of the three states looks different', async ({ page }) => {
  await page.goto(TOGGLE)
  await page.waitForFunction(() => !!window.ArtsColorSwitcher)

  const cycle = page.locator('[data-arts-cs-toggle="icon"][data-arts-cs-mode="cycle"]')
  const visibleIcon = () =>
    cycle.evaluate((element) => {
      const shown = [...element.querySelectorAll('.arts-cs-toggle__icon')].filter(
        (icon) => getComputedStyle(icon).display !== 'none'
      )

      return {
        count: shown.length,
        which: shown[0]?.className.replace(/.*icon_/, '') ?? 'none',
        label: element.getAttribute('aria-label')
      }
    })

  const system = await visibleIcon()
  expect(system).toMatchObject({ count: 1, which: 'system' })

  await cycle.click()
  const asDefault = await visibleIcon()
  expect(asDefault).toMatchObject({ count: 1, which: 'default' })

  await cycle.click()
  const asAlt = await visibleIcon()
  expect(asAlt).toMatchObject({ count: 1, which: 'alt' })

  // The whole point: system and an explicit Default resolve to the same
  // colors, and must still be told apart.
  expect(system.which).not.toBe(asDefault.which)
  expect(system.label).not.toBe(asDefault.label)

  await cycle.click()
  expect(await visibleIcon()).toMatchObject({ which: 'system' })
})

/**
 * `system` is a state the visitor holds, not a one-off starting position — and
 * a pinned page is the only place that distinction is still visible.
 *
 * On an unpinned page a stored `system` and no cookie at all now resolve the
 * same way, because both mean "ask the device". Where the author pinned a
 * palette they part company: no cookie leaves the author in charge, while
 * choosing `system` is the visitor overruling them, and the device decides
 * from there.
 */
test.describe('system preference', () => {
  test.use({ colorScheme: 'dark' })

  test('a stored system choice outranks a page the author pinned', async ({ page }) => {
    await page.goto(PINNED_LIGHT)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    // Nothing stored, so the author's pin stands even on a dark device.
    expect(await theme(page)).toBe('default')
    expect(await preference(page)).toBe('system')
    expect(await page.evaluate(() => document.cookie.includes('arts_cs_pref='))).toBe(false)

    await page.evaluate(() => window.ArtsColorSwitcher?.setPreference('system'))
    await page.waitForTimeout(300)

    expect(await theme(page)).toBe('alt')
    expect(await preference(page)).toBe('system')
    expect(await page.evaluate(() => document.cookie.includes('arts_cs_pref=system'))).toBe(true)
  })
})

/**
 * The half of the precedence table a light-scheme browser can never reach.
 *
 * Everything above runs under Playwright's default light scheme, where an
 * unpinned page resolves to the default palette and so looks identical to the
 * pre-1.0 rule where the author's baseline won outright. Only a dark device
 * tells the two apart — and only a real browser proves the pre-paint script
 * and the boot-time resolver agree, since they are written in different
 * languages against different inputs and nothing imports across that gap.
 */
test.describe('a visitor whose device asks for dark', () => {
  test.use({ colorScheme: 'dark' })

  /**
   * The change itself. An author who never chose is deferring to the device,
   * which is the only party that knows this visitor's real sunrise and sunset.
   */
  test('opens an unpinned page in the alt palette', async ({ page }) => {
    await page.goto(DEMO)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    expect(await theme(page)).toBe('alt')
    expect(await preference(page)).toBe('system')
    expect(await page.evaluate(() => document.cookie.includes('arts_cs_pref='))).toBe(false)
  })

  /** And does it before anything paints, or it is a flash rather than a feature. */
  test('resolves the device before first paint', async ({ page }) => {
    await page.goto(DEMO, { waitUntil: 'commit' })

    expect(await page.evaluate(() => document.documentElement.getAttribute('data-arts-cs'))).toBe(
      'alt'
    )
  })

  /**
   * The safety property, and the reason "Page opens in" carries three values
   * rather than two: an author who pinned a palette outranks the device, so a
   * site deliberately designed in one palette keeps it for every visitor.
   */
  test('leaves a page the author pinned alone', async ({ page }) => {
    await page.goto(PINNED_LIGHT, { waitUntil: 'commit' })

    expect(
      await page.evaluate(() => document.documentElement.getAttribute('data-arts-cs'))
    ).toBeNull()

    await page.waitForFunction(() => !!window.ArtsColorSwitcher)
    expect(await theme(page)).toBe('default')
  })

  /** A stored choice still outranks everything, device included. */
  test('still lets a stored choice win', async ({ page }) => {
    await page.goto(DEMO)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)
    await page.evaluate(() => window.ArtsColorSwitcher?.setPreference('default'))
    await page.waitForTimeout(300)

    expect(await theme(page)).toBe('default')

    await page.goto(DEMO, { waitUntil: 'commit' })
    expect(
      await page.evaluate(() => document.documentElement.getAttribute('data-arts-cs'))
    ).toBeNull()
  })

  /**
   * Releasing hands authority back down the chain, and on an unpinned page the
   * next holder is the device — not the light palette the visitor never asked
   * for. This is the assertion that would have caught the old rule.
   */
  test('returns to the device when a two-state control is released', async ({ page }) => {
    await page.goto(TOGGLE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    const toggle = page.locator('.js-arts-cs-toggle').first()

    await toggle.click()
    await page.waitForTimeout(300)
    expect(await theme(page)).toBe('default')

    await toggle.click()
    await page.waitForTimeout(300)

    expect(await theme(page)).toBe('alt')
    expect(await page.evaluate(() => document.cookie.includes('arts_cs_pref='))).toBe(false)
  })
})
