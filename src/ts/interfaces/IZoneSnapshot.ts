/** One zone as resolution sees it: is it on the line, and does it scrub? */
export interface IZoneSnapshot {
  /** The observer's verdict — the section spans its trigger line. */
  active: boolean
  /** Zero for an instant switch, a positive distance for a scrub. */
  distance: number
}
