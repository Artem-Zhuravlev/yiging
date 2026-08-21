import { apiPost } from '../../shared/api/http'
import type { Interpretation, InterpretationLens } from './model'

export function requestInterpretation(
  consultationId: string,
  lens?: InterpretationLens,
): Promise<Interpretation> {
  return apiPost<Interpretation>(`/interpretations/${consultationId}`, lens ? { lens } : {})
}
