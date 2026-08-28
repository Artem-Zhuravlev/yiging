const STORAGE_KEY = 'yijing-casting-reveal'

/** Whether the post-cast reveal animation (SPEC-042) should play. Defaults to on; a user who
 * finds it slow unticks the "Show casting animation" box, which persists `"off"` here. */
export function isCastingRevealEnabled(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) !== 'off'
  } catch {
    return true
  }
}

export function setCastingRevealEnabled(enabled: boolean): void {
  try {
    localStorage.setItem(STORAGE_KEY, enabled ? 'on' : 'off')
  } catch {
    // Non-fatal: the preference just won't persist (private mode, storage disabled).
  }
}

/** True when the OS asks for reduced motion — in which case the reveal is skipped entirely,
 * regardless of the checkbox (REQ-REVEAL-020). Guarded for jsdom, where matchMedia may be a
 * partial stub. */
export function prefersReducedMotion(): boolean {
  try {
    return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  } catch {
    return false
  }
}
