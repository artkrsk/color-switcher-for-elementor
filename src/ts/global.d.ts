import type { ElementorFrontend, ElementorModules } from '@artemsemkin/elementor-types'
import type { IArtsColorSwitcher } from './interfaces'

declare global {
  interface Window {
    /** Public integration surface — see the Integration contract in README.md. Created by this bundle. */
    ArtsColorSwitcher?: IArtsColorSwitcher
    /**
     * Published by the shared arts/scroll-timeline-polyfill loader, which our
     * script handle depends on. Settles 'native' | 'polyfilled' | 'unavailable';
     * never rejects.
     */
    __artsScrollTimelinePolyfillReady?: Promise<string>
    /** Native where supported, otherwise installed by that polyfill. */
    ViewTimeline?: new (options: {
      subject: Element
      axis?: string
      inset?: string
    }) => AnimationTimeline
    /**
     * Arts Horizontal Scroll's public surface (contract 1). Present only when
     * that plugin is active; `getTimeline` returns the pinned section's
     * timeline — native or polyfilled — or null outside a booted section.
     */
    ARTS_HS?: {
      contract?: number
      getTimeline?: (el: Element) => AnimationTimeline | null
    }
    elementorFrontend?: ElementorFrontend
    elementorModules?: ElementorModules
  }

  const elementorFrontend: ElementorFrontend
  const elementorModules: ElementorModules
}
