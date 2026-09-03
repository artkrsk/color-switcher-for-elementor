/** CustomEvent fired on `document` after every applied state change. */
export const EVENT_CHANGE = 'arts-cs:change'

/** Optional pre-swap teardown signal from Arts AJAX transitions. */
export const EVENT_AJAX_SYNC_BEFORE = 'arts/ajax/transition/sync/before'

/**
 * Editor-only: dispatched by the editor bundle on a changed element so its
 * handler re-reads settings. Elementor treats our controls as style-only —
 * it regenerates their CSS but notifies no handler — so the JS-side zone
 * would otherwise keep the settings it booted with.
 */
export const EVENT_SETTINGS_SYNC = 'arts-cs:settings-sync'
