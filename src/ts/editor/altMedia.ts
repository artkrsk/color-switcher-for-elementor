/**
 * The Alt picker — a second button inside Elementor's own media control.
 *
 * Registered under OUR type, never over `media`: elementor.addControlView is a
 * plain last-write-wins registry, so claiming a stock type would silently drop
 * whatever another plugin registered there. A name only we use cannot collide.
 *
 * The button is injected into `.elementor-control-media__tools` — the row the
 * stock template already prints for its own "Choose Image" tool, and the same
 * row Elementor's dynamic-tags switcher fills in. Appending a second control
 * field instead would read as a separate control sitting underneath, which is
 * the opposite of what the Alt is: part of this image, not another one.
 *
 * The second wp.media frame is the one piece with no precedent in Elementor —
 * its own answer to "two pickers in one row" is two separate media controls.
 * wp.media() is a factory, so an independent frame is safe; it just has to
 * stay off `this.frame`, which the parent owns.
 */

import { ALT_KEY_ID, ALT_KEY_URL, ALT_SETTING_LABELS, CONTROL_TYPE_ALT_MEDIA } from '../constants'

const SELECTOR_TOOLS = '.elementor-control-media__tools'
const SELECTOR_REPLACE = '.elementor-control-media__replace'
const SELECTOR_AREA = '.elementor-control-media-area'
const SELECTOR_STOCK_REMOVE = '.elementor-control-media__content__remove:not(.arts-cs-alt__remove)'

const CLASS_TOOL = 'elementor-control-media__tool'
// The stock remove button's own classes, so ours inherits its corner slot,
// its centring and its hover reveal instead of restating them.
const CLASS_STOCK_REMOVE =
  'elementor-control-media__remove elementor-control-media__content__remove'
const CLASS_CHOOSE = 'arts-cs-alt__choose'
const CLASS_REMOVE = 'arts-cs-alt__remove'
const CLASS_SET = 'arts-cs-alt__choose_set'

interface IAltLabels {
  choose?: string
  set?: string
  remove?: string
  frame?: string
}

interface IAttachment {
  url?: string
  id?: number
}

type TMediaFrame = ReturnType<NonNullable<Window['wp']>['media']>

