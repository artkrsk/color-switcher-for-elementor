/**
 * What the VISITOR chose — deliberately not a theme. `system` resolves to a
 * theme at read time and is never stored as one; collapsing the two is what
 * makes every competitor's "system" a starting position rather than a state
 * a visitor can hold.
 */
export type TPreference = 'system' | 'default' | 'alt'
