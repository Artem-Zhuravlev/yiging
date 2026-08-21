import { apiPost } from '../../shared/api/http'
import type { ConversationExchange, FollowUpAnswer, Interpretation, InterpretationLens } from './model'

export function requestInterpretation(
  consultationId: string,
  lens?: InterpretationLens,
): Promise<Interpretation> {
  return apiPost<Interpretation>(`/interpretations/${consultationId}`, lens ? { lens } : {})
}

export function requestFollowUp(
  consultationId: string,
  question: string,
  history: ConversationExchange[],
): Promise<FollowUpAnswer> {
  return apiPost<FollowUpAnswer>(`/interpretations/${consultationId}/followup`, { question, history })
}
