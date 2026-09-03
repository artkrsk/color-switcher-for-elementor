// @vitest-environment happy-dom

import { CLASS_SCROLL_NATIVE, CLASS_SCROLL_REVERSE, VAR_PROGRESS } from '@ts/constants'
import { scrollDriver } from '@ts/core/scrollDriver'
import type { IZone } from '@ts/interfaces'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The scrub's range is the viewport band the control draws: it starts at the
 * lower handle and ends at the upper one. Both tiers have to agree — the
 * native one in real units, the polyfilled one in percentages of the cover
 * span, because the polyfill drops length offsets.
 */

const zone = (overrides: Partial<IZone> = {}): IZone => {
  const element = document.createElement('div')
  document.body.appendChild(element)
  vi.spyOn(element, 'getBoundingClientRect').mockImplementation(
    () => ({ height: 1000, top: 0 }) as DOMRect
  )

  return {
    element,
    triggerPoint: 0.8,
    distance: 1,
    ...overrides
  }
}

/** rAF runs inline so a sync() lands before the assertion. */
beforeEach(() => {
  vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
    callback(0)

    return 1
  })
  vi.stubGlobal('cancelAnimationFrame', () => {})
  window.innerHeight = 1000
})

afterEach(() => {
  scrollDriver.teardown()
  document.body.innerHTML = ''
})

const range = (): string => document.body.style.getPropertyValue('animation-range')

/**
 * A zone inside an actively pinned Arts Horizontal Scroll track. The track's
 * `sticky` position is that plugin's own documented state probe, and the
 * panel window vars are its committed per-panel surface.
 */
const horizontalZone = (distance: number, panelStart = '33.333%', panelEnd = '100%'): IZone => {
  const wrapper = document.createElement('div')
  const track = document.createElement('div')
  const panel = document.createElement('div')

  wrapper.className = 'js-arts-hs'
  track.className = 'js-arts-hs__track'
  wrapper.appendChild(track)
  track.appendChild(panel)
  document.body.appendChild(wrapper)

  vi.spyOn(window, 'getComputedStyle').mockImplementation(
    (element) =>
      ({
        position: element === track ? 'sticky' : 'static',
        getPropertyValue: (name: string) => {
          if ('--arts-hs-panel-start' === name) {
            return panelStart
          }

          if ('--arts-hs-panel-end' === name) {
            return panelEnd
          }

          return ''
        }
      }) as unknown as CSSStyleDeclaration
  )

  return { element: panel, triggerPoint: 1, distance }
}

/** happy-dom has no Element.animate; the driver only needs what it returns. */
const captureAnimate = (): KeyframeAnimationOptions[] => {
  const options: KeyframeAnimationOptions[] = []

  ;(document.body as unknown as { animate: unknown }).animate = (
    _frames: Keyframe[],
    opts: KeyframeAnimationOptions
  ): Animation => {
    options.push(opts)

    return { cancel: () => {} } as Animation
  }

  return options
}

