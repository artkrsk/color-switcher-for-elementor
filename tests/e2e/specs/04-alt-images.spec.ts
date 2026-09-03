import { expect, test } from '@playwright/test'

/**
 * The one tier that proves the Alt image CSS APPLIES.
 *
 * PHPUnit proves the rules are generated, and it cannot see a scope prefix
 * that never matches or a `content: url()` the browser declines to honour —
 * both of which would leave every other test green.
 *
 * Both branches are covered, and they are different mechanisms: the container
 * background mirrors the widget's own declaration, while the Image widget has
 * none of its own and gets the replaced-element swap instead.
 */

const PAGE = '/color-switcher-alt-images/'

const BG = 'hero-default.jpg'
const BG_ALT = 'hero-alt.jpg'
const IMG_ALT = 'logo-alt.png'

const backgroundImage = (page: import('@playwright/test').Page): Promise<string> =>
  page.evaluate(() => {
    const node = document.querySelector('.elementor-element-acsaibg')

    return node ? getComputedStyle(node).backgroundImage : 'missing'
  })

const imageContent = (page: import('@playwright/test').Page): Promise<string> =>
  page.evaluate(() => {
    const node = document.querySelector('.elementor-element-acsaiimg img')

    return node ? getComputedStyle(node).content : 'missing'
  })

const theme = (page: import('@playwright/test').Page): Promise<string> =>
  page.evaluate(() => window.ArtsColorSwitcher?.getTheme() ?? 'missing')

test.describe('alt images', () => {
  test('a background image swaps with the palette', async ({ page }) => {
    await page.goto(PAGE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    expect(await theme(page)).toBe('default')
    expect(await backgroundImage(page)).toContain(BG)
    expect(await backgroundImage(page)).not.toContain(BG_ALT)

    await page.locator('.js-arts-cs-toggle').first().click()
    await page.waitForTimeout(300)

    expect(await theme(page)).toBe('alt')
    expect(await backgroundImage(page)).toContain(BG_ALT)
  })

  /**
   * The <img> branch. `content` on a replaced element is what does the swap,
   * so an engine that ignored it would show up here and nowhere else.
   */
  test('a content image swaps with the palette', async ({ page }) => {
    await page.goto(PAGE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    expect(await imageContent(page)).not.toContain(IMG_ALT)

    await page.locator('.js-arts-cs-toggle').first().click()
    await page.waitForTimeout(300)

    expect(await imageContent(page)).toContain(IMG_ALT)
  })

  /** Pressing again releases the choice, and both images come back with it. */
  test('releasing the choice restores both originals', async ({ page }) => {
    await page.goto(PAGE)
    await page.waitForFunction(() => !!window.ArtsColorSwitcher)

    const toggle = page.locator('.js-arts-cs-toggle').first()

    await toggle.click()
    await page.waitForTimeout(300)
    await toggle.click()
    await page.waitForTimeout(300)

    expect(await theme(page)).toBe('default')
    expect(await backgroundImage(page)).toContain(BG)
    expect(await backgroundImage(page)).not.toContain(BG_ALT)
    expect(await imageContent(page)).not.toContain(IMG_ALT)
  })
})
