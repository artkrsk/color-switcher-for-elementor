/** Mirrored from PHP Controls\AltMedia (parity-pinned). */
export const CONTROL_TYPE_ALT_MEDIA = 'arts_cs_media'

/**
 * Sub-keys inside the media control's OWN value object. Namespaced because
 * `alt` is already a key Control_Media reads, and because these are stored
 * inside other widgets' settings — renaming one later is a data migration
 * across every document, not an edit.
 */
export const ALT_KEY_URL = 'arts_cs_alt_url'
export const ALT_KEY_ID = 'arts_cs_alt_id'

/**
 * Control arg carrying the panel's own words. The view prints them, but they
 * come from PHP: this plugin ships no script translations, so a string
 * hardcoded here would be the one untranslatable thing in the panel.
 */
export const ALT_SETTING_LABELS = 'arts_cs_alt_labels'
