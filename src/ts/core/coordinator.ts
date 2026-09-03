import {
  ATTR_BASELINE,
  ATTR_STATE,
  CLASS_MORPHING,
  EVENT_CHANGE,
  HS_READY_EVENT,
  VAR_ADMIN_BAR,
  VAR_DURATION,
  VAR_PROGRESS
} from '../constants'
import type { IChangeDetail, IZone } from '../interfaces'
import type { TChangeSource, TTheme } from '../types'
import { isInverted, pinnedStageOf } from './horizontal'
import { resolveBaseline } from './preference'
import { resolve } from './resolve'
import { scrollDriver } from './scrollDriver'

/**
 * An Auto page defers to the visitor's device — except in the editor canvas,
 * which must show what the author built rather than what their laptop
 * prefers. An author on a dark machine editing a light page would otherwise
 * be designing against colors no visitor of theirs has asked for.
 */
const followsDevice = (): boolean => true !== window.elementorFrontend?.isEditMode?.()

/**
 * The page-level state owner: zones register here, resolution writes ONE
 * html attribute, CSS does every color. Owns all IntersectionObservers so
 * AJAX swaps and editor re-renders can never leak a watcher.
 *
 * Nothing runs per frame. A zone's trigger line IS an observer — its root
 * collapsed to a 1px strip at that line — so "the section spans the line" is
 * pushed to us rather than measured. That is also what makes zones work on
 * both axes: a panel pinned inside a horizontally scrolling track has a
 * frozen vertical band and moves only sideways, which a rect comparison
 * cannot see and an intersection can.
 */
class Coordinator {
  private zones = new Map<HTMLElement, IZone>()
  /**
   * One observer per distinct root, keyed by the rootMargin that defines it.
   * rootMargin is per-observer and immutable, so zones sharing a root share an
   * observer and a resize rebuilds them all. The key is the string rather than
   * the line because a zone travelling sideways gets a rotated root.
   */
  private observers = new Map<string, IntersectionObserver>()
  private active = new Set<HTMLElement>()
  /** The root each zone is currently observed against, to detect real changes. */
  private margins = new WeakMap<HTMLElement, string>()
  private baseline: TTheme = 'default'
  private morphTimer = 0
  private listening = false
  private rebuildFrame = 0
  /** Document order is only recomputed when the zone set itself changes. */
  private ordered: IZone[] | null = null

  /**
   * The server-rendered state, read once before any zone can have touched
   * it. Only used where no document wrapper carries a baseline (non-Elementor
   * documents); reading the live attribute there instead would let a zone's
   * state become the baseline and stick.
   */
  private readonly serverBaseline: TTheme = this.getTheme()

  init(): void {
    this.baseline = this.readBaseline()
    this.resolve('baseline')
  }

  register(zone: IZone): void {
    this.unregister(zone.element)
    this.zones.set(zone.element, zone)
    this.ordered = null
    this.observe(zone, this.adminBarOffset())
    this.listen()
    this.resolve('zone')
  }

  /**
   * Reports whether this element actually was a zone. The handler leans on
   * that: re-resolving after removing nothing costs a baseline re-read for no
   * reason, and it runs for every section that is off at this breakpoint.
   */
  unregister(element: HTMLElement): boolean {
    for (const observer of this.observers.values()) {
      observer.unobserve(element)
    }

    const had = this.zones.delete(element)

    this.active.delete(element)

    if (had) {
      this.ordered = null
    }

    if (!this.zones.size) {
      this.disconnect()
      this.unlisten()
    }

    return had
  }

  /** Re-read the baseline (post-AJAX-swap) and re-resolve. */
  refresh(): void {
    this.prune()
    this.baseline = this.readBaseline()
    this.resolve('baseline')
  }

  /**
   * Runtime baseline override (the dark-mode seam). A zone shows the opposite
   * of the baseline rather than a theme of its own, so changing the baseline
   * while one is in view inverts that zone too — a marked section is a
   * contrast to the page, whichever way the page is set. Deliberate: it is
   * what keeps "dark page with light interludes" working from either side.
   */
  set(theme: TTheme): void {
    this.baseline = theme
    this.resolve('api')
  }

  getTheme(): TTheme {
    return 'alt' === document.documentElement.getAttribute(ATTR_STATE) ? 'alt' : 'default'
  }

  /**
   * The page's own state, with any zone currently in view discounted. What a
   * toggle has to invert: `getTheme()` there would be the zone's inversion, so
   * pressing inside a marked section would store the opposite of what the
   * visitor is asking for and appear to do nothing.
   */
  getBaseline(): TTheme {
    return this.baseline
  }

  getProgress(): number {
    const value = Number.parseFloat(getComputedStyle(document.body).getPropertyValue(VAR_PROGRESS))

    return Number.isFinite(value) ? value : 0
  }

