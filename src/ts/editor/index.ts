/**
 * Editor bundle — keeps the preview honest while editing.
 *
 * Two jobs, both on one hookUI.After over `document/elements/settings`
 * (modeled on Elementor's KitUpdateStretchContainer): apply a page-theme
 * change to the running preview, and tell a changed element's handler to
 * re-read its settings. The second is not optional — Elementor treats our
 * element controls as style-only, so it regenerates their CSS and notifies
 * no handler; without this the JS-side zone keeps the settings it booted
 * with while the CSS side has already moved on.
 *
 * Both drive the preview through the public contract and a DOM event; the
 * hook itself never touches preview state directly.
 */

import type { HookArgs } from '@artemsemkin/elementor-types'
import { ATTR_BASELINE, EVENT_SETTINGS_SYNC, SETTING_PAGE_THEME } from '../constants'
import type { IArtsColorSwitcher } from '../interfaces'
import { registerAltMediaView } from './altMedia'

const ELEMENT_SETTING_PREFIX = 'arts_cs_'

type TPreviewWindow = Window & { ArtsColorSwitcher?: IArtsColorSwitcher }

let registered = false

const previewFrame = (): HTMLIFrameElement | undefined => elementor.$preview?.[0]

/**
 * The command carries either shape, and which one is not cosmetic: editing a
 * control in the panel dispatches `containers` (it supports multi-select),
 * while programmatic calls usually pass a single `container`. Reading only
 * the singular form silently ignored every panel edit — the zone kept its
 * boot-time settings while the panel and the CSS had already moved on.
 */
const targets = (args: HookArgs): { type?: string; id?: string }[] => {
  if (args.containers?.length) {
    return args.containers
  }

  return args.container ? [args.container] : []
}

/**
 * The wrapper attribute's vocabulary, mirroring `Documents::baseline_name()`.
 * Auto is spelled out rather than left empty: "follow the device" and "light
 * for everyone" are different instructions, and an empty value cannot say
 * which one the author picked.
 */
const baselineName = (value: unknown): string => {
  if ('alt' === value) {
    return 'alt'
  }

  return 'default' === value ? 'default' : 'auto'
}

/**
 * Page baseline: move the attribute the runtime reads, then let it re-resolve.
 *
 * Calling `set()` alone looked right and was not durable. It assigns the
 * baseline directly, while `data-arts-cs-baseline` is server-rendered once and
 * never re-rendered while editing — so the next `refresh()` (a zone control
 * switched off, a toggle widget clicked in the canvas, DOMContentLoaded) read
 * the stale attribute back and reverted the canvas. Writing the attribute
 * makes the DOM the single source of truth the rest of the runtime already
 * treats it as, and leaves `refresh()` idempotent however often it runs.
 */
const applyPageTheme = (value: unknown): void => {
  const frame = previewFrame()
  const previewWindow = frame?.contentWindow as TPreviewWindow | undefined

  frame?.contentDocument
    ?.querySelector(`[${ATTR_BASELINE}]`)
    ?.setAttribute(ATTR_BASELINE, baselineName(value))

  previewWindow?.ArtsColorSwitcher?.refresh()
}

/** Element settings: the handler re-reads them off its own live model. */
const syncElement = (id: string | undefined): void => {
  if (!id) {
    return
  }

  previewFrame()
    ?.contentDocument?.querySelector(`[data-id="${id}"]`)
    ?.dispatchEvent(new CustomEvent(EVENT_SETTINGS_SYNC))
}

const register = (): void => {
  const AfterBase = $e?.modules?.hookUI?.After

  if (registered || !AfterBase) {
    return
  }

  registered = true

  class UpdateColorSwitcher extends AfterBase {
    getCommand(): string {
      return 'document/elements/settings'
    }

    getId(): string {
      return 'arts-cs-update-settings'
    }

    getConditions(args: HookArgs): boolean {
      const settings = args?.settings

      if (!settings) {
        return false
      }

      return Object.keys(settings).some((key) => key.startsWith(ELEMENT_SETTING_PREFIX))
    }

    apply(args: HookArgs): void {
      const settings = args.settings ?? {}

      for (const container of targets(args)) {
        if ('document' === container?.type && SETTING_PAGE_THEME in settings) {
          applyPageTheme(settings[SETTING_PAGE_THEME])

          continue
        }

        syncElement(container?.id)
      }
    }
  }

  // HookBase's constructor only records type/command/id — registering is
  // explicit (After.register() calls $e.hooks.registerUIAfter).
  const hook = new UpdateColorSwitcher()

  hook.register()
}

window.addEventListener('elementor/init', register)

if ($e?.modules?.hookUI?.After) {
  register()
}

/**
 * The control view gets its own trigger, deliberately.
 *
 * It needs `elementor`, which this bundle can load before — the hook above
 * needs only `$e`, which is there earlier. Sharing the hook's one-shot guard
 * meant the eager call consumed it while the editor app was still missing, so
 * the view was never registered and the hook never recovered either.
 */
window.addEventListener('elementor/init', registerAltMediaView)

registerAltMediaView()
