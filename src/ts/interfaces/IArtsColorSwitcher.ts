import type { TPreference, TTheme } from '../types'

/** The public `window.ArtsColorSwitcher` surface. */
export interface IArtsColorSwitcher {
  /** Contract version of this build. */
  contract: number
  /** Re-read the page baseline and re-resolve the current state. */
  refresh(): void
  /** Set the runtime baseline. Changes what is shown; persists nothing. */
  set(theme: TTheme): void
  /** Store the visitor's choice and apply it. The only thing that persists. */
  setPreference(preference: TPreference): void
  /** The stored choice, or `system` when nothing has been chosen. */
  getPreference(): TPreference
  /** Current binary theme. */
  getTheme(): TTheme
  /** Current progress scalar as computed on `<body>`. */
  getProgress(): number
  /** Disconnect all observers and forget all zones. */
  destroy(): void
}
