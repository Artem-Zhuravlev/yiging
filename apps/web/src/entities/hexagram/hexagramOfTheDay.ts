const MS_PER_DAY = 86_400_000
const HEXAGRAM_COUNT = 64

/** Deterministically maps a local calendar date to a King Wen number (1-64) — pure, no I/O, no
 * randomness (SPEC-032). The same local calendar day always yields the same number; the day
 * boundary is local midnight, not UTC midnight, so two visitors in different timezones may
 * legitimately see different hexagrams at the same instant. */
export function hexagramOfTheDayNumber(date: Date = new Date()): number {
  const daysSinceEpoch = Math.floor(
    Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()) / MS_PER_DAY,
  )

  return (((daysSinceEpoch % HEXAGRAM_COUNT) + HEXAGRAM_COUNT) % HEXAGRAM_COUNT) + 1
}
