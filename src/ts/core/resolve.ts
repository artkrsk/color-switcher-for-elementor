import type { IResolution, IZoneSnapshot } from '../interfaces'
import type { TTheme } from '../types'

/**
 * Pure state resolution: a zone applies while its section spans the trigger
 * line. The page then shows the opposite of its baseline, and returns to the
 * baseline by itself once no zone is on the line.
 *
 * Whether a zone is on the line is an observer's verdict rather than a
 * measurement here — which is what makes this correct on both axes. A section
 * pinned inside a horizontally scrolling track has a frozen vertical band and
 * moves only sideways; geometry read from `top`/`bottom` cannot see that, and
 * an intersection with the line can.
 *
 * A zone with a transition distance takes over the scalar instead of setting
 * the binary state, because the two would fight: flipping the attribute puts
 * the scalar at its end value immediately, leaving the scrub nothing to do.
 *
 * Where several zones overlap (a marked section inside a marked container)
 * the last in document order decides; both mean the same thing, so nesting
 * cannot contradict itself.
 */
export const resolve = (zones: readonly IZoneSnapshot[], baseline: TTheme): IResolution => {
  const flipped: TTheme = 'alt' === baseline ? 'default' : 'alt'
  let theme = baseline
  let scrubbingIndex = -1

  zones.forEach((zone, index) => {
    if (!zone.active) {
      return
    }

    if (zone.distance > 0) {
      scrubbingIndex = index
    } else {
      theme = flipped
      scrubbingIndex = -1
    }
  })

  return { theme, scrubbingIndex }
}
