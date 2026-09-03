// @vitest-environment happy-dom

import { ATTR_BASELINE, ATTR_STATE, CLASS_MORPHING, EVENT_CHANGE } from '@ts/constants'
import { coordinator } from '@ts/core/coordinator'
import type { IChangeDetail } from '@ts/interfaces'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The executable statement of the committed surface: attribute ownership,
 * event payloads, baseline reading, teardown. contractParity.test.ts holds
 * README's table to the names; this file holds the names to behavior.
 *
 * happy-dom ships no IntersectionObserver, and the coordinator now resolves
 * from one — so the stub records what it was built with and lets a test push
 * intersections, which is exactly the surface the browser provides.
 */

interface IFakeObserver {
  rootMargin: string
  targets: Set<Element>
  fire(target: Element, isIntersecting: boolean): void
  disconnected: boolean
}

let observers: IFakeObserver[] = []

const zoneElement = (): HTMLElement => {
  const element = document.createElement('div')
  document.body.appendChild(element)

  return element
}

/** Push an intersection through whichever observer holds this element. */
const setActive = (element: Element, isIntersecting: boolean): void => {
  for (const observer of observers) {
    if (observer.targets.has(element)) {
      observer.fire(element, isIntersecting)
    }
  }
}

beforeEach(() => {
  observers = []
  window.innerHeight = 1000

  vi.stubGlobal(
    'IntersectionObserver',
    class {
      rootMargin: string
      targets = new Set<Element>()
      disconnected = false
      private callback: (entries: IntersectionObserverEntry[]) => void

      constructor(
        callback: (entries: IntersectionObserverEntry[]) => void,
        options?: { rootMargin?: string }
      ) {
        this.callback = callback
        this.rootMargin = options?.rootMargin ?? ''
        observers.push(this as unknown as IFakeObserver)
      }

      observe(target: Element): void {
        this.targets.add(target)
      }

      unobserve(target: Element): void {
        this.targets.delete(target)
      }

      disconnect(): void {
        this.targets.clear()
        this.disconnected = true
      }

      fire(target: Element, isIntersecting: boolean): void {
        this.callback([{ target, isIntersecting } as unknown as IntersectionObserverEntry])
      }
    }
  )
})

afterEach(() => {
  coordinator.destroy()
  // The coordinator is a page-level singleton: destroy() forgets zones and
  // observers, but the baseline is page state and outlives them, so tests
  // that moved it have to put it back or they leak into the next one.
  coordinator.set('default')
  document.documentElement.removeAttribute(ATTR_STATE)
  document.documentElement.classList.remove(CLASS_MORPHING)
  document.body.innerHTML = ''
})

