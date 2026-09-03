/**
 * WordPress entry — side-effect IIFE built by arts-wp into
 * src/php/libraries/color-switcher-for-elementor/color-switcher-for-elementor.js.
 */

import {
  CONTRACT,
  ENABLED_SWITCH,
  EVENT_AJAX_SYNC_BEFORE,
  SETTING_ENABLED,
  SKINS
} from './constants'
import { coordinator } from './core/coordinator'
import { current, watchSystem, write } from './core/preference'
import { attachToggle } from './core/toggle'
import { createZoneHandler } from './core/zoneHandler'
import type { IArtsColorSwitcher } from './interfaces'

declare const __ARTS_COLOR_SWITCHER_VERSION__: string

/** Elements that can carry a zone, as Elementor marks them in the DOM. */
const ZONE_SELECTOR =
  '.elementor-element[data-element_type="container"], .elementor-element[data-element_type="section"]'

/** Rendered identically by the widget and the shortcode. */
const TOGGLE_SELECTOR = '.js-arts-cs-toggle'

/** Elementor suffixes `data-widget_type` with the skin, and fires the ready
 * hook under that name — so a skinned widget needs one registration each.
 * `default` covers a widget saved before it had skins. */
const TOGGLE_WIDGET = 'arts-color-switcher-toggle'

/**
 * The enabled control's "on" value inside a rendered `data-settings` payload,
 * at any breakpoint — custom breakpoints suffix the key (`_mobile_extra`,
 * `_widescreen`). Built from the constants so a rename cannot silently
 * un-gate the boot instead of failing loudly.
 */
const ENABLED_IN_SETTINGS = new RegExp(`"${SETTING_ENABLED}(?:_[a-z_]+)?":"${ENABLED_SWITCH}"`)

const registerHooks = (): void => {
  const handlerClass = createZoneHandler()

  if (!handlerClass) {
    return
  }

  /**
   * Outside the editor `data-settings` IS what a handler would read anyway —
   * Elementor's frontend Base resolves settings through
   * `$element.data('settings')` — so one regex here spares a whole handler,
   * its two listeners and a resolve pass for every container that can never
   * be a zone. addHandler always constructs, so gating has to happen here.
   *
   * Never in the editor: that attribute is written once by PHP at render time
   * and by nothing else in Elementor — no editor code path refreshes it — so
   * a zone switched on in the panel would be judged on a stale payload and
   * never attach. There the live model is the only truth, and a handler on
   * every element is the cost of editing.
   */
  const attach = ($element: JQuery): void => {
    const element = $element.get(0)

    if (
      element &&
      !elementorFrontend.isEditMode() &&
      !ENABLED_IN_SETTINGS.test(element.dataset.settings ?? '')
    ) {
      return
    }

    elementorFrontend.elementsHandler.addHandler(handlerClass, {
      $element: $element as JQuery<HTMLElement>
    })
  }

  // elementorFrontend.init() rebuilds the hooks registry on every run (AJAX
  // routers re-invoke it), so registration MUST live in this persistent
  // listener — a one-shot top-level addAction dies on the first swap.
  elementorFrontend.hooks.addAction('frontend/element_ready/container', attach)
  elementorFrontend.hooks.addAction('frontend/element_ready/section', attach)

  // The toggle is not a zone: no data-settings gate, and it attaches in the
  // editor too, where clicking it is how an author previews the Alt palette.
  const attachToggles = ($scope: JQuery): void => {
    for (const button of $scope.get(0)?.querySelectorAll<HTMLElement>(TOGGLE_SELECTOR) ?? []) {
      attachToggle(button)
    }
  }

  for (const skin of [...SKINS, 'default']) {
    elementorFrontend.hooks.addAction(
      `frontend/element_ready/${TOGGLE_WIDGET}.${skin}`,
      attachToggles
    )
  }

  // Catch up on anything element_ready already passed over. In the editor
  // preview Elementor boots its frontend before this footer script runs, so
  // every existing element had its ready trigger fired with no hook of ours
  // registered — the zones would stay dead until something re-rendered them.
  // addHandler fingerprints by element, so re-attaching is a no-op.
  for (const element of document.querySelectorAll<HTMLElement>(ZONE_SELECTOR)) {
    attach(jQuery(element))
  }

  // Shortcode-rendered toggles never fire element_ready, and the same
  // catch-up covers the editor's already-booted preview.
  for (const button of document.querySelectorAll<HTMLElement>(TOGGLE_SELECTOR)) {
    attachToggle(button)
  }

  coordinator.init()
}

const bootWhenFrontendReady = (): void => {
  // `hooks` only exists once init() has run; before that there is nothing to
  // register against, and the event below will bring us back.
  if (window.elementorFrontend?.hooks) {
    registerHooks()
  }
}

window.addEventListener('elementor/frontend/init', bootWhenFrontendReady)
bootWhenFrontendReady()

// AJAX routers replay the native lifecycle for third-party scripts; the
// baseline may have changed with the swapped content.
document.addEventListener('DOMContentLoaded', () => coordinator.refresh())
window.addEventListener('pageshow', (event) => {
  if (event.persisted) {
    coordinator.refresh()
  }
})

// Deterministic pre-swap teardown: during a transition both containers
// coexist and the outgoing page's zones must not race the incoming state.
// A bare string listener — no dependency on the transitions package.
document.addEventListener(EVENT_AJAX_SYNC_BEFORE, () => coordinator.destroy())

// A device change re-resolves rather than forcing a theme: refresh() runs the
// same precedence table as first paint, so an explicitly light page stays
// light and the editor canvas keeps showing what the author built.
watchSystem(() => coordinator.refresh())

const api: IArtsColorSwitcher = {
  contract: CONTRACT,
  refresh: () => coordinator.refresh(),
  set: (theme) => coordinator.set(theme),
  setPreference: (preference) => {
    write(preference)
    coordinator.refresh()
  },
  getPreference: () => current(),
  getTheme: () => coordinator.getTheme(),
  getProgress: () => coordinator.getProgress(),
  destroy: () => coordinator.destroy()
}

window.ArtsColorSwitcher = api

if (import.meta.env?.DEV) {
  console.debug(`color-switcher-for-elementor ${__ARTS_COLOR_SWITCHER_VERSION__} booting`)
}