export const registerAltMediaView = (): void => {
  // `window.` and not the bare binding: this bundle is enqueued with no
  // dependencies, so it also runs before the editor app exists, where a bare
  // read throws rather than yielding undefined.
  const editor = window.elementor
  const MediaView = editor?.modules?.controls?.Media

  if (!editor?.addControlView || !MediaView) {
    return
  }

  class AltMediaView extends MediaView {
    private altFrame?: TMediaFrame

    /**
     * render() rather than onReady(): it is the Marionette entry point the
     * typed surface actually exposes, and it re-fires on every re-render,
     * which is when our injected nodes would otherwise be lost.
     */
    override render(): this {
      super.render()
      this.renderAltTools()

      return this
    }

    /** Split from the frame so the write path is testable without wp.media. */
    onAltSelected(attachment: IAttachment): void {
      this.setValue({
        [ALT_KEY_URL]: 'string' === typeof attachment.url ? attachment.url : '',
        [ALT_KEY_ID]: 'number' === typeof attachment.id ? attachment.id : ''
      })

      this.renderAltTools()
    }

    onAltRemove(): void {
      this.setValue({ [ALT_KEY_URL]: '', [ALT_KEY_ID]: '' })
      this.renderAltTools()
    }

    openAltFrame(): void {
      const media = window.wp?.media

      if (!media) {
        return
      }

      if (!this.altFrame) {
        this.altFrame = media({
          title: this.labels().frame ?? '',
          multiple: false,
          library: { type: 'image' }
        })

        this.altFrame.on('select', () => {
          const selected = this.altFrame?.state().get('selection').first()

          if (selected) {
            this.onAltSelected(selected.toJSON())
          }
        })
      }

      this.altFrame.open()
    }

    private labels(): IAltLabels {
      const labels = this.model.get(ALT_SETTING_LABELS)

      return null !== labels && 'object' === typeof labels ? (labels as IAltLabels) : {}
    }

    private altUrl(): string {
      const value = this.getControlValue()

      if (null === value || 'object' !== typeof value) {
        return ''
      }

      const url = (value as Record<string, unknown>)[ALT_KEY_URL]

      return 'string' === typeof url ? url : ''
    }

    /**
     * Rebuild both Alt affordances from the current value.
     *
     * They live in different places on purpose: the choose tool belongs in
     * the stock tools row beside "Choose Image", and the remove tool in the
     * media area where the stock remove button already sits. Both carry a
     * gradient border, which is what marks them as ours — the same language
     * the Alt swatch uses in Site Settings.
     */
    private renderAltTools(): void {
      // The control re-renders on every value change; ours must not stack up.
      for (const stale of Array.from(
        this.el.querySelectorAll(`.${CLASS_CHOOSE}, .${CLASS_REMOVE}`)
      )) {
        stale.remove()
      }

      const url = this.altUrl()
      const labels = this.labels()

      this.renderChooseTool(url, labels)

      if (url) {
        this.renderRemoveTool(labels)
      }
    }

    /**
     * Placed after the stock tool rather than appended to the row: the
     * dynamic-tags switcher lands in the same row whenever its own behavior
     * renders, and anchoring to the tool we mean to sit beside keeps the
     * order stable however that race falls.
     */
    private renderChooseTool(url: string, labels: IAltLabels): void {
      const tools = this.el.querySelector(SELECTOR_TOOLS)

      if (!tools) {
        return
      }

      const choose = document.createElement('div')

      choose.className = `${CLASS_TOOL} ${CLASS_CHOOSE}${url ? ` ${CLASS_SET}` : ''}`
      choose.textContent = (url ? labels.set : labels.choose) ?? ''
      choose.title = url
      choose.addEventListener('click', (event) => {
        this.claim(event)
        this.openAltFrame()
      })

      const replaces = tools.querySelectorAll(SELECTOR_REPLACE)
      const anchor = replaces[replaces.length - 1]

      if (anchor) {
        anchor.insertAdjacentElement('afterend', choose)
      } else {
        tools.appendChild(choose)
      }
    }

    /**
     * Anchored to the stock remove button rather than to a container class:
     * it is the thing ours mirrors, so wherever Elementor moves it ours
     * follows, and it inherits that button's corner slot, centring and hover
     * reveal. The area is only a fallback for a template with no remove
     * button to sit beside.
     */
    private renderRemoveTool(labels: IAltLabels): void {
      const stockRemove = this.el.querySelector(SELECTOR_STOCK_REMOVE)
      const area = stockRemove?.parentElement ?? this.el.querySelector(SELECTOR_AREA)

      if (!area) {
        return
      }

      const remove = document.createElement('div')

      remove.className = `${CLASS_STOCK_REMOVE} ${CLASS_REMOVE}`
      remove.title = labels.remove ?? ''
      remove.appendChild(document.createElement('i')).className = 'eicon-trash-o'
      remove.addEventListener('click', (event) => {
        this.claim(event)
        this.onAltRemove()
      })

      if (stockRemove) {
        stockRemove.insertAdjacentElement('afterend', remove)
      } else {
        area.appendChild(remove)
      }
    }

    /**
     * The whole `.elementor-control-media__content` is the parent's own
     * frame opener (`click @ui.frameOpeners`), and our buttons sit inside it —
     * so without this a press opened the stock picker underneath ours, two
     * media modals deep.
     */
    private claim(event: Event): void {
      event.preventDefault()
      event.stopPropagation()
    }
  }

  // Called both eagerly and on `elementor/init`; addControlView is a plain
  // last-write-wins registry, so re-registering an equivalent class is a
  // no-op worth more than a module-level flag would be.
  editor.addControlView(CONTROL_TYPE_ALT_MEDIA, AltMediaView)
}
