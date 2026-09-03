// Arts Horizontal Scroll integration. These names are that plugin's committed
// public surface (its README's "Integration contract", `window.ARTS_HS.contract`
// === 1) — a bump there is a review trigger here. Everything is feature-detected:
// on a page without the plugin the branch never runs.

/** Wrapper the horizontal engine pins; also the view-timeline's subject. */
export const HS_WRAPPER_SELECTOR = '.js-arts-hs'

/** The pinned, horizontally translated track. Its computed position is the state probe. */
export const HS_TRACK_SELECTOR = '.js-arts-hs__track'

/** `1` normally, `-1` on an RTL page or a forced right-to-left direction. */
export const HS_VAR_DIR = '--arts-hs-dir'

/** Bubbles on the wrapper once their engine has booted and its timeline exists. */
export const HS_READY_EVENT = 'arts-hs:ready'

/** Per-panel window, in % of the pin traversal; inherits into the panel's subtree. */
export const HS_VAR_PANEL_START = '--arts-hs-panel-start'
export const HS_VAR_PANEL_END = '--arts-hs-panel-end'