describe('scroll driver', () => {
  /**
   * Their timeline, not ours: a pinned panel's vertical position is frozen,
   * so a block-axis scrub would finish during the section's entry and hold
   * flat across the traversal.
   */
  it('rides the horizontal timeline for a zone inside a pinned track', () => {
    const options = captureAnimate()
    const timeline = {} as AnimationTimeline
    vi.stubGlobal('ARTS_HS', { contract: 1, getTimeline: () => timeline })

    scrollDriver.sync(horizontalZone(0.5), 'default', 'alt')

    // Half the distance of a window running 33.333% → 100%.
    expect(options[0]).toMatchObject({
      timeline,
      rangeStart: 'contain 33.333%',
      rangeEnd: 'contain 66.6665%'
    })
    // The vertical tier must not also be running.
    expect(document.documentElement.classList.contains(CLASS_SCROLL_NATIVE)).toBe(false)
  })

  it('spans the whole panel window when the distance covers a viewport', () => {
    const options = captureAnimate()
    vi.stubGlobal('ARTS_HS', { contract: 1, getTimeline: () => ({}) as AnimationTimeline })

    scrollDriver.sync(horizontalZone(1), 'default', 'alt')

    expect(options[0]).toMatchObject({ rangeStart: 'contain 33.333%', rangeEnd: 'contain 100%' })
  })

  /**
   * A marked horizontal SECTION is an ordinary vertical zone: its runway
   * scrolls normally, so the Viewport handles keep their literal meaning.
   * Only what travels sideways inside the track needs their timeline.
   */
  it('leaves a marked wrapper on the vertical tier', () => {
    const wrapper = document.createElement('div')
    const track = document.createElement('div')
    wrapper.className = 'js-arts-hs'
    track.className = 'js-arts-hs__track'
    wrapper.appendChild(track)
    document.body.appendChild(wrapper)
    vi.spyOn(wrapper, 'getBoundingClientRect').mockImplementation(
      () => ({ height: 1000, top: 0 }) as DOMRect
    )
    vi.stubGlobal('ARTS_HS', { contract: 1, getTimeline: () => ({}) as AnimationTimeline })

    scrollDriver.sync({ element: wrapper, triggerPoint: 1, distance: 0.25 }, 'default', 'alt')

    expect(range()).toBe('cover 0px cover 250px')
  })

  /**
   * Their engine goes `static` on touch and vertical breakpoints, and a WAAPI
   * animation would bypass that CSS gate — so the normal tier takes over.
   */
  it('falls back to the vertical tier when their track is not pinned', () => {
    const wrapper = document.createElement('div')
    const track = document.createElement('div')
    const panel = document.createElement('div')
    wrapper.className = 'js-arts-hs'
    track.className = 'js-arts-hs__track'
    wrapper.appendChild(track)
    track.appendChild(panel)
    document.body.appendChild(wrapper)
    vi.spyOn(panel, 'getBoundingClientRect').mockImplementation(
      () => ({ height: 1000, top: 0 }) as DOMRect
    )
    vi.stubGlobal('ARTS_HS', { contract: 1, getTimeline: () => ({}) as AnimationTimeline })

    scrollDriver.sync({ element: panel, triggerPoint: 1, distance: 0.25 }, 'default', 'alt')

    expect(range()).toBe('cover 0px cover 250px')
  })

  /**
   * Removing an animation restores the base value in the same frame and
   * starts no transition, so without this the page cut to the baseline the
   * instant a scrubbing section left. The held value has to survive one frame
   * as a normal declaration for the body transition to interpolate from.
   */
  it('eases the held value back instead of cutting it', () => {
    const frames: FrameRequestCallback[] = []
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      frames.push(callback)

      return frames.length
    })

    scrollDriver.sync(zone({ triggerPoint: 1, distance: 0.5 }), 'default', 'alt')
    frames.shift()?.(0)

    vi.spyOn(window, 'getComputedStyle').mockReturnValue({
      getPropertyValue: () => '1'
    } as unknown as CSSStyleDeclaration)

    scrollDriver.teardown()
    expect(document.body.style.getPropertyValue(VAR_PROGRESS)).toBe('1')

    frames.pop()?.(0)
    expect(document.body.style.getPropertyValue(VAR_PROGRESS)).toBe('')
  })

  /**
   * Two teardowns can land in one frame batch — leaving a scrub zone fires a
   * resolve per observer event, and each one with no scrub zone tears down.
   * The second used to cancel the first's scheduled removal of the pinned
   * value, stranding it inline on body forever: every color froze at the held
   * mid-mix value until something else animated the scalar.
   */
  it('still removes the pinned value when a second teardown lands before the release frame', () => {
    const frames = new Map<number, FrameRequestCallback>()
    let handle = 0
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      frames.set(++handle, callback)

      return handle
    })
    vi.stubGlobal('cancelAnimationFrame', (id: number) => {
      frames.delete(id)
    })
    const runFrames = (): void => {
      for (const [id, frame] of [...frames]) {
        frames.delete(id)
        frame(0)
      }
    }

    scrollDriver.sync(zone({ triggerPoint: 1, distance: 0.5 }), 'default', 'alt')
    runFrames()

    vi.spyOn(window, 'getComputedStyle').mockReturnValue({
      getPropertyValue: () => '0.4'
    } as unknown as CSSStyleDeclaration)

    scrollDriver.teardown()
    expect(document.body.style.getPropertyValue(VAR_PROGRESS)).toBe('0.4')

    // The second teardown, before the release frame has run.
    scrollDriver.teardown()
    runFrames()

    expect(document.body.style.getPropertyValue(VAR_PROGRESS)).toBe('')
  })

  it('starts the scrub at the trigger line, not where the section enters view', () => {
    scrollDriver.sync(zone({ triggerPoint: 0.8 }), 'default', 'alt')

    // Cover starts with the section top at the viewport bottom; a line at 80%
    // of the way up means it has travelled the remaining 20% by then.
    expect(range()).toBe('cover 200px cover 1200px')
  })

  it('moves the whole range with the trigger line', () => {
    scrollDriver.sync(zone({ triggerPoint: 0.5 }), 'default', 'alt')

    expect(range()).toBe('cover 500px cover 1500px')
  })

  it('spans only the distance the handles describe', () => {
    scrollDriver.sync(zone({ triggerPoint: 1, distance: 0.25 }), 'default', 'alt')

    expect(range()).toBe('cover 0px cover 250px')
  })

  /** The scrub may not outlive the zone it belongs to. */
  it('clamps a distance longer than the section to the section', () => {
    const short = zone({ triggerPoint: 1, distance: 1 })
    vi.spyOn(short.element, 'getBoundingClientRect').mockImplementation(
      () => ({ height: 300, top: 0 }) as DOMRect
    )

    scrollDriver.sync(short, 'default', 'alt')

    expect(range()).toBe('cover 0px cover 300px')
  })

  it('reverses the keyframes when scrubbing back to the default theme', () => {
    scrollDriver.sync(zone(), 'alt', 'default')

    expect(document.documentElement.classList.contains(CLASS_SCROLL_NATIVE)).toBe(true)
    expect(document.documentElement.classList.contains(CLASS_SCROLL_REVERSE)).toBe(true)
  })

  it('does nothing when the page already shows the state being scrubbed to', () => {
    scrollDriver.sync(zone(), 'alt', 'alt')

    expect(document.documentElement.classList.contains(CLASS_SCROLL_NATIVE)).toBe(false)
    expect(range()).toBe('')
  })

  /**
   * The name has to be unique in scope: if every zone declared it the
   * reference would be ambiguous, and an ambiguous timeline resolves to none.
   */
  it('names the timeline on the scrubbing element only, and cleans it up', () => {
    const scrubbing = zone()
    const other = zone()

    scrollDriver.sync(scrubbing, 'default', 'alt')

    expect(scrubbing.element.style.getPropertyValue('view-timeline')).toBe('--arts-cs-zone block')
    expect(other.element.style.getPropertyValue('view-timeline')).toBe('')

    scrollDriver.sync(null, 'default', 'alt')
    expect(scrubbing.element.style.getPropertyValue('view-timeline')).toBe('')
  })

  it('tears down when the coordinator hands over no zone', () => {
    scrollDriver.sync(zone(), 'default', 'alt')
    expect(range()).not.toBe('')

    scrollDriver.sync(null, 'default', 'alt')
    expect(range()).toBe('')
    expect(document.documentElement.classList.contains(CLASS_SCROLL_NATIVE)).toBe(false)
  })
})
