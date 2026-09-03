// @vitest-environment happy-dom

import { ALT_KEY_ID, ALT_KEY_URL, ALT_SETTING_LABELS, CONTROL_TYPE_ALT_MEDIA } from '@ts/constants'
import { registerAltMediaView } from '@ts/editor/altMedia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The Alt picker lives INSIDE Elementor's media control rather than beside
 * it, so what these hold is the injection: the button lands in the stock
 * tools row, next to the stock tool, and re-rendering never stacks copies of
 * it. Elementor's control views are Marionette classes we cannot boot here,
 * so the fake stands in for exactly the surface the subclass touches.
 */

const LABELS = {
  choose: 'Choose Alt Image',
  set: 'Alt Image is Set',
  remove: 'Remove Alt Image',
  frame: 'Select Alt Image'
}

interface IAltView {
  el: HTMLElement
  render(): unknown
  onAltSelected(attachment: { url?: string; id?: number }): void
  onAltRemove(): void
}

const markup = `
  <div class="elementor-control-media__content elementor-control-preview-area">
    <div class="elementor-control-media-area">
      <div class="elementor-control-media__remove elementor-control-media__content__remove"></div>
      <div class="elementor-control-media__preview"></div>
    </div>
    <div class="elementor-control-media__tools elementor-control-dynamic-switcher-wrapper">
      <div class="elementor-control-media__tool elementor-control-media__replace" data-media-type="image">Choose Image</div>
    </div>
  </div>
`

const setUpEditor = () => {
  const registered: Record<string, unknown> = {}
  const values: Record<string, unknown> = {}

  class FakeMediaView {
    el: HTMLElement = document.createElement('div')
    model = { get: (key: string) => (ALT_SETTING_LABELS === key ? LABELS : undefined) }

    constructor() {
      this.el.innerHTML = markup
    }

    render(): this {
      return this
    }

    getControlValue(): Record<string, unknown> {
      return values
    }

    setValue(patch: Record<string, unknown>): void {
      Object.assign(values, patch)
    }
  }

  vi.stubGlobal('elementor', {
    modules: { controls: { Media: FakeMediaView } },
    addControlView: (type: string, view: unknown) => {
      registered[type] = view
    }
  })

  return { registered, values }
}

const build = (registered: Record<string, unknown>): IAltView => {
  const View = registered[CONTROL_TYPE_ALT_MEDIA] as new () => IAltView
  const view = new View()

  view.render()

  return view
}

const tools = (view: IAltView): string[] =>
  Array.from(
    view.el.querySelectorAll('.elementor-control-media__tools .elementor-control-media__tool')
  ).map((node) => node.className.replace('elementor-control-media__tool ', ''))

const hasAltRemove = (view: IAltView): boolean =>
  null !== view.el.querySelector('.elementor-control-media-area .arts-cs-alt__remove')

/** Ours must sit immediately after the stock remove, mirroring its slot. */
const altRemoveFollowsStock = (view: IAltView): boolean =>
  view.el
    .querySelector('.elementor-control-media__content__remove:not(.arts-cs-alt__remove)')
    ?.nextElementSibling?.classList.contains('arts-cs-alt__remove') ?? false

describe('alt media control view', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  it('registers under its own type, never over the stock media type', () => {
    const { registered } = setUpEditor()

    registerAltMediaView()

    expect(registered[CONTROL_TYPE_ALT_MEDIA]).toBeTypeOf('function')
    expect(registered.media).toBeUndefined()
  })

  it('does nothing when the stock media view is absent', () => {
    vi.stubGlobal('elementor', { modules: { controls: {} }, addControlView: vi.fn() })

    expect(() => registerAltMediaView()).not.toThrow()
  })

  /**
   * The editor bundle is enqueued with no dependencies, so it runs before
   * the editor app exists. Reading the bare `elementor` binding throws a
   * ReferenceError there — optional chaining guards a null value, never an
   * undeclared one — and that took the settings hook down with it.
   */
  it('does nothing when the editor app has not booted yet', () => {
    expect(() => registerAltMediaView()).not.toThrow()
  })

  /** Beside the stock tool, not underneath the control. */
  it('injects its tool into the stock tools row, after the stock tool', () => {
    const { registered } = setUpEditor()
    registerAltMediaView()

    const view = build(registered)

    expect(tools(view)).toEqual(['elementor-control-media__replace', 'arts-cs-alt__choose'])
    expect(view.el.querySelector('.arts-cs-alt__choose')?.textContent).toBe(LABELS.choose)
  })

  it('writes both alt sub-keys when a selection is made', () => {
    const { registered, values } = setUpEditor()
    registerAltMediaView()

    build(registered).onAltSelected({ url: 'https://example.test/logo-alt.png', id: 42 })

    expect(values[ALT_KEY_URL]).toBe('https://example.test/logo-alt.png')
    expect(values[ALT_KEY_ID]).toBe(42)
  })

  /** In the media area, where it inherits the stock remove's corner slot. */
  it('offers a remove tool only once an alt is set, and clears both keys', () => {
    const { registered, values } = setUpEditor()
    registerAltMediaView()

    const view = build(registered)
    expect(hasAltRemove(view)).toBe(false)

    view.onAltSelected({ url: 'https://example.test/logo-alt.png', id: 42 })
    expect(hasAltRemove(view)).toBe(true)
    expect(altRemoveFollowsStock(view)).toBe(true)
    expect(view.el.querySelector('.arts-cs-alt__choose')?.textContent).toBe(LABELS.set)

    view.onAltRemove()
    expect(hasAltRemove(view)).toBe(false)
    expect(values[ALT_KEY_URL]).toBe('')
    expect(values[ALT_KEY_ID]).toBe('')
  })

  /**
   * The whole preview area is the parent view's own frame opener, and our
   * buttons sit inside it — so a press used to open the stock picker
   * underneath ours, two media modals deep.
   */
  it('does not let a press reach the parent control frame opener', () => {
    const { registered } = setUpEditor()
    registerAltMediaView()

    const view = build(registered)
    view.onAltSelected({ url: 'https://example.test/logo-alt.png', id: 42 })

    const openStockFrame = vi.fn()
    view.el
      .querySelector('.elementor-control-preview-area')
      ?.addEventListener('click', openStockFrame)

    for (const selector of ['.arts-cs-alt__choose', '.arts-cs-alt__remove']) {
      view.el.querySelector(selector)?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    }

    expect(openStockFrame).not.toHaveBeenCalled()
  })

  /** The control re-renders on every value change. */
  it('does not stack duplicate tools across renders', () => {
    const { registered } = setUpEditor()
    registerAltMediaView()

    const view = build(registered)
    view.render()
    view.render()

    expect(view.el.querySelectorAll('.arts-cs-alt__choose')).toHaveLength(1)
  })
})
