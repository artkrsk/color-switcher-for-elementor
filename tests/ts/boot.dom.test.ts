// @vitest-environment happy-dom

import { ATTR_PREFERENCE, ATTR_STATE, CONTRACT, COOKIE_PREFERENCE, SKINS } from '@ts/constants'
import { afterEach, describe, expect, it, vi } from 'vitest'

/**
 * The frontend entry, which is almost entirely wiring: which hooks get
 * registered, and which elements are allowed past the `data-settings` gate
 * before a handler is built for them. None of that was exercised — the only
 * thing reading this file was `phpParity.test.ts`, which greps it as TEXT to
 * check the gate's literals match PHP. That catches a renamed constant and
 * nothing else: the gate could invert and the regex would still match.
 *
 * What the coordinator then DOES with a zone belongs to `contract.dom.test.ts`,
 * which drives the singleton directly. This file stops at the boundary.
 */

interface IBooted {
  /** Every `element_ready` hook boot registered, by name. */
  actions: Map<string, (element: unknown) => void>
  /** Elements that made it as far as addHandler. */
  handled: HTMLElement[]
  /** Boot's own copy of the singleton, so a spy sees what the API calls. */
  coordinator: { refresh: () => void; destroy: () => void }
  /** Lets a test add hooks late and replay Elementor's init event. */
  frontend: Record<string, unknown>
}

/**
 * `elementorFrontend` and `jQuery` are bare identifiers in boot, so they have
 * to exist on globalThis before the import or the module body throws. The
 * DOM is seeded by the caller BEFORE this runs: boot's catch-up scans read the
 * document at import time.
 */
const setUpBoot = async (
  options: {
    /** Omit the hooks registry to model Elementor not having booted yet. */
    hooks?: boolean
    /** Without this, createZoneHandler() returns null and boot registers nothing. */
    modules?: boolean
    editMode?: boolean
  } = {}
): Promise<IBooted> => {
  const { hooks = true, modules = true, editMode = false } = options

  const actions = new Map<string, (element: unknown) => void>()
  const handled: HTMLElement[] = []

  const frontend: Record<string, unknown> = {
    isEditMode: () => editMode,
    elementsHandler: {
      addHandler: (_class: unknown, options: { $element: { get(index: number): HTMLElement } }) => {
        handled.push(options.$element.get(0))
      }
    }
  }

  if (hooks) {
    frontend.hooks = {
      addAction: (name: string, callback: (element: unknown) => void) => actions.set(name, callback)
    }
  }

  vi.stubGlobal('elementorFrontend', frontend)
  vi.stubGlobal('jQuery', (element: HTMLElement) => ({ get: () => element }))
  vi.stubGlobal(
    'elementorModules',
    modules ? { frontend: { handlers: { Base: class {} } } } : undefined
  )
  vi.stubGlobal('matchMedia', () => ({ matches: false, addEventListener: () => {} }))
  vi.stubGlobal(
    'IntersectionObserver',
    class {
      observe(): void {}
      unobserve(): void {}
      disconnect(): void {}
    }
  )

  // Boot holds module-level singletons, so the registry has to be dropped for
  // each boot. The coordinator is imported FIRST and out of the same fresh
  // registry — imported before the reset it would be a different instance from
  // the one boot goes on to use, and every spy would sit on the wrong object.
  vi.resetModules()

  const { coordinator } = await import('@ts/core/coordinator')
  await import('@ts/boot')

  return { actions, handled, coordinator, frontend }
}

/** An element shaped the way Elementor renders one, gate payload and all. */
const zone = (settings?: string): HTMLElement => {
  const element = document.createElement('div')

  element.className = 'elementor-element'
  element.setAttribute('data-element_type', 'container')

  if (settings !== undefined) {
    element.setAttribute('data-settings', settings)
  }

  document.body.appendChild(element)

  return element
}

afterEach(() => {
  // biome-ignore lint/suspicious/noDocumentCookie: clearing the fixture the same way the code under test writes it
  document.cookie = `${COOKIE_PREFERENCE}=;max-age=0;path=/`
  document.documentElement.removeAttribute(ATTR_STATE)
  document.documentElement.removeAttribute(ATTR_PREFERENCE)
  document.body.innerHTML = ''
})