  /** Disconnect everything — pre-swap teardown and the contract's destroy(). */
  destroy(): void {
    this.disconnect()
    this.zones.clear()
    this.ordered = null
    this.unlisten()
    scrollDriver.teardown()
  }

  private getZonesInOrder(): IZone[] {
    if (!this.ordered) {
      this.ordered = [...this.zones.values()].sort((a, b) =>
        a.element.compareDocumentPosition(b.element) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1
      )
    }

    return this.ordered
  }

  private observe(zone: IZone, offset: number): void {
    const margin = this.marginFor(zone, offset)
    let observer = this.observers.get(margin)

    if (!observer) {
      observer = new IntersectionObserver((entries) => this.onIntersect(entries), {
        rootMargin: margin,
        threshold: 0
      })
      this.observers.set(margin, observer)
    }

    observer.observe(zone.element)
    this.margins.set(zone.element, margin)
  }

  /**
   * The trigger line, as a root collapsed to a 1px strip — across the
   * viewport's height normally, across its WIDTH for a section pinned inside
   * a horizontally scrolling track.
   *
   * The rotation is what keeps the control honest there. A pinned panel spans
   * the whole track height for its entire traversal, so a horizontal line is
   * always crossed and the handle would decide nothing; turned ninety degrees
   * it keeps exactly the meaning it has vertically — how far into the screen
   * the section travels before it switches.
   */
  private marginFor(zone: IZone, offset: number): string {
    const stage = pinnedStageOf(zone.element)

    if (!stage) {
      return this.rootMarginFor(this.lineOf(zone, offset))
    }

    // Measured across the stage rather than the viewport: that is where the
    // handles' 0 and 100 belong, and it survives anything inset from the
    // viewport edge — a boxed section, a page gutter, the editor canvas's own
    // margin, which a line pinned to x=0 would sit inside and never be crossed.
    const rect = stage.getBoundingClientRect()
    const width = window.innerWidth
    // Panels enter from the side they travel from, so the line is measured
    // from that side: mirrored on an RTL page, which their own var reports.
    const travelled = isInverted(stage) ? 1 - zone.triggerPoint : zone.triggerPoint
    const lineX = Math.min(
      Math.max(Math.round(rect.left + rect.width * travelled), 0),
      Math.max(width - 1, 0)
    )

    return `0px ${-Math.max(width - lineX - 1, 0)}px 0px ${-lineX}px`
  }

  /**
   * The trigger line in viewport px, measured from below the admin bar so it
   * sits where a logged-in visitor actually sees it. Clamped to leave room
   * for the 1px strip below.
   */
  private lineOf(zone: IZone, offset: number): number {
    const usable = window.innerHeight - offset
    const line = Math.round(offset + usable * zone.triggerPoint)

    return Math.min(Math.max(line, 0), Math.max(window.innerHeight - 1, 0))
  }

  /**
   * Collapses the root to a 1px full-width strip at the line, which makes
   * `isIntersecting` exactly "this section spans the trigger line".
   *
   * The 1px is load-bearing: a zero-height root is degenerate and reports
   * nothing — the precise failure this plugin already shipped once, when a
   * -100% margin collapsed the root at trigger point 0.
   *
   * Full width, deliberately. A centre point would give free exclusivity
   * between horizontal panels, but a vertical zone narrower than half the
   * viewport would then never intersect it.
   */
  private rootMarginFor(line: number): string {
    return `${-line}px 0px ${-Math.max(window.innerHeight - line - 1, 0)}px 0px`
  }

  private onIntersect(entries: IntersectionObserverEntry[]): void {
    for (const entry of entries) {
      const element = entry.target as HTMLElement

      if (entry.isIntersecting) {
        this.active.add(element)
      } else {
        this.active.delete(element)
      }
    }

    this.resolve('zone')
  }

  /**
   * Only a resize is listened for, and only to rebuild. rootMargin is
   * absolute px and immutable, so it goes stale when the line moves OR when
   * the viewport height changes — the bottom inset is measured from it.
   * Scroll is not listened for at all; that is the point of driving
   * resolution from observers.
   */
  private listen(): void {
    if (this.listening) {
      return
    }

    this.listening = true
    window.addEventListener('resize', this.onResize, { passive: true })
    // Their engine boots on its own element_ready hook, which can land after
    // ours — a zone observed before that got an unrotated root. The event
    // bubbles, so one listener covers every section on the page.
    document.addEventListener(HS_READY_EVENT, this.onResize)
  }

  private unlisten(): void {
    this.listening = false
    window.removeEventListener('resize', this.onResize)
    document.removeEventListener(HS_READY_EVENT, this.onResize)
    window.cancelAnimationFrame(this.rebuildFrame)
    this.rebuildFrame = 0
  }

  private onResize = (): void => {
    if (this.rebuildFrame) {
      return
    }

    this.rebuildFrame = window.requestAnimationFrame(() => {
      this.rebuildFrame = 0
      this.rebuild()
    })
  }

