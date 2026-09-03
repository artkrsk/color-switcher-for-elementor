import {
  EVENT_CHANGE,
  PREFERENCE_SYSTEM,
  SKIN_BUTTONS,
  SKIN_DROPDOWN,
  SKIN_SWITCH
} from '../constants'
import type { TPreference } from '../types'
import { coordinator } from './coordinator'
import { clear, current, isPreference, read, write } from './preference'

/**
 * The visitor-facing controls. They are the only thing in the plugin that
 * persists anything: everything else changes what is shown without committing
 * it.
 *
 * Every skin funnels into `apply()`, and none of them resolves a theme — they
 * move the PREFERENCE and let the coordinator re-read the baseline. What each
 * skin shows is decided in CSS off `<html>`, which the head script stamps
 * before first paint; the work here is the presses and the announcements.
 */

/** The Elementor skin, which is also the shape of the control. */
const ATTR_SKIN = 'data-arts-cs-toggle'
/** Two states or three — orthogonal to the skin, except where one implies it. */
const ATTR_MODE = 'data-arts-cs-mode'
/** On a child of the Buttons skin: the preference that child stands for. */
const ATTR_SET = 'data-arts-cs-set'
/** What the control is called, for the name a screen reader announces. */
const ATTR_NAME = 'data-arts-cs-name'

const CYCLE: TPreference[] = [PREFERENCE_SYSTEM, 'default', 'alt']

/** Author-supplied and translated, so the runtime never invents a word. */
const labelOf = (root: HTMLElement, preference: TPreference): string =>
  root.getAttribute(`data-arts-cs-label-${preference}`) ?? ''

/** Opposite of the page's own state, never of what a zone is showing. */
const opposite = (): TPreference => ('alt' === coordinator.getBaseline() ? 'default' : 'alt')

/**
 * A visible caption already names the control; overwriting it would announce
 * something other than what the visitor can read.
 */
const name = (root: HTMLElement, text: string): void => {
  if (root.querySelector('.arts-cs-toggle__label')) {
    return
  }

  root.setAttribute('aria-label', text)
  root.setAttribute('title', text)
}

/** What a control has to show when nothing is stored and the author decides. */
const shown = (cycles: boolean): TPreference =>
  read() ?? (cycles ? PREFERENCE_SYSTEM : coordinator.getTheme())

const describe = (root: HTMLElement): void => {
  const skin = root.getAttribute(ATTR_SKIN)
  const cycles = 'cycle' === root.getAttribute(ATTR_MODE)

  if (SKIN_DROPDOWN === skin) {
    const select = root.querySelector('select')

    if (select) {
      select.value = shown(cycles)
    }

    return
  }

  if (SKIN_BUTTONS === skin) {
    // With two options and nothing stored, NO option is pressed: the visitor
    // has expressed nothing and the author's design is what they are seeing.
    // That empty state is the whole reason this skin can offer three states
    // through two controls.
    const active = cycles ? current() : read()

    for (const option of root.querySelectorAll(`[${ATTR_SET}]`)) {
      option.setAttribute('aria-pressed', String(option.getAttribute(ATTR_SET) === active))
    }

    return
  }

  if (cycles) {
    // Three states cannot be expressed as checked or pressed, so the name
    // carries the state instead — and it moves with every press.
    name(root, `${root.getAttribute(ATTR_NAME) ?? ''}: ${labelOf(root, current())}`)

    return
  }

  // A two-state control is named after the palette it turns on and reports
  // whether that palette is showing. `role="switch"` only where the control
  // looks like one; elsewhere a pressed button, which is announced far more
  // consistently.
  root.setAttribute(
    SKIN_SWITCH === skin ? 'aria-checked' : 'aria-pressed',
    String('alt' === coordinator.getTheme())
  )
}

const attached = new Set<HTMLElement>()

/** Prunes what an AJAX swap detached, so the set cannot grow across pages. */
const describeAll = (): void => {
  for (const root of attached) {
    if (root.isConnected) {
      describe(root)
    } else {
      attached.delete(root)
    }
  }
}

const apply = (preference: TPreference | null): void => {
  if (preference) {
    write(preference)
  } else {
    clear()
  }

  // refresh() re-reads the baseline, which now consults the stored preference
  // — so no control ever has to resolve a theme itself.
  coordinator.refresh()
  // Not left to the change event: moving from `system` to an explicit Default
  // on a light OS moves the PREFERENCE without moving the theme, so nothing
  // fires and every control would keep announcing the old state.
  describeAll()
}

const activate = (root: HTMLElement, event: Event): void => {
  const cycles = 'cycle' === root.getAttribute(ATTR_MODE)

  if (SKIN_BUTTONS === root.getAttribute(ATTR_SKIN)) {
    const option = (event.target as HTMLElement | null)?.closest(`[${ATTR_SET}]`)
    const value = option?.getAttribute(ATTR_SET)

    if (!isPreference(value)) {
      return
    }

    // Pressing the pinned option again releases it. Three options are a
    // choice among all of them, so there is nothing to release there.
    apply(!cycles && value === read() ? null : value)

    return
  }

  if (cycles) {
    apply(CYCLE[(CYCLE.indexOf(current()) + 1) % CYCLE.length] as TPreference)

    return
  }

  // Two states, three positions: pinned, released, pinned the other way.
  // Releasing hands the page back to its author — where the visitor was
  // before their first press, which following the OS would not guarantee.
  apply(read() ? null : opposite())
}

/**
 * One `arts-cs:change` listener for every control on the page rather than one
 * each: an AJAX swap replaces the markup, and a per-control listener would
 * outlive it holding a detached node for the rest of the session.
 */
let listening = false

const listen = (): void => {
  if (listening) {
    return
  }

  listening = true
  document.addEventListener(EVENT_CHANGE, describeAll)
}

/** Both the element_ready hook and boot's catch-up scan reach the same root. */
export const attachToggle = (root: HTMLElement): void => {
  if (attached.has(root)) {
    return
  }

  attached.add(root)
  listen()

  const select = root.querySelector('select')

  if (select) {
    select.addEventListener('change', () => {
      if (isPreference(select.value)) {
        apply(select.value)
      }
    })
  } else {
    root.addEventListener('click', (event) => activate(root, event))
  }

  // The rendered state is a starting guess — the page may have come from a
  // cache that knows nothing about this visitor.
  describe(root)
}
