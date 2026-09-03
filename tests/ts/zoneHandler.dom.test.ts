// @vitest-environment happy-dom

import { EVENT_SETTINGS_SYNC } from '@ts/constants'
import { coordinator } from '@ts/core/coordinator'
import { createZoneHandler } from '@ts/core/zoneHandler'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The handler is where Elementor's control values become zones, and its
 * settings-reading is all edge cases: sliders arrive as objects, units vary,
 * and the editor changes them under a live handler. Nothing else exercises
 * that translation — the e2e specs only see its outcome.
 */

interface IHandlerInstance {
  onDestroy(): void
  onElementChange(setting: string): void
}

const registered: unknown[] = []
const unregistered: HTMLElement[] = []
/** What the coordinator would actually be holding, so unregister() can report. */
const held = new Set<HTMLElement>()
let refreshed = 0

/** Minimal stand-in for elementorModules.frontend.handlers.Base. */
class FakeBase {
  $element: { get(index: number): HTMLElement | undefined }
  private settings: Record<string, unknown>

  constructor(options: { $element: HTMLElement; settings: Record<string, unknown> }) {
    this.$element = { get: () => options.$element }
    this.settings = options.settings

    // Elementor's Base calls onInit() from its own constructor, before a
    // subclass's field initializers run. Handlers that bind listeners to
    // class fields silently bind `undefined` — reproduce that ordering.
    ;(this as unknown as { onInit(): void }).onInit()
  }

  onInit(): void {}

  /** Elementor returns one value for a key, the whole object without one. */
  getElementSettings(key?: string): unknown {
    return key ? this.settings[key] : this.settings
  }

  /** Stands in for Elementor's per-breakpoint accessor. */
  getCurrentDeviceSetting(key: string): unknown {
    return this.settings[key]
  }
}

// Handlers listen on window, so a leaked one keeps reacting in later tests.
const handlers: IHandlerInstance[] = []

const makeHandler = (element: HTMLElement, settings: Record<string, unknown>): IHandlerInstance => {
  const HandlerClass = createZoneHandler() as new (options: {
    $element: HTMLElement
    settings: Record<string, unknown>
  }) => IHandlerInstance

  const handler = new HandlerClass({ $element: element, settings })
  handlers.push(handler)

  return handler
}

const zoneElement = (): HTMLElement => {
  const element = document.createElement('div')
  document.body.appendChild(element)

  return element
}

beforeEach(() => {
  registered.length = 0
  unregistered.length = 0
  held.clear()
  refreshed = 0

  vi.stubGlobal(
    'IntersectionObserver',
    class {
      observe(): void {}
      unobserve(): void {}
      disconnect(): void {}
    }
  )
  vi.stubGlobal('elementorModules', { frontend: { handlers: { Base: FakeBase } } })
  vi.spyOn(coordinator, 'register').mockImplementation((zone) => {
    registered.push(zone)
    held.add(zone.element)
  })
  vi.spyOn(coordinator, 'unregister').mockImplementation((element) => {
    unregistered.push(element)

    return held.delete(element)
  })
  vi.spyOn(coordinator, 'refresh').mockImplementation(() => {
    refreshed++
  })
})

afterEach(() => {
  for (const handler of handlers) {
    handler.onDestroy()
  }

  handlers.length = 0
  document.body.innerHTML = ''
})