  /**
   * Re-observe every zone against freshly measured lines. The active set is
   * dropped with the observers and refilled by their initial callbacks, so a
   * zone that stopped spanning its line at the new size is not left active.
   *
   * Skipped entirely when every root would come out identical — a width-only
   * resize, which the editor's device switcher and any horizontal window drag
   * produce. Rebuilding there would drop and refill the active set for
   * nothing, and a partially refilled set is a state we would rather never
   * resolve from.
   *
   * The probe compares against the UPRIGHT root, so a zone pinned in a
   * horizontal track — whose stored root is rotated — never matches and always
   * rebuilds. That is the wanted answer there: its line is measured across the
   * stage, so a width change really does move it.
   */
  private rebuild(): void {
    this.prune()

    // Once for the whole pass: every read of the allowance is a forced style
    // flush, and both loops below want the same one.
    const offset = this.adminBarOffset()
    let stale = false

    for (const zone of this.zones.values()) {
      if (this.margins.get(zone.element) !== this.rootMarginFor(this.lineOf(zone, offset))) {
        stale = true
        break
      }
    }

    if (!stale) {
      return
    }

    this.disconnect()

    for (const zone of this.zones.values()) {
      this.observe(zone, offset)
    }
  }

  private disconnect(): void {
    for (const observer of this.observers.values()) {
      observer.disconnect()
    }

    this.observers.clear()
    this.active.clear()
  }

  private resolve(source: TChangeSource): void {
    this.prune()

    const zones = this.getZonesInOrder()
    const snapshots = zones.map((zone) => ({
      active: this.active.has(zone.element),
      distance: zone.distance
    }))

    const { theme, scrubbingIndex } = resolve(snapshots, this.baseline)

    this.apply(theme, source)
    // The scrub interpolates away from whatever surrounds it and toward the
    // flip of the page baseline.
    scrollDriver.sync(zones[scrubbingIndex] ?? null, theme, this.flipped())
  }

  private flipped(): TTheme {
    return 'alt' === this.baseline ? 'default' : 'alt'
  }

  /**
   * The stylesheet resolves the admin bar's allowance (including zeroing it
   * below 600px, where the bar stops being pinned); JS never re-derives the
   * heights or breakpoints itself. Reading it costs a style flush, so callers
   * take it once and hand it down rather than measuring per zone.
   */
  private adminBarOffset(): number {
    const value = Number.parseFloat(getComputedStyle(document.body).getPropertyValue(VAR_ADMIN_BAR))

    return Number.isFinite(value) ? value : 0
  }

  private apply(theme: TTheme, source: TChangeSource): void {
    if (theme === this.getTheme()) {
      return
    }

    const html = document.documentElement

    this.startMorphWindow()

    if ('alt' === theme) {
      html.setAttribute(ATTR_STATE, 'alt')
    } else {
      html.removeAttribute(ATTR_STATE)
    }

    const detail: IChangeDetail = { theme, source }
    document.dispatchEvent(new CustomEvent(EVENT_CHANGE, { detail }))
  }

  /**
   * Suppress Elementor's stock container background transition for exactly
   * one morph, so backgrounds don't chase the animating scalar.
   */
  private startMorphWindow(): void {
    const html = document.documentElement
    const duration = Number.parseFloat(
      getComputedStyle(document.body).getPropertyValue(VAR_DURATION)
    )
    const ms = (Number.isFinite(duration) ? duration : 0.4) * 1000

    html.classList.add(CLASS_MORPHING)
    window.clearTimeout(this.morphTimer)
    this.morphTimer = window.setTimeout(() => html.classList.remove(CLASS_MORPHING), ms)
  }

  /** Editor re-renders and AJAX swaps replace elements without notice. */
  private prune(): void {
    for (const element of this.zones.keys()) {
      if (!element.isConnected) {
        this.unregister(element)
      }
    }
  }

  /**
   * What the server rendered, then the visitor's own layer on top. A stored
   * preference wins over the page's authored baseline — the presence of a
   * cookie is what transfers authority — and because refresh() runs after an
   * AJAX swap, the preference carries across pages for free.
   *
   * With no wrapper there is no authored baseline to read, so the head
   * script's verdict on `<html>` stands as-is: it has already resolved the
   * device, and resolving it twice would only produce the same answer.
   */
  private readBaseline(): TTheme {
    const wrapper = document.querySelector(`[${ATTR_BASELINE}]`)

    if (!wrapper) {
      return resolveBaseline(this.serverBaseline, false)
    }

    const authored = wrapper.getAttribute(ATTR_BASELINE)

    return resolveBaseline(
      'alt' === authored ? 'alt' : 'default',
      'auto' === authored && followsDevice()
    )
  }
}

export const coordinator = new Coordinator()
