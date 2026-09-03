/**
 * Elementor skin ids, which are also the value of `data-arts-cs-toggle` and
 * the suffix Elementor puts on `data-widget_type` — so boot must register an
 * `element_ready` hook per id. Frozen: a rename orphans saved widgets.
 */
/** Not exported: nothing branches on the icon skin, it is the fallthrough. */
const SKIN_ICON = 'icon'
export const SKIN_SWITCH = 'switch'
export const SKIN_BUTTONS = 'buttons'
export const SKIN_DROPDOWN = 'dropdown'

export const SKINS = [SKIN_ICON, SKIN_SWITCH, SKIN_BUTTONS, SKIN_DROPDOWN] as const
