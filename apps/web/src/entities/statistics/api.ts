import { apiGet } from '../../shared/api/http'
import type { Statistics } from './model'

export function fetchStatistics(): Promise<Statistics> {
  return apiGet<Statistics>('/statistics')
}
