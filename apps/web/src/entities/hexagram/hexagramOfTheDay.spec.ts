import { describe, it, expect } from 'vitest'
import { hexagramOfTheDayNumber } from './hexagramOfTheDay'

describe('hexagramOfTheDayNumber', () => {
  it('returns a number in the 1-64 range', () => {
    const number = hexagramOfTheDayNumber(new Date('2026-08-21T12:00:00'))

    expect(number).toBeGreaterThanOrEqual(1)
    expect(number).toBeLessThanOrEqual(64)
  })

  it('returns the same number for two times on the same local calendar day', () => {
    const morning = hexagramOfTheDayNumber(new Date('2026-08-21T00:05:00'))
    const night = hexagramOfTheDayNumber(new Date('2026-08-21T23:55:00'))

    expect(morning).toBe(night)
  })

  it('returns a different number the next calendar day (at least once across a full cycle)', () => {
    const day1 = hexagramOfTheDayNumber(new Date('2026-08-21T12:00:00'))
    const numbers = new Set<number>()
    for (let i = 0; i < 64; i++) {
      numbers.add(hexagramOfTheDayNumber(new Date(2026, 7, 21 + i, 12, 0, 0)))
    }

    // Across a full 64-day cycle, every King Wen number 1-64 appears exactly once.
    expect(numbers.size).toBe(64)
    expect(numbers.has(day1)).toBe(true)
  })

  it('is a pure function of the date, independent of call order or repeated calls', () => {
    const date = new Date('2026-08-21T12:00:00')

    expect(hexagramOfTheDayNumber(date)).toBe(hexagramOfTheDayNumber(date))
  })
})
