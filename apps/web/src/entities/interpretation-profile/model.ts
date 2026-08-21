export type Tone = 'neutral' | 'formal' | 'casual' | 'poetic'
export type ResponseLength = 'standard' | 'brief' | 'detailed'

export const TONES: Tone[] = ['neutral', 'formal', 'casual', 'poetic']
export const RESPONSE_LENGTHS: ResponseLength[] = ['standard', 'brief', 'detailed']

/** A single, global standing preference for how interpretations/follow-up answers are written
 * (SPEC-035) — this app has no accounts, so there is exactly one profile. */
export interface InterpretationProfile {
  tone: Tone
  length: ResponseLength
  notes: string | null
}

export interface InterpretationProfilePatch {
  tone?: Tone
  length?: ResponseLength
  notes?: string | null
}
