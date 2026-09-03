/** A section that switches the page palette while it is on screen. */
export interface IZone {
  element: HTMLElement
  /** Trigger line as a fraction of the usable viewport height (0..1, 0 = top). */
  triggerPoint: number
  /** How much of the viewport the change is spread over; 0 switches instantly. */
  distance: number
}
