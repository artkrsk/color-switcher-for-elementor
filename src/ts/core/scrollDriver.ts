import {
  CLASS_SCROLL_NATIVE,
  CLASS_SCROLL_REVERSE,
  HS_READY_EVENT,
  HS_VAR_PANEL_END,
  HS_VAR_PANEL_START,
  HS_WRAPPER_SELECTOR,
  VAR_ADMIN_BAR,
  VAR_PROGRESS
} from '../constants'
import type { IZone } from '../interfaces'
import type { TTheme } from '../types'
import { pinnedTrackOf } from './horizontal'

/**
 * Computed BEFORE the polyfill loads: once installed it monkeypatches
 * CSS.supports, and a later probe would misreport native support.
 */
const SUPPORTS_NATIVE =
  typeof CSS !== 'undefined' &&
  CSS.supports('view-timeline: --probe block') &&
  CSS.supports('animation-timeline: --probe') &&
  CSS.supports('animation-range: contain 0% contain 100%') &&
  CSS.supports('timeline-scope: --probe')

/**
 * Drives the ONE scroll-bound zone a page may have (v1 constraint: every
 * tier holds its edge values via `fill: both`, which only composes for a
 * single animation; extra scroll zones are inert and documented as such).
 *
 * Three tiers, in the order `install()` tries them. Arts Horizontal Scroll's
 * published timeline first, because it is the only one that can describe a
 * panel travelling sideways. Then native CSS: add the class, set the range,
 * done; the browser owns the scrub with zero main-thread JS. Then WAAPI
 * against the polyfill's (or native) ViewTimeline, animating the same scalar
 * on the same element.
 */
class ScrollDriver {
  private animation: Animation | null = null
  private activeZone: IZone | null = null
  private activeFrom: TTheme = 'default'
  private activeTo: TTheme = 'alt'
  private scheduled = 0
  private resizeTimer = 0
  private releaseFrame = 0
  private awaitingBoot = new WeakSet<HTMLElement>()

  constructor() {
    window.addEventListener('resize', () => {
      window.clearTimeout(this.resizeTimer)
      this.resizeTimer = window.setTimeout(() => this.rebuildAfterResize(), 250)
    })
  }

  /**
   * The WAAPI tier bakes the length→percentage conversion at setup, and a
   * horizontally pinned track can change state entirely (their engine goes
   * vertical on touch and narrow breakpoints), so both are rebuilt on resize.
   * The native vertical tier consumes real units and needs no rebuild.
   */
  private rebuildAfterResize(): void {
    if (this.activeZone && (!SUPPORTS_NATIVE || pinnedTrackOf(this.activeZone.element))) {
      this.reinstall()
    }
  }

  /** Tear down and set the same zone up again — resize, and their late boot. */
  private reinstall(): void {
    const zone = this.activeZone
    const from = this.activeFrom
    const to = this.activeTo

    if (!zone) {
      return
    }

    this.teardown()
    this.activeZone = zone
    this.activeFrom = from
    this.activeTo = to
    this.install(zone, from, to)
  }

  /**
   * The coordinator hands over the scroll-bound zone that currently owns the
   * scalar, or null when the binary state does. Coalesced: registration
   * bursts (editor re-renders, initial scan) settle into one apply.
   */
  sync(zone: IZone | null, from: TTheme, to: TTheme): void {
    window.cancelAnimationFrame(this.scheduled)
    this.scheduled = window.requestAnimationFrame(() => this.apply(zone, from, to))
  }

  teardown(): void {
    window.cancelAnimationFrame(this.scheduled)

    // Hand the scrubbed value back to the cascade rather than dropping it.
    // Removing an animation restores the base value within the same frame and
    // starts no transition, so a section leaving mid-scrub cut to the baseline
    // instantly. Pinning the held value inline and releasing it one frame
    // later gives the body transition something to interpolate from, so
    // leaving a scroll-bound section looks like leaving an instant one.
    this.release(this.activeZone ? this.heldProgress() : '')

    this.animation?.cancel()
    this.animation = null
    this.activeZone?.element.style.removeProperty('view-timeline')
    this.activeZone = null

    const html = document.documentElement
    html.classList.remove(CLASS_SCROLL_NATIVE, CLASS_SCROLL_REVERSE)
    document.body.style.removeProperty('animation-range')
  }

  private heldProgress(): string {
    return getComputedStyle(document.body).getPropertyValue(VAR_PROGRESS).trim()
  }

