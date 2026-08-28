import { describe, it, expect, afterEach } from 'vitest'
import { isCastingRevealEnabled, setCastingRevealEnabled } from './castingRevealPreference'

describe('castingRevealPreference', () => {
  afterEach(() => localStorage.clear())

  it('defaults to enabled when nothing is stored', () => {
    expect(isCastingRevealEnabled()).toBe(true)
  })

  it('persists an opt-out and reads it back as disabled', () => {
    setCastingRevealEnabled(false)
    expect(localStorage.getItem('yijing-casting-reveal')).toBe('off')
    expect(isCastingRevealEnabled()).toBe(false)
  })

  it('persists re-enabling', () => {
    setCastingRevealEnabled(false)
    setCastingRevealEnabled(true)
    expect(isCastingRevealEnabled()).toBe(true)
  })
})
