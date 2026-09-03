// @vitest-environment happy-dom

import { ATTR_PREFERENCE, ATTR_STATE, COOKIE_PREFERENCE } from '@ts/constants'
import { coordinator } from '@ts/core/coordinator'
import { read } from '@ts/core/preference'
import { attachToggle } from '@ts/core/toggle'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The controls are the only thing in the plugin that persists anything. These
 * hold them to that: what they store, what they release, and what they say
 * while doing it.
 */

/** As PHP renders it, minus the icons no assertion here reads. */
const control = (skin = 'icon', mode = 'binary', options: string[] = []): HTMLElement => {
  const element = document.createElement(options.length ? 'div' : 'button')

  element.className = `arts-cs-toggle arts-cs-toggle_${skin} js-arts-cs-toggle`
  element.setAttribute('data-arts-cs-toggle', skin)
  element.setAttribute('data-arts-cs-mode', mode)
  element.setAttribute('data-arts-cs-name', 'Color theme')
  element.setAttribute('data-arts-cs-label-system', 'System')
  element.setAttribute('data-arts-cs-label-default', 'Light')
  element.setAttribute('data-arts-cs-label-alt', 'Dark')

  for (const state of options) {
    const option = document.createElement('button')

    option.className = 'arts-cs-toggle__option'
    option.setAttribute('data-arts-cs-set', state)
    option.setAttribute('aria-pressed', 'false')
    element.appendChild(option)
  }

  document.body.appendChild(element)

  return element
}

const dropdown = (states: string[]): HTMLElement => {
  const element = control('dropdown', 3 === states.length ? 'cycle' : 'binary')
  const select = document.createElement('select')

  for (const state of states) {
    const option = document.createElement('option')

    option.value = state
    select.appendChild(option)
  }

  element.appendChild(select)

  return element
}

const optionOf = (root: HTMLElement, state: string): HTMLElement =>
  root.querySelector(`[data-arts-cs-set="${state}"]`) as HTMLElement

/** A page the author designed dark, which the coordinator reads on refresh. */
const authorDark = (): void => {
  const wrapper = document.createElement('div')

  wrapper.setAttribute('data-arts-cs-baseline', 'alt')
  document.body.appendChild(wrapper)
  coordinator.refresh()
}

beforeEach(() => {
  vi.stubGlobal('matchMedia', () => ({ matches: false, addEventListener: () => {} }))
  vi.stubGlobal(
    'IntersectionObserver',
    class {
      observe(): void {}
      unobserve(): void {}
      disconnect(): void {}
    }
  )
  coordinator.refresh()
})

afterEach(() => {
  // biome-ignore lint/suspicious/noDocumentCookie: clearing the fixture the same way the code under test writes it
  document.cookie = `${COOKIE_PREFERENCE}=;max-age=0;path=/`
  document.documentElement.removeAttribute(ATTR_STATE)
  document.documentElement.removeAttribute(ATTR_PREFERENCE)
  document.body.innerHTML = ''
  coordinator.destroy()
})

describe('two-state controls', () => {
  /**
   * The pattern the whole two-state argument turns on: a visitor who presses
   * once is pinned, and pressing again hands the page back to its author —
   * exactly where they were before they touched anything. Storing `system`
   * instead would send an OS-dark visitor somewhere they have never been.
   */
  it('pins a choice, then releases it', () => {
    const element = control()
    attachToggle(element)

    element.click()
    expect(read()).toBe('alt')
    expect(coordinator.getTheme()).toBe('alt')

    element.click()
    expect(read()).toBeNull()
    expect(coordinator.getTheme()).toBe('default')
  })

  /** Which way it pins is the page's own state, not whatever it shows now. */
  it('pins the opposite of a dark page', () => {
    authorDark()

    const element = control()
    attachToggle(element)

    element.click()
    expect(read()).toBe('default')
    expect(coordinator.getTheme()).toBe('default')
  })

  /**
   * A zone shows the opposite of the baseline while it is on screen. Reading
   * the applied theme there would store the opposite of what the visitor is
   * asking for, and the first press would appear to do nothing.
   */
  it('ignores a zone that has inverted the page', () => {
    document.documentElement.setAttribute(ATTR_STATE, 'alt')

    const element = control()
    attachToggle(element)

    element.click()
    expect(read()).toBe('alt')
  })

  it('reports its state as a pressed button', () => {
    const element = control()
    attachToggle(element)

    expect(element.getAttribute('aria-pressed')).toBe('false')

    element.click()
    expect(element.getAttribute('aria-pressed')).toBe('true')
  })

  /** `role="switch"` only where the control looks like one. */
  it('reports a switch as checked', () => {
    const element = control('switch')
    attachToggle(element)

    element.click()
    expect(element.getAttribute('aria-checked')).toBe('true')
    expect(element.hasAttribute('aria-pressed')).toBe(false)
  })
})

