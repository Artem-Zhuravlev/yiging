import { describe, it, expect } from 'vitest'
import { consultationToMarkdown, slugifyForFilename } from './consultationMarkdown'
import type { Consultation } from '../../entities/consultation/model'

// Stub t: return the last segment of the key so section headings are recognisable, and
// interpolate {date}/{question} where used.
const t = (key: string, named?: Record<string, unknown>): string => {
  const label = key.split('.').pop() ?? key
  if (named) return Object.entries(named).reduce((s, [k, v]) => s.replace(`{${k}}`, String(v)), label)
  return label
}

function base(overrides: Partial<Consultation> = {}): Consultation {
  return {
    id: 'c1',
    question: 'Should I take the offer?',
    method: 'three_coins',
    primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    changingLinePositions: [],
    resultingHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    createdAt: '2026-08-14T10:00:00+00:00',
    notes: [],
    tags: [],
    context: null,
    whatHappenedBefore: null,
    whatUserWantsToUnderstand: null,
    backgroundInformation: null,
    initialInterpretation: null,
    outcome: null,
    followUpTo: null,
    followUps: [],
    favorite: false,
    ...overrides,
  }
}

describe('consultationToMarkdown', () => {
  it('renders header, metadata, hexagrams and changing lines for a bare consultation, and omits empty sections', () => {
    const md = consultationToMarkdown(base(), t)

    expect(md).toContain('# Should I take the offer?')
    expect(md).toContain('three_coins ·')
    expect(md).toContain('**primary:** 1. 乾 (Qián)')
    expect(md).toContain('**resulting:** 1. 乾 (Qián)')
    expect(md).toContain('**changingLines:** noChangingLines')
    expect(md).not.toContain('## notes')
    expect(md).not.toContain('## context')
    expect(md).not.toContain('## outcome')
    expect(md).not.toContain('## followUp')
    expect(md).toContain('---')
    expect(md).toContain('exportedOn')
  })

  it('renders every section for a fully-populated consultation', () => {
    const md = consultationToMarkdown(
      base({
        changingLinePositions: [1, 4],
        notes: [{ label: 'before', text: 'Feeling torn.', createdAt: '2026-08-14T09:00:00+00:00' }],
        tags: ['career', 'money'],
        context: 'New role, more travel.',
        outcome: {
          whatActuallyHappened: 'Accepted; relocated.',
          outcome: 'Positive so far.',
          reflection: null,
          recordedAt: '2026-08-21T10:00:00+00:00',
          interpretationLens: null,
          interpretationSummary: null,
        },
        followUpTo: { id: 'c0', question: 'Earlier job question?' },
        followUps: [{ id: 'c2', question: 'What to watch for in month one?' }],
      }),
      t,
    )

    expect(md).toContain('**changingLines:** 1, 4')
    expect(md).toContain('## notes')
    expect(md).toContain('- **before:** Feeling torn.')
    expect(md).toContain('**tags:** career, money')
    expect(md).toContain('## context')
    expect(md).toContain('- **context:** New role, more travel.')
    expect(md).toContain('## outcome')
    expect(md).toContain('- **whatHappened:** Accepted; relocated.')
    expect(md).toContain('- **outcomeField:** Positive so far.')
    expect(md).not.toContain('- **reflection:**')
    expect(md).toContain('## followUp')
    expect(md).toContain('followsUpOn')
    expect(md).toContain('- What to watch for in month one?')
  })
})

describe('slugifyForFilename', () => {
  it('slugifies a question and truncates', () => {
    expect(slugifyForFilename('Should I take the offer?!')).toBe('should-i-take-the-offer')
  })

  it('falls back to "consultation" for an empty slug', () => {
    expect(slugifyForFilename('   ??? ')).toBe('consultation')
  })
})
