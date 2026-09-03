import type { TChangeSource, TTheme } from '../types'

/** Payload of the public `arts-cs:change` event. */
export interface IChangeDetail {
  theme: TTheme
  source: TChangeSource
}
