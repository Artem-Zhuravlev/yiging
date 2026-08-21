export type InterpretationLens = 'general' | 'psychological' | 'practical' | 'symbolic'

export const INTERPRETATION_LENSES: InterpretationLens[] = [
  'general',
  'psychological',
  'practical',
  'symbolic',
]

export interface Interpretation {
  summary: string
  coreTheme: string
  situation: string
  changingLineMeaning: string | null
  transition: string | null
  practicalReflection: string
  uncertainties: string[]
  sourceReferences: string[]
  lens: InterpretationLens
}

/** One prior round of a follow-up conversation about an interpretation (SPEC-034) — sent back
 * to the server with each new follow-up request, since conversations aren't persisted. */
export interface ConversationExchange {
  question: string
  answer: string
}

export interface FollowUpAnswer {
  answer: string
  sourceReferences: string[]
}
