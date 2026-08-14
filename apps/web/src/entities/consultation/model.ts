import type { LinePolarity } from '../hexagram/model'

/** Methods a user may choose from the New Consultation form. Deliberately excludes
 * 'random' — SPEC-004 documents it as non-traditional dev/test tooling, never something a
 * real reading should be attributed to. */
export type SelectableCastingMethod = 'three_coins' | 'manual'

/** The full set of methods a *stored* consultation may report (the API itself doesn't
 * restrict `random`, even though this UI never submits it). */
export type CastingMethod = SelectableCastingMethod | 'random'

export interface ManualLine {
  polarity: LinePolarity
  changing: boolean
}

export interface HexagramSummary {
  kingWenNumber: number
  chineseName: string
  pinyin: string
}

export interface ConsultationNote {
  label: 'before' | 'after' | 'later'
  text: string
  createdAt: string
}

export interface Consultation {
  id: string
  question: string
  method: CastingMethod
  primaryHexagram: HexagramSummary
  changingLinePositions: number[]
  resultingHexagram: HexagramSummary
  createdAt: string
  notes: ConsultationNote[]
  tags: string[]
}

export type NewConsultationRequest =
  | { question: string; method: 'three_coins' }
  | { question: string; method: 'manual'; lines: ManualLine[] }
