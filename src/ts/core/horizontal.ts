import { HS_TRACK_SELECTOR, HS_VAR_DIR, HS_WRAPPER_SELECTOR } from '../constants'

/**
 * Arts Horizontal Scroll interop, in one place because both the coordinator
 * (which decides an observer's shape) and the scroll driver (which decides a
 * scrub's timeline) have to agree on whether an element is travelling
 * sideways right now.
 */

/**
 * The track this element rides, but only while their engine is actually
 * running it: their documented state probe is the track's computed position —
 * `sticky` is pinned, `static` is one of the vertical states (touch, a
 * breakpoint with the layout off, a browser without support), where the
 * element is in ordinary flow and none of this applies.
 *
 * The TRACK, not the wrapper: `closest` matches the element itself, and a
 * marked horizontal section is an ordinary vertical zone — its runway scrolls
 * the normal way. Only what travels inside the track is special.
 */
export const pinnedTrackOf = (element: HTMLElement): HTMLElement | null => {
  const track = element.closest<HTMLElement>(HS_TRACK_SELECTOR)

  if (!track || 'sticky' !== getComputedStyle(track).position) {
    return null
  }

  return track
}

/**
 * The visible stage a pinned panel travels across: the wrapper, which is
 * viewport-width and clips its own overflow.
 *
 * Emphatically NOT the track. That is the full max-content row of every panel
 * — several viewports wide — and it is the thing being translated, so a line
 * measured against it would slide along with the content it is meant to be
 * measuring. Measured against the wrapper the line also survives a boxed
 * section and any gutter the page or the editor canvas puts at the edge.
 */
export const pinnedStageOf = (element: HTMLElement): HTMLElement | null => {
  const track = pinnedTrackOf(element)

  return track ? track.closest<HTMLElement>(HS_WRAPPER_SELECTOR) : null
}

/** Their RTL flag: the panels travel the other way, so the line mirrors. */
export const isInverted = (stage: HTMLElement): boolean =>
  '-1' === getComputedStyle(stage).getPropertyValue(HS_VAR_DIR).trim()
