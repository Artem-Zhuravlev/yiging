import { watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { announce } from './announce'

type StatusResolver = (status: string) => string | undefined

/**
 * Announce a page's load-state transitions in the app-wide polite live region (SPEC-039,
 * REQ-A11Y-004), so a screen-reader user hears that a fetch finished or failed rather than the
 * page silently swapping its DOM.
 *
 * Pass a ref to the page's status string (`computed(() => state.value.status)`). The default
 * mapping covers the near-universal `loading` / `error` / `loaded` trio; pass `resolve` to add
 * or override messages for page-specific statuses such as `not-found`.
 */
export function useStatusAnnouncer(status: Ref<string>, resolve?: StatusResolver): void {
  const { t } = useI18n()

  watch(status, (next, prev) => {
    if (next === prev) {
      return
    }

    const custom = resolve?.(next)
    if (custom !== undefined) {
      announce(custom)
      return
    }

    if (next === 'loading') {
      announce(t('a11y.loading'))
    } else if (next === 'error') {
      announce(t('a11y.loadFailed'))
    } else if (next === 'loaded') {
      announce(t('a11y.loaded'))
    }
  })
}
