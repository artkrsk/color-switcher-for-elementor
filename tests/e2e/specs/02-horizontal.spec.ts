import { expect, test } from '@playwright/test'

/**
 * Zones inside an Arts Horizontal Scroll track. A pinned panel's vertical band
 * is frozen while it travels sideways, so this is the case that proves zone
 * state follows what is actually on screen — and that the Viewport handles
 * still mean something there, measured across the stage instead of down it.
 *
 * The fixture marks two panels: panel three has both handles at 50, so it
 * must NOT switch as it appears — it switches once its leading edge reaches
 * mid-stage. Panel two's handles are apart, so it scrubs the scalar instead
 * and never touches the theme attribute the binary assertions read.
 *
 * Same assertions on both projects: chromium runs both engines natively,
 * firefox runs both through the shared scroll-timeline polyfill.
 */

const DEMO = '/color-switcher-horizontal/'

const theme = async (page: import('@playwright/test').Page): Promise<string> =>
  page.evaluate(() => window.ArtsColorSwitcher?.getTheme() ?? 'missing')

const scrollTo = async (page: import('@playwright/test').Page, y: number): Promise<void> => {
  await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y)
  await page.waitForTimeout(700)
}

/** Where the pinned traversal starts, and how long it lasts, in document px. */
const pin = async (
  page: import('@playwright/test').Page
): Promise<{ top: number; window: number }> =>
  page.evaluate(() => {
    const wrapper = document.querySelector('.js-arts-hs') as HTMLElement
    const track = document.querySelector('.js-arts-hs__track') as HTMLElement

    return {
      top: wrapper.getBoundingClientRect().top + window.scrollY,
      window: wrapper.offsetHeight - track.offsetHeight
    }
  })

/**
 * The marked panel's leading edge as a percentage of the stage it crosses:
 * 100 as it appears, 0 once it has travelled the whole way.
 */
const leadingEdge = async (page: import('@playwright/test').Page): Promise<number> =>
  page.evaluate(() => {
    const stage = (document.querySelector('.js-arts-hs') as HTMLElement).getBoundingClientRect()
    const panel = (
      document.querySelector('[data-id="acshsp3"]') as HTMLElement
    ).getBoundingClientRect()

    return ((panel.left - stage.left) / stage.width) * 100
  })

/**
 * How far the Switch skin's knob has travelled, 0..1. The knob rides
 * --arts-cs-p, which every scrub tier animates on body — this page's scrub
 * runs off the horizontal engine's own timeline, and the knob must not care.
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
  await page.waitForFunction(() => !!window.ArtsColorSwitcher && !!window.ARTS_HS)
  // The horizontal engine measures on boot; its track has to be pinned before
  // any of this means anything.
  await page.waitForFunction(() => {
    const track = document.querySelector('.js-arts-hs__track')

    return !!track && getComputedStyle(track).position === 'sticky'
  })
})

test('a marked panel switches when it reaches its line across the stage', async ({ page }) => {
  const { top, window: pinWindow } = await pin(page)

  expect(await theme(page)).toBe('default')

  const samples: { theme: string; edge: number }[] = []

  for (let fraction = 0; fraction <= 1; fraction += 0.05) {
    await scrollTo(page, top + pinWindow * fraction)
    samples.push({ theme: await theme(page), edge: await leadingEdge(page) })
  }

  // It never switches while the panel is still short of its line — the whole
  // point of the rotation. A little slack for sampling granularity.
  const early = samples.filter((sample) => sample.edge > 55)
  expect(early.every((sample) => sample.theme === 'default')).toBe(true)

  // And it does switch once the panel has passed it.
  const arrived = samples.filter((sample) => sample.edge <= 50 && sample.edge > -50)
  expect(arrived.some((sample) => sample.theme === 'alt')).toBe(true)

  // Past the section entirely: the page takes its own palette back.
  await scrollTo(page, top + pinWindow + 1200)
  expect(await theme(page)).toBe('default')
})

/**
 * Their layout control is per-breakpoint, and on a narrow screen the engine
 * turns itself off: the track goes `static` and the panels stack in ordinary
 * vertical flow. A zone inside one has to keep working as a plain section —
 * which it does without a special case, because an intersection with the
 * trigger line means the same thing on either axis.
 */
test('a marked panel still switches when their engine is off at this width', async ({ page }) => {
  await page.setViewportSize({ width: 420, height: 860 })
  await page.reload()
  await page.waitForFunction(() => !!window.ArtsColorSwitcher)
  await page.waitForFunction(() => {
    const track = document.querySelector('.js-arts-hs__track')

    return !!track && getComputedStyle(track).position === 'static'
  })

  const panel = page.locator('[data-id="acshsp3"]')
  const box = await panel.boundingBox()
  const documentTop = (box?.y ?? 0) + (await page.evaluate(() => window.scrollY))
  const viewport = page.viewportSize()?.height ?? 860

  await scrollTo(page, documentTop - viewport)
  expect(await theme(page)).toBe('default')

  // Stacked, the handles read down the viewport again: both at 50 puts the
  // line halfway, so the panel has to span it.
  await scrollTo(page, documentTop - viewport * 0.5 + 10)
  expect(await theme(page)).toBe('alt')
})

/**
 * Panel two's handles are apart (25–75 across the stage), so its zone scrubs
 * the scalar from the horizontal engine's published timeline. The theme
 * attribute never flips for a scrub, so a knob caught partway while the theme
 * still reads default is the proof that the Switch rides the scalar — driven
 * by a sideways traversal our own block-axis tiers cannot describe.
 */
test('the Switch knob rides a panel scrub', async ({ page }) => {
  const { top, window: pinWindow } = await pin(page)

  expect(await knobProgress(page)).toBeLessThan(0.05)

  const samples: { knob: number; theme: string }[] = []

  for (let fraction = 0; fraction <= 0.7; fraction += 0.05) {
    await scrollTo(page, top + pinWindow * fraction)
    samples.push({ knob: await knobProgress(page), theme: await theme(page) })
  }

  const partway = samples.filter(
    (sample) => sample.theme === 'default' && sample.knob > 0.15 && sample.knob < 0.85
  )
  expect(partway.length).toBeGreaterThan(0)

  // Out of the pin entirely: the scalar is handed back and the knob goes home.
  await scrollTo(page, top + pinWindow + 1200)
  expect(await knobProgress(page)).toBeLessThan(0.05)
})

/** Symmetric on the way back up — the state is read, never accumulated. */
test('the same panel hands the page back on the way out', async ({ page }) => {
  const { top, window: pinWindow } = await pin(page)

  await scrollTo(page, top + pinWindow * 0.6)
  expect(await theme(page)).toBe('alt')

  await scrollTo(page, top + pinWindow * 0.1)
  expect(await theme(page)).toBe('default')
})
