import { expect, test } from '@playwright/test'

/**
 * The two things the plugin exists to do, in a real browser: a marked section
 * turns the whole page while it is in view, and a scroll-bound one scrubs the
 * same switch continuously. Identical assertions on both projects — chromium
 * runs the native CSS tier, firefox the polyfilled WAAPI one, and neither is
 * allowed to behave differently.
 */

const DEMO = '/color-switcher-demo/'

/** The scalar the entire color system is driven by. */
const progress = async (page: import('@playwright/test').Page): Promise<number> =>
  page.evaluate(() => window.ArtsColorSwitcher?.getProgress() ?? -1)

const theme = async (page: import('@playwright/test').Page): Promise<string> =>
  page.evaluate(() => window.ArtsColorSwitcher?.getTheme() ?? 'missing')

const scrollTo = async (page: import('@playwright/test').Page, y: number): Promise<void> => {
  await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y)
  await page.waitForTimeout(700)
}

const sectionHeight = async (page: import('@playwright/test').Page, id: string): Promise<number> =>
  page.evaluate(
    (selector) => document.querySelector(selector)?.getBoundingClientRect().height ?? 0,
    `[data-id="${id}"]`
  )

/** Document-space top of a section, so scrolling targets are stable. */
const sectionTop = async (page: import('@playwright/test').Page, id: string): Promise<number> =>
  page.evaluate((selector) => {
    const element = document.querySelector(selector)

    return element ? element.getBoundingClientRect().top + window.scrollY : 0
  }, `[data-id="${id}"]`)

/**
 * How far the Switch skin's knob has travelled, 0..1. The knob rides the
 * scalar directly, so its translation over its full travel (one column plus
 * one gap) is the same number the color system is showing.
 */
const knobProgress = async (page: import('@playwright/test').Page): Promise<number> =>
  page.evaluate(() => {
    const track = document.querySelector(
      '.arts-cs-toggle_switch .arts-cs-toggle__track'
    ) as HTMLElement
    const knob = track.querySelector('.arts-cs-toggle__knob') as HTMLElement
    const transform = getComputedStyle(knob).transform
    const shift = 'none' === transform ? 0 : new DOMMatrixReadOnly(transform).m41
    const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0

    return shift / (knob.offsetWidth + gap)
  })

test.beforeEach(async ({ page }) => {
  await page.goto(DEMO)
  await page.waitForFunction(() => !!window.ArtsColorSwitcher)
})

test('exposes the documented contract', async ({ page }) => {
  const api = await page.evaluate(() => ({
    contract: window.ArtsColorSwitcher?.contract,
    methods: [
      typeof window.ArtsColorSwitcher?.refresh,
      typeof window.ArtsColorSwitcher?.set,
      typeof window.ArtsColorSwitcher?.getTheme,
      typeof window.ArtsColorSwitcher?.getProgress,
      typeof window.ArtsColorSwitcher?.destroy
    ]
  }))

  expect(api.contract).toBe(1)
  expect(api.methods).toEqual(['function', 'function', 'function', 'function', 'function'])
})

test('a marked section turns the whole page and restores on the way out', async ({ page }) => {
  expect(await theme(page)).toBe('default')

  const events: string[] = []
  await page.exposeFunction('recordChange', (detail: string) => {
    events.push(detail)
  })
  await page.evaluate(() => {
    document.addEventListener('arts-cs:change', (event) => {
      const detail = (event as CustomEvent<{ theme: string; source: string }>).detail
      void (window as unknown as { recordChange(payload: string): void }).recordChange(
        `${detail.theme}:${detail.source}`
      )
    })
  })

  // The intro section's own background is a Global Color, so the page-wide
  // switch is visible on an element that never opted into anything.
  const introBefore = await page
    .locator('[data-id="acsintro"]')
    .evaluate((element) => getComputedStyle(element).backgroundColor)

  await scrollTo(page, await sectionTop(page, 'acsalt'))

  expect(await theme(page)).toBe('alt')
  await expect(page.locator('html')).toHaveAttribute('data-arts-cs', 'alt')

  const introDuring = await page
    .locator('[data-id="acsintro"]')
    .evaluate((element) => getComputedStyle(element).backgroundColor)
  expect(introDuring).not.toBe(introBefore)

  // The behavior the interval model exists for: the NEXT section marks
  // nothing, and the page returns on its own — no section has to opt into
  // being "the default one".
  await scrollTo(page, await sectionTop(page, 'acsmid'))

  expect(await theme(page)).toBe('default')
  expect(await page.locator('html').getAttribute('data-arts-cs')).toBeNull()

  await scrollTo(page, 0)

  expect(await theme(page)).toBe('default')
  expect(events).toEqual(['alt:zone', 'default:zone'])
})

/**
 * The demo's scroll-bound section keeps its handles apart (lower at 50%), so
 * the change follows scroll across the lower half of the screen instead of
 * happening at a line. The section before it is unmarked, so the scrub starts
 * from the page baseline and runs Default → Alt.
 */