describe('the three-state cycle', () => {
  it('cycles back to following the system', () => {
    const element = control('icon', 'cycle')
    attachToggle(element)

    element.click()
    expect(read()).toBe('default')

    element.click()
    expect(read()).toBe('alt')

    element.click()
    expect(read()).toBe('system')
  })

  /** Three states cannot be checked or pressed, so the name carries them. */
  it("announces the state it is in, in the author's words", () => {
    const element = control('icon', 'cycle')
    attachToggle(element)

    expect(element.getAttribute('aria-label')).toBe('Color theme: System')

    element.click()
    expect(element.getAttribute('aria-label')).toBe('Color theme: Light')

    element.click()
    expect(element.getAttribute('aria-label')).toBe('Color theme: Dark')
  })
})

describe('the buttons skin', () => {
  /**
   * Two options and nothing stored: neither is pressed. That empty state is
   * how two controls express the third, and it is why this skin can offer
   * "follow the system" without a button for it.
   */
  it('presses neither option while the author still decides', () => {
    const element = control('buttons', 'binary', ['default', 'alt'])
    attachToggle(element)

    expect(optionOf(element, 'default').getAttribute('aria-pressed')).toBe('false')
    expect(optionOf(element, 'alt').getAttribute('aria-pressed')).toBe('false')
  })

  it('releases the pinned option when it is pressed again', () => {
    const element = control('buttons', 'binary', ['default', 'alt'])
    attachToggle(element)

    optionOf(element, 'alt').click()
    expect(read()).toBe('alt')
    expect(optionOf(element, 'alt').getAttribute('aria-pressed')).toBe('true')

    optionOf(element, 'alt').click()
    expect(read()).toBeNull()
    expect(optionOf(element, 'alt').getAttribute('aria-pressed')).toBe('false')
  })

  /** Three options are a choice among all of them: nothing to release. */
  it('presses System while nothing is stored, and keeps it on re-press', () => {
    const element = control('buttons', 'cycle', ['system', 'default', 'alt'])
    attachToggle(element)

    expect(optionOf(element, 'system').getAttribute('aria-pressed')).toBe('true')

    optionOf(element, 'system').click()
    expect(read()).toBe('system')
    expect(optionOf(element, 'system').getAttribute('aria-pressed')).toBe('true')
  })
})

describe('the dropdown skin', () => {
  it('stores what was selected', () => {
    const element = dropdown(['system', 'default', 'alt'])
    attachToggle(element)

    const select = element.querySelector('select') as HTMLSelectElement

    select.value = 'alt'
    select.dispatchEvent(new Event('change'))

    expect(read()).toBe('alt')
    expect(coordinator.getTheme()).toBe('alt')
  })

  /** CSS cannot state a select's value, so the runtime is what corrects it. */
  it('shows the stored choice a cached page could not know', () => {
    // biome-ignore lint/suspicious/noDocumentCookie: seeding the fixture the way a returning visitor arrives
    document.cookie = `${COOKIE_PREFERENCE}=alt;path=/`

    const element = dropdown(['system', 'default', 'alt'])
    attachToggle(element)

    expect((element.querySelector('select') as HTMLSelectElement).value).toBe('alt')
  })
})

describe('every control on the page', () => {
  /**
   * The preference lives on `<html>`, where the head script puts it before
   * paint — so the stylesheet decides what looks active, and a second control
   * in the footer is never told anything.
   */
  it('reads its active state from one attribute', () => {
    const element = control()
    attachToggle(element)

    element.click()
    expect(document.documentElement.getAttribute(ATTR_PREFERENCE)).toBe('alt')

    element.click()
    expect(document.documentElement.hasAttribute(ATTR_PREFERENCE)).toBe(false)
  })

  /**
   * Moving from `system` to an explicit Default on a light OS moves the
   * preference without moving the theme, so no change event fires — and a
   * control that only listened would keep announcing the old state.
   */
  it('stays in step with a control it never hears from', () => {
    const first = control('icon', 'cycle')
    const second = control('icon', 'cycle')

    attachToggle(first)
    attachToggle(second)

    first.click()
    expect(second.getAttribute('aria-label')).toBe('Color theme: Light')
  })

  /** element_ready and boot's catch-up scan both reach the same root. */
  it('binds a control only once', () => {
    const element = control()
    attachToggle(element)
    attachToggle(element)

    element.click()
    expect(read()).toBe('alt')
  })
})
