// @vitest-environment happy-dom

import { ATTR_BASELINE, EVENT_SETTINGS_SYNC } from '@ts/constants'
import { beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The editor hook is the piece that keeps the preview honest, and the one
 * that already failed once in a way nothing caught: Elementor regenerates
 * CSS for our controls but calls no handler, so a control change left the
 * running zone on its boot-time settings. These tests hold both paths — the
 * page-theme call and the per-element sync event — to that lesson.
 */

interface IHookInstance {
  getCommand(): string
  getId(): string
  getConditions(args: unknown): boolean
  apply(args: unknown): void
  register(): void
}

interface IPreviewCalls {
  refreshes: number
}

const setUpEditor = async (): Promise<{
  hook: IHookInstance
  previewDocument: Document
  wrapper: HTMLElement
  calls: IPreviewCalls
}> => {
  const previewDocument = document.implementation.createHTMLDocument('preview')
  const registeredHooks: IHookInstance[] = []

  // The Elementor document wrapper, carrying what the server rendered. This
  // is the only surface the runtime re-reads, so the hook has to move it.
  const wrapper = previewDocument.createElement('div')
  wrapper.setAttribute(ATTR_BASELINE, 'auto')
  previewDocument.body.appendChild(wrapper)

  const calls: IPreviewCalls = { refreshes: 0 }

  class FakeAfter {
    register(): void {
      registeredHooks.push(this as unknown as IHookInstance)
    }
  }

  vi.stubGlobal('$e', { modules: { hookUI: { After: FakeAfter } } })
  vi.stubGlobal('elementor', {
    $preview: [
      {
        contentWindow: {
          ArtsColorSwitcher: {
            refresh: () => {
              calls.refreshes += 1
            }
          }
        },
        contentDocument: previewDocument
      }
    ]
  })

  vi.resetModules()
  await import('@ts/editor/index')

  const hook = registeredHooks[0]

  if (!hook) {
    throw new Error('the editor bundle registered no hook')
  }

  return { hook, previewDocument, wrapper, calls }
}

beforeEach(() => {
  vi.unstubAllGlobals()
})

describe('editor hook', () => {
  it('registers itself against the settings command', async () => {
    const { hook } = await setUpEditor()

    expect(hook.getCommand()).toBe('document/elements/settings')
    expect(hook.getId()).toBe('arts-cs-update-settings')
  })

  it('runs only for our own settings', async () => {
    const { hook } = await setUpEditor()

    expect(hook.getConditions({ settings: { arts_cs_theme: 'alt' } })).toBe(true)
    expect(hook.getConditions({ settings: { background_color: '#fff' } })).toBe(false)
    expect(hook.getConditions({})).toBe(false)
  })

  /**
   * The regression this replaced: the hook used to call `set()`, which assigns
   * the baseline past the attribute the runtime re-reads. The canvas looked
   * right until anything triggered a refresh, then reverted to whatever the
   * server had rendered.
   */
  it('moves the baseline attribute the runtime re-reads', async () => {
    const { hook, wrapper } = await setUpEditor()

    hook.apply({ container: { type: 'document' }, settings: { arts_cs_page_theme: 'alt' } })
    expect(wrapper.getAttribute(ATTR_BASELINE)).toBe('alt')

    hook.apply({ container: { type: 'document' }, settings: { arts_cs_page_theme: 'default' } })
    expect(wrapper.getAttribute(ATTR_BASELINE)).toBe('default')

    hook.apply({ container: { type: 'document' }, settings: { arts_cs_page_theme: '' } })
    expect(wrapper.getAttribute(ATTR_BASELINE)).toBe('auto')
  })

  it('asks the preview to re-resolve rather than assigning a theme', async () => {
    const { hook, calls } = await setUpEditor()

    hook.apply({ container: { type: 'document' }, settings: { arts_cs_page_theme: 'alt' } })

    expect(calls.refreshes).toBe(1)
  })

  it('dispatches a sync event on the changed element', async () => {
    const { hook, previewDocument } = await setUpEditor()
    const element = previewDocument.createElement('div')
    element.setAttribute('data-id', 'abc123')
    previewDocument.body.appendChild(element)

    const seen: string[] = []
    element.addEventListener(EVENT_SETTINGS_SYNC, () => seen.push('synced'))

    hook.apply({
      container: { type: 'element', id: 'abc123' },
      settings: { arts_cs_trigger_point: { unit: '%', size: 60 } }
    })

    expect(seen).toEqual(['synced'])
  })

  /**
   * Editing a control in the panel dispatches the plural form — it supports
   * multi-select. Reading only `container` made every panel edit a silent
   * no-op while programmatic calls kept working, which is exactly how this
   * shipped past a green suite once.
   */
  it('handles the plural containers the panel dispatches', async () => {
    const { hook, previewDocument, wrapper, calls } = await setUpEditor()
    const element = previewDocument.createElement('div')
    element.setAttribute('data-id', 'abc123')
    previewDocument.body.appendChild(element)

    const seen: string[] = []
    element.addEventListener(EVENT_SETTINGS_SYNC, () => seen.push('synced'))

    hook.apply({
      containers: [{ type: 'element', id: 'abc123' }],
      settings: { arts_cs_trigger_point: { unit: '%', size: 25 } }
    })
    hook.apply({
      containers: [{ type: 'document' }],
      settings: { arts_cs_page_theme: 'alt' }
    })

    expect(seen).toEqual(['synced'])
    expect(wrapper.getAttribute(ATTR_BASELINE)).toBe('alt')
    expect(calls.refreshes).toBe(1)
  })

  it('syncs every element of a multi-selection', async () => {
    const { hook, previewDocument } = await setUpEditor()
    const seen: string[] = []

    for (const id of ['one', 'two']) {
      const element = previewDocument.createElement('div')
      element.setAttribute('data-id', id)
      previewDocument.body.appendChild(element)
      element.addEventListener(EVENT_SETTINGS_SYNC, () => seen.push(id))
    }

    hook.apply({
      containers: [
        { type: 'element', id: 'one' },
        { type: 'element', id: 'two' }
      ],
      settings: { arts_cs_theme: 'alt' }
    })

    expect(seen).toEqual(['one', 'two'])
  })

  it('survives an element that is no longer in the preview', async () => {
    const { hook } = await setUpEditor()

    expect(() =>
      hook.apply({
        container: { type: 'element', id: 'gone' },
        settings: { arts_cs_trigger_point: { unit: '%', size: 60 } }
      })
    ).not.toThrow()

    expect(() =>
      hook.apply({
        container: { type: 'element' },
        settings: { arts_cs_trigger_point: { unit: '%', size: 60 } }
      })
    ).not.toThrow()
  })
})
