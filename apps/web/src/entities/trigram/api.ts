import { apiGet } from '../../shared/api/http'
import type { Trigram } from './model'

export function fetchTrigrams(): Promise<Trigram[]> {
  return apiGet<Trigram[]>('/trigrams')
}