describe('zone handler', () => {
  it('does not build a class when Elementor has not booted', () => {
    vi.stubGlobal('elementorModules', undefined)

    expect(createZoneHandler()).toBeNull()
  })

  it('registers a zone from the enabled control and the viewport range', () => {
    const element = zoneElement()

    makeHandler(element, {
      arts_cs_enabled: 'switch',
      arts_cs_viewport: { unit: '%', sizes: { start: 40, end: 70 } }
    })

    // Handles read bottom-up; the runtime measures lines from the top.
    expect(registered).toEqual([{ element, triggerPoint: 0.6, distance: 0.3 }])
  })

  it('treats handles at the same place as an instant switch', () => {
    makeHandler(zoneElement(), {
      arts_cs_enabled: 'switch',
      arts_cs_viewport: { unit: '%', sizes: { start: 100, end: 100 } }
    })

    expect(registered[0]).toMatchObject({ triggerPoint: 0, distance: 0 })
  })

  it('unregisters when the section is not marked', () => {
    const element = zoneElement()

    makeHandler(element, { arts_cs_enabled: 'none' })

    expect(registered).toEqual([])
    expect(unregistered).toEqual([element])
  })

  /**
   * The reason the control is a two-value select: a breakpoint that says
   * 'none' has to stop Elementor's inheritance walk, or it would fall back to
   * the wider breakpoint's 'switch'.
   */
  it('stays off at a breakpoint that says none', () => {
    const element = zoneElement()

    makeHandler(element, { arts_cs_enabled: 'none' })

    expect(registered).toEqual([])
  })

  /**
   * Off at this breakpoint is the resting state of every unmarked section, and
   * re-resolving there re-reads the baseline off the whole document. Only a
   * zone actually taken back is worth resolving for.
   */
  it('does not re-resolve for a section that was never a zone', () => {
    makeHandler(zoneElement(), { arts_cs_enabled: 'none' })

    expect(refreshed).toBe(0)
  })

  it('re-resolves when a live zone switches itself off', () => {
    const element = zoneElement()
    const settings: Record<string, unknown> = { arts_cs_enabled: 'switch' }
    makeHandler(element, settings)

    settings.arts_cs_enabled = 'none'
    element.dispatchEvent(new CustomEvent(EVENT_SETTINGS_SYNC))

    expect(refreshed).toBe(1)
  })

  it('falls back to an instant switch at the top when the range is unusable', () => {
    for (const value of [undefined, {}, { sizes: {} }, { sizes: { start: 'x', end: 'y' } }]) {
      makeHandler(zoneElement(), {
        arts_cs_enabled: 'switch',
        arts_cs_viewport: value
      })
    }

    expect(registered.map((zone) => (zone as { triggerPoint: number }).triggerPoint)).toEqual([
      0, 0, 0, 0
    ])
  })

  it('accepts the handles in either order', () => {
    makeHandler(zoneElement(), {
      arts_cs_enabled: 'switch',
      arts_cs_viewport: { sizes: { start: 80, end: 20 } }
    })

    expect(registered[0]).toMatchObject({ triggerPoint: 0.8, distance: 0.6 })
  })

  /**
   * The editor path: Elementor regenerates CSS for these controls but calls
   * no handler, so the editor bundle dispatches this event instead.
   */
  it('re-registers when the editor dispatches a settings sync', () => {
    const element = zoneElement()
    makeHandler(element, { arts_cs_enabled: 'switch' })
    expect(registered).toHaveLength(1)

    element.dispatchEvent(new CustomEvent(EVENT_SETTINGS_SYNC))
    expect(registered).toHaveLength(2)
  })

  it('stops listening and unregisters on destroy', () => {
    const element = zoneElement()
    const handler = makeHandler(element, { arts_cs_enabled: 'switch' })
    handler.onDestroy()

    expect(unregistered).toEqual([element])

    element.dispatchEvent(new CustomEvent(EVENT_SETTINGS_SYNC))
    expect(registered).toHaveLength(1)
  })

  /**
   * A zone that switches itself off at a narrow breakpoint must be able to
   * come back on a wider one. The handler watches resize for that reason: the
   * coordinator cannot, because an unregistered zone leaves it with nothing
   * to watch.
   */
  it('comes back when the breakpoint widens again', () => {
    const element = zoneElement()
    const settings: Record<string, unknown> = { arts_cs_enabled: 'none' }
    makeHandler(element, settings)

    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      callback(0)

      return 1
    })
    expect(registered).toHaveLength(0)

    settings.arts_cs_enabled = 'switch'
    window.dispatchEvent(new Event('resize'))

    expect(registered).toHaveLength(1)
  })

  it('stops watching resize once destroyed', () => {
    const element = zoneElement()
    const handler = makeHandler(element, { arts_cs_enabled: 'switch' })

    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      callback(0)

      return 1
    })
    vi.stubGlobal('cancelAnimationFrame', () => {})
    handler.onDestroy()
    window.dispatchEvent(new Event('resize'))

    expect(registered).toHaveLength(1)
  })

  it('reacts to our own controls through onElementChange, ignoring others', () => {
    const handler = makeHandler(zoneElement(), { arts_cs_enabled: 'switch' })
    handler.onElementChange('background_color')
    expect(registered).toHaveLength(1)

    handler.onElementChange('arts_cs_viewport')
    expect(registered).toHaveLength(2)
  })
})