test('a scroll-bound section scrubs the scalar continuously', async ({ page }) => {
  const top = await sectionTop(page, 'acsscrub')
  const height = await sectionHeight(page, 'acsscrub')
  const viewport = page.viewportSize()?.height ?? 800
  // The lower handle at 50% puts the trigger line mid-viewport; the zone
  // becomes active when the section top reaches it.
  const line = viewport * 0.5
  const entry = top - line

  await scrollTo(page, entry - 50)
  expect(await progress(page)).toBeLessThan(0.1)

  await scrollTo(page, entry + viewport * 0.25)
  const atHalf = await progress(page)

  await scrollTo(page, entry + viewport * 0.5)
  const atEnd = await progress(page)

  expect(atHalf).toBeGreaterThan(0.3)
  expect(atHalf).toBeLessThan(0.7)
  expect(atEnd).toBeGreaterThan(0.9)

  // Reversible — the scrub follows scroll position, not events.
  await scrollTo(page, entry + viewport * 0.25)
  expect(Math.abs((await progress(page)) - atHalf)).toBeLessThan(0.1)

  // And it leaves the way an instant zone does: the held value is handed back
  // to the cascade, which eases it home rather than cutting.
  await scrollTo(page, top + height - line + 30)
  expect(await progress(page)).toBeLessThan(0.1)
  expect(await theme(page)).toBe('default')
})

/**
 * The Switch knob rides --arts-cs-p rather than the html attribute, so it
 * arrives with the colors in an instant zone AND tracks a scrub the attribute
 * never flips for — the state a theme-keyed knob used to freeze through.
 */
test('the Switch knob rides the scalar through zones and scrubs', async ({ page }) => {
  expect(await knobProgress(page)).toBeLessThan(0.05)

  await scrollTo(page, await sectionTop(page, 'acsalt'))
  expect(await knobProgress(page)).toBeGreaterThan(0.95)

  // Mid-scrub: the theme attribute deliberately stays put (flipping it would
  // snap the scalar), yet the knob sits partway — the whole point.
  const top = await sectionTop(page, 'acsscrub')
  const viewport = page.viewportSize()?.height ?? 800
  const entry = top - viewport * 0.5

  await scrollTo(page, entry + viewport * 0.25)

  const mid = await knobProgress(page)
  expect(mid).toBeGreaterThan(0.2)
  expect(mid).toBeLessThan(0.8)
  expect(await theme(page)).toBe('default')

  await scrollTo(page, 0)
  expect(await knobProgress(page)).toBeLessThan(0.05)
})

/**
 * The Switch's icon paint is a scalar-driven blend of the author's Normal and
 * Active colours, never a selector on the html attribute — which a scrub
 * deliberately never flips, so attribute-keyed paint lit the wrong icon for
 * the whole band a scrub held.
 */
test('the Switch icon paint blends with the scalar', async ({ page }) => {
  const iconColor = (variant: string): Promise<string> =>
    page.evaluate(
      (selector) => getComputedStyle(document.querySelector(selector) as HTMLElement).color,
      `.arts-cs-toggle_switch .arts-cs-toggle__icon_${variant}`
    )

  const altAtRest = await iconColor('alt')
  const defaultAtRest = await iconColor('default')

  // Inside the instant zone (p = 1) the alt icon carries the Active colour
  // and the default icon has handed it back.
  await scrollTo(page, await sectionTop(page, 'acsalt'))
  const altSwitched = await iconColor('alt')
  const defaultSwitched = await iconColor('default')
  expect(altSwitched).not.toBe(altAtRest)
  expect(defaultSwitched).not.toBe(defaultAtRest)

  // Mid-scrub the paint sits between the ends — the state the attribute-keyed
  // selectors could never express.
  const top = await sectionTop(page, 'acsscrub')
  const viewport = page.viewportSize()?.height ?? 800
  await scrollTo(page, top - viewport * 0.5 + viewport * 0.25)

  const altMid = await iconColor('alt')
  expect(altMid).not.toBe(altAtRest)
  expect(altMid).not.toBe(altSwitched)
})

test('duration 0 flips instantly (the escape hatch)', async ({ page }) => {
  await page.evaluate(() => {
    document.body.style.setProperty('--arts-cs-duration', '0s')
  })

  await page.evaluate(() => window.ArtsColorSwitcher?.set('alt'))
  // Long enough for the changed transition property to take effect (Firefox
  // needs a frame), far short of the 0.4s default — which would still read
  // mid-flight here, so this stays a real test of instantness.
  await page.waitForTimeout(150)

  expect(await progress(page)).toBe(1)
})

test('author transitions on other properties keep working through a morph', async ({ page }) => {
  await page.evaluate(() => {
    const element = document.querySelector('[data-id="acsintro"]') as HTMLElement
    element.style.transition = 'opacity 0.2s linear'
    element.style.opacity = '1'
  })

  await page.evaluate(() => window.ArtsColorSwitcher?.set('alt'))
  await page.evaluate(() => {
    const element = document.querySelector('[data-id="acsintro"]') as HTMLElement
    element.style.opacity = '0.5'
  })
  await page.waitForTimeout(400)

  const opacity = await page
    .locator('[data-id="acsintro"]')
    .evaluate((element) => getComputedStyle(element).opacity)

  expect(Number(opacity)).toBeCloseTo(0.5, 1)
})
