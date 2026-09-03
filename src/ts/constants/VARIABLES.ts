/** Live progress scalar 0..1, on `<body>`. */
export const VAR_PROGRESS = '--arts-cs-p'

/**
 * Morph timing, as customization vars rather than controls: the stylesheet
 * carries the defaults as var() fallbacks (0.4s / ease; `--arts-cs-ease` lives only in the stylesheet and the README), and a theme sets the
 * vars to override — one line of CSS instead of two kit controls.
 */
export const VAR_DURATION = '--arts-cs-duration'

/** Admin-bar allowance, resolved by the stylesheet (see index.scss). */
export const VAR_ADMIN_BAR = '--arts-cs-admin-bar'
