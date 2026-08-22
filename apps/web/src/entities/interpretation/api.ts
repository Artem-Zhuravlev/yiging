import { apiPost } from '../../shared/api/http'
import type { ConversationExchange, FollowUpAnswer, Interpretation, InterpretationLens } from './model'

export function requestInterpretation(
  consultationId: string,
  lens?: InterpretationLens,
  language?: string,
): Promise<Interpretation> {
  return apiPost<Interpretation>(`/interpretations/${consultationId}`, {
    ...(lens ? { lens } : {}),
    ...(language ? { language } : {}),
  })
}

export function requestFollowUp(
  consultationId: string,
  question: string,
  history: ConversationExchange[],
  language?: string,
): Promise<FollowUpAnswer> {
  return apiPost<FollowUpAnswer>(`/interpretations/${consultationId}/followup`, {
    question,
    history,
    ...(language ? { language } : {}),
  })
}
