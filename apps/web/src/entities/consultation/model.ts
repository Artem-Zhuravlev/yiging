import type { LinePolarity } from '../hexagram/model'
import type { InterpretationLens } from '../interpretation/model'

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

/** The optional richer-context fields a consultation may carry beyond its one-line question
 * (SPEC-019) — all independently settable, none required. */
export interface ConsultationContext {
  context: string | null
  whatHappenedBefore: string | null
  whatUserWantsToUnderstand: string | null
  backgroundInformation: string | null
  initialInterpretation: string | null
}

/** A separate historical record of what actually happened, kept structurally distinct from the
 * original consultation and its interpretation (SPEC-020) — null until the user records
 * something, never modifying `question`/hexagrams/interpretation. */
export interface ConsultationOutcome {
  whatActuallyHappened: string | null
  outcome: string | null
  reflection: string | null
  recordedAt: string
  /** Links this outcome to the AI interpretation the user consulted before it happened
   * (SPEC-036) — a small `{lens, summary}` snapshot, never the full Interpretation, matching
   * the "AI output is never persisted" stance except for this narrow, explicit exception. */
  interpretationLens: InterpretationLens | null
  interpretationSummary: string | null
}

/** A minimal display shape for a linked consultation (SPEC-021) — deliberately not a full
 * Consultation, matching the backend's own ConsultationSummary. */
export interface ConsultationSummary {
  id: string
  question: string
}

export interface Consultation extends ConsultationContext {
  id: string
  question: string
  method: CastingMethod
  primaryHexagram: HexagramSummary
  changingLinePositions: number[]
  resultingHexagram: HexagramSummary
  createdAt: string
  notes: ConsultationNote[]
  tags: string[]
  outcome: ConsultationOutcome | null
  followUpTo: ConsultationSummary | null
  followUps: ConsultationSummary[]
  favorite: boolean
}

/** The lean row shape `GET /api/consultations` returns per consultation (SPEC-041) — everything
 * the History page's cards and date grouping need, without the notes/context/outcome/follow-up
 * payload of a full `Consultation`. The full object is still fetched per-consultation on the
 * detail page, and in bulk (via `/consultations/export`) for backup. */
export interface ConsultationListItem {
  id: string
  question: string
  method: CastingMethod
  primaryHexagram: HexagramSummary
  changingLinePositions: number[]
  resultingHexagram: HexagramSummary
  createdAt: string
  tags: string[]
  favorite: boolean
}

/** One page of `GET /api/consultations` (SPEC-041). `nextCursor` is non-null exactly when more
 * rows exist after this page; pass it back as `cursor` to fetch the next one. */
export interface ConsultationListPage {
  items: ConsultationListItem[]
  nextCursor: string | null
}

export interface ConsultationListParams {
  limit?: number
  cursor?: string | null
  q?: string
  tags?: string[]
  favorite?: boolean
}

/** Other consultations sharing this one's primary hexagram, resulting hexagram, or exact
 * changing-line set (SPEC-023) — only present on the single-consultation detail response, never
 * on the list/create/update responses (see ConsultationDetail). */
export interface ConsultationRepeats {
  primaryHexagram: ConsultationSummary[]
  resultingHexagram: ConsultationSummary[]
  changingLines: ConsultationSummary[]
}

/** The shape `GET /api/consultations/{id}` returns — a `Consultation` plus `repeats`, computed
 * only for the single-detail endpoint (SPEC-023). */
export interface ConsultationDetail extends Consultation {
  repeats: ConsultationRepeats
}

export type NewConsultationRequest = (
  | { question: string; method: 'three_coins' }
  | { question: string; method: 'manual'; lines: ManualLine[] }
) &
  Partial<ConsultationContext> & { followUpToConsultationId?: string }

export interface ConsultationPatch
  extends Partial<ConsultationContext>,
    Partial<Omit<ConsultationOutcome, 'recordedAt'>> {
  note?: { label: ConsultationNote['label']; text: string }
  tag?: string
  followUpToConsultationId?: string | null
  favorite?: boolean
}