describe('boot', () => {
  it('registers one zone hook per element type and one toggle hook per skin', async () => {
    const { actions } = await setUpBoot()

    // Elementor suffixes `data-widget_type` with the skin and fires the ready
    // hook under that name, so a skinned widget needs one registration each —
    // built from SKINS rather than restated, which is what stops a new skin
    // from being silently unhooked. `default` covers widgets saved before the
    // skins existed.
    expect([...actions.keys()].sort()).toEqual(
      [
        'frontend/element_ready/container',
        'frontend/element_ready/section',
        ...[...SKINS, 'default'].map(
          (skin) => `frontend/element_ready/arts-color-switcher-toggle.${skin}`
        )
      ].sort()
    )
  })

  it('registers nothing when Elementor has not booted its frontend', async () => {
    const { actions, frontend } = await setUpBoot({ hooks: false })

    expect(actions.size).toBe(0)

    // The event brings it back — a one-shot top-level call would have died
    // here, and AJAX routers re-run init() on every swap.
    frontend.hooks = {
      addAction: (name: string, callback: (element: unknown) => void) => actions.set(name, callback)
    }
    window.dispatchEvent(new Event('elementor/frontend/init'))

    expect(actions.size).toBe(7)
  })

  it('registers nothing when there is no handler class to build on', async () => {
    // createZoneHandler() reads elementorModules.frontend.handlers.Base and
    // returns null without it; boot must leave the page alone rather than
    // registering hooks that would throw on their first element.
    const { actions } = await setUpBoot({ modules: false })

    expect(actions.size).toBe(0)
  })

  describe('the data-settings gate', () => {
    /**
     * `addHandler` always constructs, so an element that can never be a zone
     * has to be turned away BEFORE the call. Outside the editor `data-settings`
     * is what a handler would read anyway, so one regex here spares a whole
     * handler and its listeners for every ordinary container on the page.
     */
    it('turns away an element whose settings do not enable a zone', async () => {
      const { actions, handled } = await setUpBoot()

      actions.get('frontend/element_ready/container')?.({
        get: () => zone('{"background_background":"classic"}')
      })

      expect(handled).toEqual([])
    })

    it('lets an enabled element through', async () => {
      const { actions, handled } = await setUpBoot()
      const element = zone('{"arts_cs_enabled":"switch"}')

      actions.get('frontend/element_ready/container')?.({ get: () => element })

      expect(handled).toEqual([element])
    })

    it('lets an element enabled at a custom breakpoint through', async () => {
      // Custom breakpoints suffix the key, so the gate cannot test for the
      // bare name — a zone switched on for `mobile_extra` only would be
      // dropped on every device including that one.
      const { actions, handled } = await setUpBoot()
      const element = zone('{"arts_cs_enabled_mobile_extra":"switch"}')

      actions.get('frontend/element_ready/container')?.({ get: () => element })

      expect(handled).toEqual([element])
    })

    it('attaches to everything in the editor, gate or no gate', async () => {
      // `data-settings` is written once by PHP and refreshed by nothing, so in
      // the editor it is stale the moment a control changes. There the live
      // model is the only truth and a handler on every element is the cost.
      const { actions, handled } = await setUpBoot({ editMode: true })
      const element = zone('{"background_background":"classic"}')

      actions.get('frontend/element_ready/container')?.({ get: () => element })

      expect(handled).toEqual([element])
    })
  })

  it('catches up on elements that were ready before it ran', async () => {
    // In the editor preview Elementor boots its frontend before this footer
    // script runs, so every existing element already fired its ready trigger
    // with no hook of ours registered.
    const early = zone('{"arts_cs_enabled":"switch"}')
    zone('{"background_background":"classic"}')

    const { handled } = await setUpBoot()

    expect(handled).toEqual([early])
  })

  it('publishes the contract and routes it to the coordinator', async () => {
    const { coordinator } = await setUpBoot()
    const refresh = vi.spyOn(coordinator, 'refresh')
    const destroy = vi.spyOn(coordinator, 'destroy')

    expect(window.ArtsColorSwitcher?.contract).toBe(CONTRACT)

    window.ArtsColorSwitcher?.refresh()
    expect(refresh).toHaveBeenCalled()

    window.ArtsColorSwitcher?.destroy()
    expect(destroy).toHaveBeenCalled()
  })

  it('tears the coordinator down before a transition swaps the page', async () => {
    // Both containers coexist during a transition, so the outgoing page's
    // zones must not race the incoming state. A bare string listener, so
    // nothing here depends on the transitions package.
    const { coordinator } = await setUpBoot()
    const destroy = vi.spyOn(coordinator, 'destroy')

    document.dispatchEvent(new Event('arts/ajax/transition/sync/before'))

    expect(destroy).toHaveBeenCalled()
  })
})