  private release(held: string): void {
    if (!held) {
      // Nothing to pin — and a pending removal, if any, must still land.
      // Cancelling it here let a second teardown in the same frame batch
      // strand the pinned value inline on body forever, freezing every color
      // at the held mid-mix value.
      return
    }

    window.cancelAnimationFrame(this.releaseFrame)
    document.body.style.setProperty(VAR_PROGRESS, held)
    this.releaseFrame = window.requestAnimationFrame(() => {
      document.body.style.removeProperty(VAR_PROGRESS)
    })
  }

  private apply(zone: IZone | null, from: TTheme, to: TTheme): void {
    // Scrubbing toward the state the page already shows is a no-op.
    if (!zone || from === to) {
      this.teardown()

      return
    }

    if (this.activeZone && this.isSameSetup(zone) && from === this.activeFrom) {
      return
    }

    this.teardown()
    this.activeZone = zone
    this.activeFrom = from
    this.activeTo = to
    this.install(zone, from, to)
  }

  /**
   * A zone pinned inside a horizontally scrolling track is the first case,
   * because our own view-timeline cannot describe it: the panel's vertical
   * position is frozen while it is pinned, so a block-axis scrub would finish
   * during the section's entry and then hold flat for the whole traversal.
   * Arts Horizontal Scroll publishes the timeline that DOES describe it.
   */
  private install(zone: IZone, from: TTheme, to: TTheme): void {
    const timeline = this.horizontalTimeline(zone)

    if (timeline) {
      this.applyHorizontal(zone, from, to, timeline)

      return
    }

    if (SUPPORTS_NATIVE) {
      this.applyNative(zone, to)

      return
    }

    void this.applyWaapi(zone, from, to)
  }

  /**
   * Their committed JS path, one implementation across tiers. Gated on their
   * documented state probe — a `sticky` track means the horizontal engine is
   * actually running, `static` means a vertical state — because a WAAPI
   * animation would otherwise bypass the CSS gate that turns their engine off.
   */
  private horizontalTimeline(zone: IZone): AnimationTimeline | null {
    const track = pinnedTrackOf(zone.element)

    if (!track) {
      return null
    }

    const timeline = window.ARTS_HS?.getTimeline?.(zone.element) ?? null
    const wrapper = track.closest<HTMLElement>(HS_WRAPPER_SELECTOR)

    if (!timeline && wrapper && !this.awaitingBoot.has(wrapper)) {
      // Their engine boots from its own element_ready hook, which can land
      // after ours. Once per wrapper, so repeated installs (every resize, and
      // a breakpoint flip back to horizontal) cannot stack listeners.
      this.awaitingBoot.add(wrapper)
      wrapper.addEventListener(
        HS_READY_EVENT,
        () => {
          this.awaitingBoot.delete(wrapper)

          if (this.activeZone && pinnedTrackOf(this.activeZone.element)) {
            this.reinstall()
          }
        },
        { once: true }
      )
    }

    return timeline
  }

  /**
   * Their range runs `contain 0%` (pin engage) to `contain 100%` (release),
   * and each panel carries the window it occupies within that. The zone's own
   * distance — a fraction of the viewport when scrolling vertically — reads
   * here as the same fraction of the panel's passage across the screen.
   */
  private applyHorizontal(
    zone: IZone,
    fromTheme: TTheme,
    toTheme: TTheme,
    timeline: AnimationTimeline
  ): void {
    const styles = getComputedStyle(zone.element)
    const start = this.percentOf(styles.getPropertyValue(HS_VAR_PANEL_START), 0)
    const end = this.percentOf(styles.getPropertyValue(HS_VAR_PANEL_END), 100)
    const finish = Math.min(end, start + (end - start) * zone.distance)

    this.animation = document.body.animate(this.keyframes(fromTheme, toTheme), {
      timeline,
      fill: 'both',
      easing: 'linear',
      rangeStart: `contain ${start}%`,
      rangeEnd: `contain ${finish}%`
    } as unknown as KeyframeAnimationOptions)
  }

  private percentOf(value: string, fallback: number): number {
    const percent = Number.parseFloat(value)

    return Number.isFinite(percent) ? percent : fallback
  }

