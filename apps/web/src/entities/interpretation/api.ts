import { apiPost } from '../../shared/api/http'
import type { Interpretation } from './model'

export function requestInterpretation(consultationId: string): Promise<Interpretation> {
  return apiPost<Interpretation>(`/interpretations/${consultationId}`, {})
}
