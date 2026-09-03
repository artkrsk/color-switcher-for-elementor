/** Binary theme state, on `<html>` (`document.documentElement`). */
export const ATTR_STATE = 'data-arts-cs'

/**
 * The visitor's stored preference, mirrored onto `<html>` from the cookie —
 * by the pre-paint head script first, then by every cookie write. Absent when
 * nothing is stored. Lives here rather than on the toggle so a skin's active
 * state is decided by CSS before first paint, and so every toggle on the page
 * shows the same thing without being told.
 */
export const ATTR_PREFERENCE = 'data-arts-cs-pref'

/** Per-page baseline, on the Elementor document wrapper (AJAX-swap source). */
export const ATTR_BASELINE = 'data-arts-cs-baseline'
