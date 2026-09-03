// @vitest-environment happy-dom

import { COOKIE_PREFERENCE } from '@ts/constants'
import { current, read, resolveBaseline, watchSystem, write } from '@ts/core/preference'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The visitor's layer. Authority runs visitor, then author, then device: the
 * presence of a cookie transfers authority from the page's author to the
 * visitor, and only a page whose author declined to choose (`auto`) lets the
 * device decide.
 */

const setSystemDark = (dark: boolean, listeners: (() => void)[] = []): void => {
  vi.stubGlobal('matchMedia', () => ({
    matches: dark,
    addEventListener: (_event: string, handler: () => void) => listeners.push(handler)
  }))
}

beforeEach(() => {
  setSystemDark(false)
})

afterEach(() => {
  // biome-ignore lint/suspicious/noDocumentCookie: clearing the fixture the same way the code under test writes it
  document.cookie = `${COOKIE_PREFERENCE}=;max-age=0;path=/`
})

describe('preference', () => {
  it('keeps a page the author set, whatever the device says', () => {
    setSystemDark(true)

    expect(read()).toBeNull()
    expect(resolveBaseline('alt', false)).toBe('alt')
    expect(resolveBaseline('default', false)).toBe('default')
  })

  /**
   * The rule this plugin changed before 1.0: an author who never chose is
   * deferring to the visitor's device, which is the only party that knows
   * their real sunrise and sunset.
   */
  it('lets the device decide an auto page when nothing is stored', () => {
    setSystemDark(true)
    expect(resolveBaseline('default', true)).toBe('alt')

    setSystemDark(false)
    expect(resolveBaseline('default', true)).toBe('default')
  })

  /** A stored choice outranks whatever the page's author designed. */
  it('lets a stored choice override the rendered baseline', () => {
    write('default')
    expect(resolveBaseline('alt', false)).toBe('default')

    write('alt')
    expect(resolveBaseline('default', false)).toBe('alt')
  })

  /** A stored choice outranks the device too, including on an auto page. */
  it('lets a stored choice override the device on an auto page', () => {
    setSystemDark(true)

    write('default')
    expect(resolveBaseline('default', true)).toBe('default')
  })

  it('resolves a stored system preference through the OS', () => {
    write('system')

    setSystemDark(true)
    expect(resolveBaseline('default', false)).toBe('alt')

    setSystemDark(false)
    expect(resolveBaseline('alt', false)).toBe('default')
  })

  it('reports system when nothing has been chosen', () => {
    expect(current()).toBe('system')

    write('alt')
    expect(current()).toBe('alt')
  })

  it('ignores a cookie value it does not recognise', () => {
    // biome-ignore lint/suspicious/noDocumentCookie: clearing the fixture the same way the code under test writes it
    document.cookie = `${COOKIE_PREFERENCE}=purple;path=/`

    expect(read()).toBeNull()
    expect(resolveBaseline('alt', false)).toBe('alt')
  })

  /**
   * The load-bearing one: following the device must never write the cookie.
   * Persisting here would resolve the stored `system` into a concrete theme
   * and silently destroy the preference the feature exists to hold.
   *
   * It notifies rather than handing back a theme, so the caller re-runs the
   * whole precedence table — that is what keeps an explicitly light page from
   * following a device change it was never meant to hear.
   */
  it('reports a device change without ever persisting a resolved theme', () => {
    const listeners: (() => void)[] = []
    setSystemDark(false, listeners)
    write('system')

    let changes = 0
    watchSystem(() => {
      changes += 1
    })

    setSystemDark(true, listeners)
    for (const listener of listeners) {
      listener()
    }

    expect(changes).toBe(1)
    expect(read()).toBe('system')
  })

  it('reports a device change when nothing is stored at all', () => {
    const listeners: (() => void)[] = []
    setSystemDark(false, listeners)

    let changes = 0
    watchSystem(() => {
      changes += 1
    })

    for (const listener of listeners) {
      listener()
    }

    expect(changes).toBe(1)
    expect(read()).toBeNull()
  })

  it('goes quiet once a visitor has chosen a concrete theme', () => {
    const listeners: (() => void)[] = []
    setSystemDark(false, listeners)
    write('alt')

    let changes = 0
    watchSystem(() => {
      changes += 1
    })

    for (const listener of listeners) {
      listener()
    }

    expect(changes).toBe(0)
    expect(read()).toBe('alt')
  })
})