  private keyframes(fromTheme: TTheme, toTheme: TTheme): Keyframe[] {
    const from = 'alt' === fromTheme ? 1 : 0
    const to = 'alt' === toTheme ? 1 : 0

    return [{ [VAR_PROGRESS]: String(from) }, { [VAR_PROGRESS]: String(to) }]
  }

  private isSameSetup(zone: IZone): boolean {
    const active = this.activeZone

    return (
      !!active &&
      active.element === zone.element &&
      active.triggerPoint === zone.triggerPoint &&
      active.distance === zone.distance
    )
  }

  /**
   * How far into the zone's cover range the scrub starts: the trigger point
   * is the viewport line the section top must reach, and the cover range
   * begins with that top at the viewport bottom — so the distance between
   * them is the untriggered part of the viewport.
   */
  private startPx(zone: IZone, usable: number): number {
    // Rounded: the fractions this arithmetic produces would otherwise reach
    // the stylesheet as `199.99999999999994px`.
    return Math.round(usable * (1 - zone.triggerPoint))
  }

  /**
   * Viewport height below the admin bar — the stylesheet owns the heights and
   * the below-600px regime. Read once per install and handed down: every read
   * is a forced style flush, and startPx/lengthPx/endPx all want the same one.
   */
  private usableHeight(): number {
    const value = Number.parseFloat(getComputedStyle(document.body).getPropertyValue(VAR_ADMIN_BAR))

    return window.innerHeight - (Number.isFinite(value) ? value : 0)
  }

  /**
   * The scrub may not outlive the zone: once the section's bottom reaches the
   * line the zone is over, so a distance longer than that span would be cut
   * off part-way and then jump.
   */
  private endPx(zone: IZone, usable: number): number {
    const activeSpan = zone.element.getBoundingClientRect().height

    return (
      this.startPx(zone, usable) + Math.min(this.lengthPx(zone, usable), Math.max(activeSpan, 1))
    )
  }

  /** The distance is a fraction of the same usable viewport as the line. */
  private lengthPx(zone: IZone, usable: number): number {
    return Math.round(usable * zone.distance)
  }

  /**
   * The polyfill maps any non-percentage range offset to the full named
   * range (length offsets are silently dropped — measured, and the family
   * precedent only ever feeds it percentages), so both ends are converted to
   * a percentage of the zone's cover span at setup time.
   */
  private percentOfCover(zone: IZone, px: number): string {
    const coverSpan = zone.element.getBoundingClientRect().height + window.innerHeight
    const percent = coverSpan > 0 ? Math.min(100, Math.max(0, (px / coverSpan) * 100)) : 100

    return `cover ${percent}%`
  }

  private applyNative(zone: IZone, to: TTheme): void {
    const html = document.documentElement
    // Both ends are measured BEFORE the writes below. Naming the timeline and
    // adding the html classes dirties style, so the rect read inside endPx()
    // would otherwise force a synchronous layout to satisfy it.
    const usable = this.usableHeight()
    const start = this.startPx(zone, usable)
    const end = this.endPx(zone, usable)

    // The timeline is named here rather than in the stylesheet because the
    // name must be unique in scope: every zone declaring it would make the
    // reference ambiguous, and an ambiguous name resolves to no timeline at
    // all — the scrub would silently never run.
    zone.element.style.setProperty('view-timeline', '--arts-cs-zone block')
    html.classList.add(CLASS_SCROLL_NATIVE)
    html.classList.toggle(CLASS_SCROLL_REVERSE, 'default' === to)
    document.body.style.setProperty('animation-range', `cover ${start}px cover ${end}px`)
  }

  private async applyWaapi(zone: IZone, fromTheme: TTheme, toTheme: TTheme): Promise<void> {
    if (window.__artsScrollTimelinePolyfillReady) {
      await window.__artsScrollTimelinePolyfillReady
    }

    // The zone may have been re-synced or torn down while the polyfill loaded.
    if (this.activeZone !== zone || !window.ViewTimeline) {
      return
    }

    const timeline = new window.ViewTimeline({ subject: zone.element, axis: 'block' })
    const usable = this.usableHeight()

    this.animation = document.body.animate(this.keyframes(fromTheme, toTheme), {
      timeline,
      fill: 'both',
      easing: 'linear',
      rangeStart: this.percentOfCover(zone, this.startPx(zone, usable)),
      rangeEnd: this.percentOfCover(zone, this.endPx(zone, usable))
    } as unknown as KeyframeAnimationOptions)
  }
}

export const scrollDriver = new ScrollDriver()
