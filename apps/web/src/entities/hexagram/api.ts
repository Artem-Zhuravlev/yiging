import { apiGet } from '../../shared/api/http'
import type { Hexagram } from './model'

export function fetchHexagrams(): Promise<Hexagram[]> {
  return apiGet<Hexagram[]>('/hexagrams')
}

export function fetchHexagram(kingWenNumber: number): Promise<Hexagram> {
  return apiGet<Hexagram>(`/hexagrams/${kingWenNumber}`)
}
