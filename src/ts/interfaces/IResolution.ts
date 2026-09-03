import type { TTheme } from '../types'

/** The outcome of one geometry resolution pass. */
export interface IResolution {
  /** The binary state to write, i.e. what the page shows when nothing scrubs. */
  theme: TTheme
  /**
   * Index of the scroll-bound zone that currently owns the scalar, or -1.
   * Only one thing may drive the scalar at a time: a scrub animation holds
   * its value with `fill: both` and outranks the binary declaration, so a
   * page mixing both kinds of zone needs an arbiter rather than two writers.
   */
  scrubbingIndex: number
}
