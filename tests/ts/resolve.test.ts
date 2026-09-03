import { resolve } from '@ts/core/resolve'
import type { IZoneSnapshot } from '@ts/interfaces'
import { describe, expect, it } from 'vitest'

/**
 * A zone applies while its section spans the trigger line and stops when it
 * leaves — nothing outlives what is on screen. Whether it spans the line is
 * an observer's verdict, so these cases are stated as that verdict rather
 * than as rectangles. Where the line sits, and the page-edge cases that fall
 * out of it, belong to the coordinator's rootMargin — see
 * contract.dom.test.ts — and to the e2e journey.
 */
const zone = (active: boolean, distance = 0): IZoneSnapshot => ({ active, distance })

describe('resolve', () => {
  it('returns the baseline when no zones exist', () => {
    expect(resolve([], 'default')).toEqual({ theme: 'default', scrubbingIndex: -1 })
    expect(resolve([], 'alt')).toEqual({ theme: 'alt', scrubbingIndex: -1 })
  })

  it('leaves the baseline alone while no zone is on the line', () => {
    expect(resolve([zone(false)], 'default').theme).toBe('default')
  })

  it('flips the page while a section spans the line', () => {
    expect(resolve([zone(true)], 'default').theme).toBe('alt')
  })

  /**
   * The dark-mode seam: a zone shows the opposite of the baseline, so moving
   * the baseline under an active zone inverts the zone with it.
   */
  it('inverts an active zone when the baseline moves under it', () => {
    expect(resolve([zone(true)], 'default').theme).toBe('alt')
    expect(resolve([zone(true)], 'alt').theme).toBe('default')
  })

  /** Parent and child both mean "the opposite of the baseline". */
  it('agrees with itself when zones are nested', () => {
    expect(resolve([zone(true), zone(true)], 'default').theme).toBe('alt')
  })

  it('ignores zones that are off the line when another one is on it', () => {
    expect(resolve([zone(false), zone(true)], 'default').theme).toBe('alt')
  })

  /**
   * Only one thing may write the scalar: a scrub holds its value with
   * `fill: both` and outranks the binary declaration, so a zone with a
   * distance takes the scalar instead of flipping the attribute — otherwise
   * the flip would jump the scalar to its end value and leave it nothing to
   * animate.
   */
  it('hands the scalar to a zone that has a distance to travel', () => {
    expect(resolve([zone(true, 0.5)], 'default')).toEqual({
      theme: 'default',
      scrubbingIndex: 0
    })
  })

  it('lets a later instant zone take the scalar back', () => {
    expect(resolve([zone(true, 0.5), zone(true)], 'default')).toEqual({
      theme: 'alt',
      scrubbingIndex: -1
    })
  })

  /**
   * Overlapping scrubs hand the scalar over rather than compose: the last in
   * document order wins, and the driver restarts on its own endpoints.
   */
  it('hands the scalar to the last of two overlapping scrub zones', () => {
    expect(resolve([zone(true, 0.5), zone(true, 0.25)], 'default')).toEqual({
      theme: 'default',
      scrubbingIndex: 1
    })
  })

  it('does not scrub for a zone that is off the line', () => {
    expect(resolve([zone(false, 0.5)], 'default')).toEqual({
      theme: 'default',
      scrubbingIndex: -1
    })
  })
})
