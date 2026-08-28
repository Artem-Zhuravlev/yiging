import { ref } from 'vue'

/** The single message currently held by the app-wide polite live region (`shared/ui/LiveRegion.vue`,
 * mounted once in `App.vue`). Any page can push to it via `announce()` to have a screen reader
 * speak a status transition — loading finished, an error occurred, a route changed (SPEC-039,
 * REQ-A11Y-004). */
export const liveMessage = ref('')

/**
 * Announce `message` in the app-wide polite live region.
 *
 * Assigns directly rather than debouncing: the callers here announce distinct transition strings
 * ("Loading…" → "Loaded" → an error message), so an identical back-to-back message that a screen
 * reader would skip is not a case we need to handle.
 */
export function announce(message: string): void {
  liveMessage.value = message
}
