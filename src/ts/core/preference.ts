import {
  ATTR_PREFERENCE,
  COOKIE_MAX_AGE,
  COOKIE_PREFERENCE,
  PREFERENCE_SYSTEM,
  QUERY_DARK
} from '../constants'
import type { TPreference, TTheme } from '../types'

/**
 * The visitor's own layer, in front of the page's baseline.
 *
 * Authority runs visitor, then author, then device. A cookie always wins:
 * its PRESENCE is what transfers authority, and once one exists — even
 * `system` — the visitor decides. With no cookie the page's own setting
 * decides, and only where the author declined to set one (an Auto page) does
 * the device get a say.
 *
 * The device is last rather than absent because it is the only party that
 * knows the visitor's real sunrise and sunset. An author who wants a page
 * light for everyone says so, and is obeyed.
 *
 * Nothing here writes the cookie except `write()` and `clear()`. `set()` on
 * the public contract stays side-effect free, which is what keeps a zone flip
 * mid-scroll — and a third party's preview — from committing anything the
 * visitor did not choose.
 */

const prefersDark = (): boolean => window.matchMedia?.(QUERY_DARK).matches === true

/** `system` is resolved here and never stored resolved. */
const themeOf = (preference: TPreference): TTheme => {
  if (PREFERENCE_SYSTEM === preference) {
    return prefersDark() ? 'alt' : 'default'
  }

  return 'alt' === preference ? 'alt' : 'default'
}

/** The only place the three stored values are spelled out. */
export const isPreference = (value: string | null | undefined): value is TPreference =>
  'system' === value || 'default' === value || 'alt' === value

export const read = (): TPreference | null => {
  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${COOKIE_PREFERENCE}=([^;]*)`))
  const value = match?.[1]

  return isPreference(value) ? value : null
}

/**
 * Mirror onto `<html>`, where the stylesheet reads it. Every cookie write goes
 * through here so the attribute cannot drift from the cookie the head script
 * stamped it from before paint.
 */
const stamp = (preference: TPreference | null): void => {
  if (preference) {
    document.documentElement.setAttribute(ATTR_PREFERENCE, preference)

    return
  }

  document.documentElement.removeAttribute(ATTR_PREFERENCE)
}

export const write = (preference: TPreference): void => {
  // biome-ignore lint/suspicious/noDocumentCookie: CookieStore is async, and the preference has to be readable synchronously by the pre-paint head script
  document.cookie = `${COOKIE_PREFERENCE}=${preference};path=/;max-age=${COOKIE_MAX_AGE};SameSite=Lax`
  stamp(preference)
}

/**
 * Release, rather than store a third value. Deliberately not `write('system')`:
 * deleting the cookie puts the visitor back exactly where they were before
 * their first press, which is the one place a two-state control can return
 * them to. What that place IS now depends on the page — the author's palette
 * where they set one, the device's where they did not — and either way it is
 * somewhere the visitor has already been.
 */
export const clear = (): void => {
  // biome-ignore lint/suspicious/noDocumentCookie: same reason as write() — one synchronous writer, read by an inline head script
  document.cookie = `${COOKIE_PREFERENCE}=;path=/;max-age=0;SameSite=Lax`
  stamp(null)
}

/**
 * The precedence table. A stored preference wins; failing that an Auto page
 * defers to the device and any other page keeps what the server rendered.
 *
 * The server reads neither the cookie nor the device — that is what keeps a
 * visitor's theme out of a shared page cache — so this is the only place the
 * visitor layer is applied. The pre-paint head script implements this same
 * table in `Assets::print_head_script()`; the two MUST agree, or the page
 * changes theme between first paint and boot.
 *
 * @param rendered What the server put on the page.
 * @param auto     Whether the author left this page to the visitor's device.
 */
export const resolveBaseline = (rendered: TTheme, auto: boolean): TTheme => {
  const preference = read()

  if (preference) {
    return themeOf(preference)
  }

  return auto ? themeOf(PREFERENCE_SYSTEM) : rendered
}

/**
 * Keep the device live: a visitor who never chose, or who chose to follow it,
 * tracks it mid-session.
 *
 * Deliberately NOTIFIES rather than handing back a theme. The caller
 * re-resolves through `resolveBaseline()`, so the page's own setting still
 * gets its say — an explicitly light page must not start following the device
 * just because the visitor changed it — and the precedence table stays in one
 * place. Writes nothing either way: persisting here would resolve a stored
 * `system` into a concrete theme and destroy the preference it exists to serve.
 */
export const watchSystem = (onChange: () => void): void => {
  window.matchMedia?.(QUERY_DARK).addEventListener('change', () => {
    const preference = read()

    if (!preference || PREFERENCE_SYSTEM === preference) {
      onChange()
    }
  })
}

/** What the toggle cycles through, and what the widget renders state from. */
export const current = (): TPreference => read() ?? PREFERENCE_SYSTEM
