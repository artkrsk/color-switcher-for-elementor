// @vitest-environment happy-dom

import { readFileSync } from 'node:fs'
import { ATTR_PREFERENCE, ATTR_STATE, COOKIE_PREFERENCE } from '@ts/constants'
import { resolveBaseline } from '@ts/core/preference'
import type { TPreference, TTheme } from '@ts/types'
import { afterEach, describe, expect, it, vi } from 'vitest'

/**
 * The precedence table is stated twice — once in PHP, inline in <head> so the
 * first paint is already correct, and once in TS, at boot. Nothing imports
 * anything across that gap, and the two are written in different languages
 * against different inputs (a cookie string vs. a parsed one, an inlined PHP
 * flag vs. a DOM attribute).
 *
 * If they ever disagree the page changes theme between first paint and boot —
 * the exact flash the head script exists to prevent, reintroduced by the thing
 * meant to prevent it. So run the real emitted script against the real
 * resolver over every combination that can occur.
 *
 * The script is EXTRACTED from Assets.php rather than copied here: a copy
 * would pass forever while the shipped script drifted.
 */

const ASSETS_PHP = readFileSync('src/php/Managers/Assets.php', 'utf8')

/** Rebuild the emitted JS from the concatenated PHP string literals. */
const headScript = (auto: boolean): string => {
  const assignment = ASSETS_PHP.match(/\$script\s*=\s*([\s\S]*?);\n/)?.[1]

  if (!assignment) {
    throw new Error('Could not find the $script assignment in Assets.php')
  }

  const segments = [...assignment.matchAll(/"((?:[^"\\]|\\.)*)"/g)].map((match) =>
    // PHP double-quote escapes used by this string: \\ and \n.
    (match[1] ?? '').replace(/\\\\/g, '\\').replace(/\\n/g, '\n')
  )

  if (0 === segments.length) {
    throw new Error('Extracted no string segments from the $script assignment')
  }

  return segments
    .join('')
    .replace(/\{\$cookie\}/g, COOKIE_PREFERENCE)
    .replace(/\{\$attribute\}/g, ATTR_PREFERENCE)
    .replace(/\{\$auto\}/g, auto ? '1' : '0')
}

const PAGES = ['auto', 'default', 'alt'] as const
const COOKIES = [null, 'system', 'default', 'alt'] as const

type TPage = (typeof PAGES)[number]

/** What `Documents::add_baseline_html_attribute()` puts on <html>. */
const serverRendered = (page: TPage): TTheme => ('alt' === page ? 'alt' : 'default')

const run = (page: TPage, cookie: TPreference | null, dark: boolean): TTheme => {
  const root = document.documentElement

  root.removeAttribute(ATTR_STATE)
  root.removeAttribute(ATTR_PREFERENCE)

  if ('alt' === serverRendered(page)) {
    root.setAttribute(ATTR_STATE, 'alt')
  }

  // biome-ignore lint/suspicious/noDocumentCookie: the fixture writes the cookie the same way the plugin does
  document.cookie = cookie
    ? `${COOKIE_PREFERENCE}=${cookie};path=/`
    : `${COOKIE_PREFERENCE}=;max-age=0;path=/`

  vi.stubGlobal('matchMedia', () => ({ matches: dark, addEventListener: () => {} }))

  new Function(headScript('auto' === page))()

  return 'alt' === root.getAttribute(ATTR_STATE) ? 'alt' : 'default'
}

afterEach(() => {
  // biome-ignore lint/suspicious/noDocumentCookie: clearing the fixture the same way the code under test writes it
  document.cookie = `${COOKIE_PREFERENCE}=;max-age=0;path=/`
  document.documentElement.removeAttribute(ATTR_STATE)
  document.documentElement.removeAttribute(ATTR_PREFERENCE)
  vi.unstubAllGlobals()
})

describe('head script ↔ resolveBaseline parity', () => {
  for (const page of PAGES) {
    for (const cookie of COOKIES) {
      for (const dark of [false, true]) {
        const label = `page=${page} cookie=${cookie ?? 'none'} device=${dark ? 'dark' : 'light'}`

        it(`agrees before and after boot — ${label}`, () => {
          const painted = run(page, cookie, dark)

          const booted = resolveBaseline(serverRendered(page), 'auto' === page)

          expect(painted).toBe(booted)
        })
      }
    }
  }

  /**
   * The change this suite was written for. Spelled out separately from the
   * matrix so a regression names itself rather than showing up as one of
   * twenty-four indistinguishable failures.
   */
  it('lets the device decide an auto page, and only an auto page', () => {
    expect(run('auto', null, true)).toBe('alt')
    expect(run('auto', null, false)).toBe('default')

    expect(run('default', null, true)).toBe('default')
    expect(run('alt', null, false)).toBe('alt')
  })

  it('still lets a stored choice outrank the device everywhere', () => {
    expect(run('auto', 'default', true)).toBe('default')
    expect(run('alt', 'default', true)).toBe('default')
    expect(run('default', 'alt', false)).toBe('alt')
  })

  /** The attribute the stylesheet reads to decide what looks pressed. */
  it('stamps the preference attribute only when one is stored', () => {
    run('auto', 'system', true)
    expect(document.documentElement.getAttribute(ATTR_PREFERENCE)).toBe('system')

    run('auto', null, true)
    expect(document.documentElement.hasAttribute(ATTR_PREFERENCE)).toBe(false)
  })
})