describe('ArtsColorSwitcher contract', () => {
  it('owns the html state attribute through set()', () => {
    coordinator.set('alt')
    expect(document.documentElement.getAttribute(ATTR_STATE)).toBe('alt')

    coordinator.set('default')
    expect(document.documentElement.hasAttribute(ATTR_STATE)).toBe(false)
  })

  it('reports the current theme', () => {
    expect(coordinator.getTheme()).toBe('default')
    coordinator.set('alt')
    expect(coordinator.getTheme()).toBe('alt')
  })

  it('dispatches arts-cs:change with theme and source', () => {
    const events: IChangeDetail[] = []
    const listener = (event: Event): void => {
      events.push((event as CustomEvent<IChangeDetail>).detail)
    }

    document.addEventListener(EVENT_CHANGE, listener)
    coordinator.set('alt')
    coordinator.set('alt')
    coordinator.set('default')
    document.removeEventListener(EVENT_CHANGE, listener)

    expect(events).toEqual([
      { theme: 'alt', source: 'api' },
      { theme: 'default', source: 'api' }
    ])
  })

  it('adds the morphing class for the morph window', () => {
    coordinator.set('alt')
    expect(document.documentElement.classList.contains(CLASS_MORPHING)).toBe(true)
  })

  it('re-reads the baseline from the document wrapper on refresh()', () => {
    const wrapper = document.createElement('div')
    wrapper.setAttribute(ATTR_BASELINE, 'alt')
    document.body.appendChild(wrapper)

    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('alt')

    wrapper.setAttribute(ATTR_BASELINE, 'default')
    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('default')
  })

  /**
   * An untouched page defers to the visitor's device. The wrapper says `auto`
   * rather than carrying a theme, because "follow the device" and "light for
   * everyone" are different instructions and an empty value cannot tell them
   * apart after an AJAX swap.
   */
  it('lets the device decide a baseline the author left on auto', () => {
    const wrapper = document.createElement('div')
    wrapper.setAttribute(ATTR_BASELINE, 'auto')
    document.body.appendChild(wrapper)

    vi.stubGlobal('matchMedia', () => ({ matches: true, addEventListener: () => {} }))
    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('alt')

    vi.stubGlobal('matchMedia', () => ({ matches: false, addEventListener: () => {} }))
    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('default')
  })

  /** An author who stated a palette is obeyed, device or no device. */
  it('ignores the device on a page the author set explicitly', () => {
    const wrapper = document.createElement('div')
    wrapper.setAttribute(ATTR_BASELINE, 'default')
    document.body.appendChild(wrapper)

    vi.stubGlobal('matchMedia', () => ({ matches: true, addEventListener: () => {} }))
    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('default')
  })

  /**
   * The canvas shows what the author built. Without this an author on a dark
   * machine would open a light page and find it dark, then design against
   * colors none of their visitors asked for.
   */
  it('does not let the device decide inside the editor canvas', () => {
    const wrapper = document.createElement('div')
    wrapper.setAttribute(ATTR_BASELINE, 'auto')
    document.body.appendChild(wrapper)

    vi.stubGlobal('matchMedia', () => ({ matches: true, addEventListener: () => {} }))
    vi.stubGlobal('elementorFrontend', { isEditMode: () => true })

    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('default')
  })

  it('lets a registered zone override the baseline and restores on unregister', () => {
    const element = zoneElement()

    coordinator.register({ element, triggerPoint: 0.8, distance: 0 })
    setActive(element, true)
    expect(coordinator.getTheme()).toBe('alt')

    coordinator.unregister(element)
    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('default')
  })

  it('follows the observer in both directions', () => {
    const element = zoneElement()

    coordinator.register({ element, triggerPoint: 0.8, distance: 0 })
    expect(coordinator.getTheme()).toBe('default')

    setActive(element, true)
    expect(coordinator.getTheme()).toBe('alt')

    setActive(element, false)
    expect(coordinator.getTheme()).toBe('default')
  })

  /**
   * The trigger line IS the observer's root, collapsed to a 1px strip. The
   * strip is what makes the state work on both axes — a panel pinned inside a
   * horizontally scrolling track has a frozen vertical band and moves only
   * sideways, which no rect comparison can see.
   */
  it('collapses the root to a 1px strip at the trigger line', () => {
    coordinator.register({ element: zoneElement(), triggerPoint: 0.8, distance: 0 })

    expect(observers[0]?.rootMargin).toBe('-800px 0px -199px 0px')
  })

  /**
   * A zero-height root reports nothing — the failure this plugin shipped once,
   * when a -100% margin collapsed the root at trigger point 0.
   */
  it('never collapses the root to nothing at the very top', () => {
    coordinator.register({ element: zoneElement(), triggerPoint: 0, distance: 0 })

    expect(observers[0]?.rootMargin).toBe('0px 0px -999px 0px')
  })

  it('measures the line from below the admin bar', () => {
    vi.spyOn(window, 'getComputedStyle').mockReturnValue({
      getPropertyValue: () => '32px'
    } as unknown as CSSStyleDeclaration)

    coordinator.register({ element: zoneElement(), triggerPoint: 0, distance: 0 })

    expect(observers[0]?.rootMargin).toBe('-32px 0px -967px 0px')
  })

  /**
   * A section pinned inside a horizontal track spans the whole track height
   * for its entire traversal, so a horizontal line is always crossed and the
   * handle would decide nothing. Turned ninety degrees it keeps the meaning
   * it has vertically: how far into the screen the section travels first.
   */
  it('rotates the root for a section pinned in a horizontal track', () => {
    const track = document.createElement('div')
    const wrapper = document.createElement('div')
    const panel = document.createElement('div')

    wrapper.className = 'js-arts-hs'
    track.className = 'js-arts-hs__track'
    wrapper.appendChild(track)
    track.appendChild(panel)
    document.body.appendChild(wrapper)
    window.innerWidth = 1000

    vi.spyOn(window, 'getComputedStyle').mockImplementation(
      (element) =>
        ({
          position: element === track ? 'sticky' : 'static',
          getPropertyValue: () => ''
        }) as unknown as CSSStyleDeclaration
    )
    // The stage is inset from the viewport — a boxed section, or the editor
    // canvas's own gutter. The line belongs to the stage, not the window.
    vi.spyOn(wrapper, 'getBoundingClientRect').mockImplementation(
      () => ({ left: 100, width: 800 }) as DOMRect
    )

    coordinator.register({ element: panel, triggerPoint: 0.5, distance: 0 })

    expect(observers[0]?.rootMargin).toBe('0px -499px 0px -500px')
  })

  /** A vertical state leaves it an ordinary zone, measured the normal way. */
  it('keeps the upright root when their track is not pinned', () => {
    const track = document.createElement('div')
    const panel = document.createElement('div')

    track.className = 'js-arts-hs__track'
    track.appendChild(panel)
    document.body.appendChild(track)

    vi.spyOn(window, 'getComputedStyle').mockReturnValue({
      position: 'static',
      getPropertyValue: () => ''
    } as unknown as CSSStyleDeclaration)

    coordinator.register({ element: panel, triggerPoint: 0.8, distance: 0 })

    expect(observers[0]?.rootMargin).toBe('-800px 0px -199px 0px')
  })

  /** rootMargin is per-observer, so a shared line means a shared observer. */
  it('builds one observer per distinct trigger line', () => {
    coordinator.register({ element: zoneElement(), triggerPoint: 0.8, distance: 0 })
    coordinator.register({ element: zoneElement(), triggerPoint: 0.8, distance: 0 })
    coordinator.register({ element: zoneElement(), triggerPoint: 0.4, distance: 0 })

    expect(observers).toHaveLength(2)
    expect(observers[0]?.targets.size).toBe(2)
  })

  /** rootMargin cannot be mutated, so a resize rebuilds every observer. */
  it('rebuilds observers when the viewport changes', () => {
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      callback(0)

      return 1
    })

    coordinator.register({ element: zoneElement(), triggerPoint: 0.8, distance: 0 })
    window.innerHeight = 600
    window.dispatchEvent(new Event('resize'))

    expect(observers[0]?.disconnected).toBe(true)
    expect(observers[1]?.rootMargin).toBe('-480px 0px -119px 0px')
  })

  /**
   * A width-only resize leaves every root identical, and rebuilding would
   * drop the active set and refill it asynchronously for no reason.
   */
  it('does not rebuild when the roots would be identical', () => {
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      callback(0)

      return 1
    })

    coordinator.register({ element: zoneElement(), triggerPoint: 0.8, distance: 0 })
    window.dispatchEvent(new Event('resize'))

    expect(observers).toHaveLength(1)
    expect(observers[0]?.disconnected).toBe(false)
  })

  it('listens for resize only — never for scroll', () => {
    const added: string[] = []

    vi.spyOn(window, 'addEventListener').mockImplementation((event) => {
      added.push(event)
    })

    coordinator.register({ element: zoneElement(), triggerPoint: 0.8, distance: 0 })

    expect(added).toContain('resize')
    expect(added).not.toContain('scroll')
  })

  it('stops listening once the last zone is gone', () => {
    const element = zoneElement()
    const removed: string[] = []

    vi.spyOn(window, 'removeEventListener').mockImplementation((event) => {
      removed.push(event)
    })

    coordinator.register({ element, triggerPoint: 0.8, distance: 0 })
    coordinator.unregister(element)

    expect(removed).toContain('resize')
    expect(observers[0]?.disconnected).toBe(true)
  })

  it('prunes zones whose element left the DOM', () => {
    const element = zoneElement()

    coordinator.register({ element, triggerPoint: 0.8, distance: 0 })
    setActive(element, true)
    expect(coordinator.getTheme()).toBe('alt')

    element.remove()
    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('default')
  })

  it('destroy() forgets every zone', () => {
    const element = zoneElement()

    coordinator.register({ element, triggerPoint: 0.8, distance: 0 })
    setActive(element, true)

    coordinator.destroy()
    coordinator.refresh()
    expect(coordinator.getTheme()).toBe('default')
  })
})
