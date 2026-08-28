import { nextTick } from 'vue'

/**
 * Move keyboard focus to the current page's `<main id="main">` landmark.
 *
 * Called from the router's `afterEach` hook on every client-side navigation *except* the initial
 * load (SPEC-039, REQ-A11Y-003) — a fresh page load must leave focus at the document start so the
 * skip link is the first thing a keyboard user reaches.
 *
 * `<main>` carries `tabindex="-1"` so it is programmatically focusable without becoming a
 * permanent tab stop. Waits a tick so the destination component has rendered its `<main>`.
 */
export async function focusMain(): Promise<void> {
  await nextTick()
  const main = document.getElementById('main')
  main?.focus()
}
