/** Elementor's range-slider value: two handles, bottom-up percentages. */
export interface IViewportValue {
  unit?: string
  sizes?: {
    start?: number | string
    end?: number | string
  }
}
