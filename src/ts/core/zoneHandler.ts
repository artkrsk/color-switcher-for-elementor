import {
  ENABLED_SWITCH,
  EVENT_SETTINGS_SYNC,
  SETTING_ENABLED,
  SETTING_VIEWPORT
} from '../constants'
import type { IViewportValue, IZone } from '../interfaces'
import { coordinator } from './coordinator'

/**
 * Build the handler class lazily: elementorModules only exists once
 * Elementor's frontend bundle has booted, which is why this is a factory
 * rather than a top-level class. Returns null if that never happened.
 */
export const createZoneHandler = (): unknown => {
  const Base = elementorModules?.frontend?.handlers?.Base

  if (!Base) {
    return null
  }

  return class ZoneHandler extends Base {
    private resizeFrame = 0

    /**
     * We subscribe as an EventListener object rather than with callback
     * properties: Elementor's Base calls onInit() from its own constructor,
     * which runs BEFORE this class's field initializers, so any arrow-function
     * field would still be undefined at subscribe time and the listener would
     * be silently dropped. A prototype method always exists.
     *
     * Resize is here, not in the coordinator, because a zone that switches
     * itself off at a narrow breakpoint leaves the coordinator with nothing to
     * watch — and then nothing could bring it back on a wider screen.
     */
    handleEvent(event: Event): void {
      if ('resize' !== event.type) {
        this.registerZone()

        return
      }

      if (this.resizeFrame) {
        return
      }

      this.resizeFrame = window.requestAnimationFrame(() => {
        this.resizeFrame = 0
        this.registerZone()
      })
    }

    onInit(...args: unknown[]): void {
      super.onInit(...args)
      this.element()?.addEventListener(EVENT_SETTINGS_SYNC, this)
      window.addEventListener('resize', this, { passive: true })
      this.registerZone()
    }

    /**
     * Fires for behavior controls; ours are style-only as far as Elementor is
     * concerned, so the editor bundle's explicit sync event is what actually
     * reaches us. Kept because it costs nothing and covers the other case.
     */
    onElementChange(setting: string): void {
      if (setting.startsWith('arts_cs_')) {
        this.registerZone()
      }
    }

    onDestroy(): void {
      const element = this.element()

      window.removeEventListener('resize', this)
      window.cancelAnimationFrame(this.resizeFrame)

      if (element) {
        element.removeEventListener(EVENT_SETTINGS_SYNC, this)
        coordinator.unregister(element)
      }
    }

    private element(): HTMLElement | undefined {
      return this.$element?.get(0)
    }

    private registerZone(): void {
      const element = this.element()

      if (!element) {
        return
      }

      // Per breakpoint, with Elementor's own inheritance: a narrower device
      // falls back to the wider one unless it carries a value of its own.
      if (ENABLED_SWITCH !== this.getCurrentDeviceSetting(SETTING_ENABLED)) {
        // Only when this element really was a zone: refresh() re-reads the
        // baseline off the document, and the branch is also the resting state
        // of every section that is simply switched off at this breakpoint.
        if (coordinator.unregister(element)) {
          coordinator.refresh()
        }

        return
      }

      const { start, end } = this.readViewport()

      const zone: IZone = {
        element,
        // The handles are read bottom-up (0% is the viewport bottom), while
        // the runtime measures lines from the top — hence the inversion.
        triggerPoint: 1 - start / 100,
        // The span between the handles is how much of the viewport the change
        // is spread over; handles together mean an instant switch.
        distance: (end - start) / 100
      }

      coordinator.register(zone)
    }

    /** Defaults match the control: both handles at the top of the viewport. */
    private readViewport(): { start: number; end: number } {
      const value = this.getElementSettings(SETTING_VIEWPORT) as IViewportValue | undefined
      const clamp = (raw: unknown, fallback: number): number => {
        const size = Number(raw)

        return Number.isFinite(size) ? Math.min(Math.max(size, 0), 100) : fallback
      }

      const start = clamp(value?.sizes?.start, 100)
      const end = clamp(value?.sizes?.end, 100)

      // A range control can hand the handles over in either order.
      return { start: Math.min(start, end), end: Math.max(start, end) }
    }
  }
}
