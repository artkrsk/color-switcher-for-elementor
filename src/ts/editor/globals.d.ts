/**
 * Editor globals, typed via @artemsemkin/elementor-types.
 *
 * `elementor` and `wp` are only safe once the editor app has booted. This
 * bundle is enqueued with no dependencies — the family idiom, and what every
 * sibling plugin does — so it runs BEFORE that, where reading a bare binding
 * throws a ReferenceError rather than yielding undefined. Those two therefore
 * go through `window.` at module scope, which is a plain property read.
 *
 * `$e` is the exception and stays bare on purpose: `elementor-common` (which
 * depends on `elementor-web-cli`, where `window.$e` is assigned) is enqueued
 * at priority 9 of `elementor/editor/before_enqueue_scripts` and ours at 10,
 * while `elementor-editor` joins the queue only after that action returns.
 */
import type { $e as EDollar, ElementorEditor } from '@artemsemkin/elementor-types'

interface IMediaFrame {
  on(event: 'select', handler: () => void): void
  open(): void
  state(): {
    get(key: 'selection'): { first(): { toJSON(): { url?: string; id?: number } } | undefined }
  }
}

declare global {
  interface Window {
    /** Present only once the editor app has booted (`elementor/init`). */
    elementor?: ElementorEditor
    /** WordPress's media modal. Not covered by @artemsemkin/elementor-types. */
    wp?: {
      media(options: {
        title?: string
        multiple?: boolean
        library?: { type?: string }
      }): IMediaFrame
    }
  }

  const elementor: ElementorEditor
  const $e: EDollar
}
