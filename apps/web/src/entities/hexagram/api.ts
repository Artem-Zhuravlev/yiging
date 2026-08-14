import { apiGet } from '../../shared/api/http'
import type { Hexagram, HexagramComparison, LinePolarity } from './model'

export function fetchHexagrams(): Promise<Hexagram[]> {
  return apiGet<Hexagram[]>('/hexagrams')
}

export function fetchHexagram(kingWenNumber: number): Promise<Hexagram> {
  return apiGet<Hexagram>(`/hexagrams/${kingWenNumber}`)
}

/** @param polarities exactly 6 entries, bottom to top */
export function computeHexagramFromLines(polarities: LinePolarity[]): Promise<Hexagram> {
  return apiGet<Hexagram>(`/hexagrams/from-lines?lines=${polarities.join(',')}`)
}

export function compareHexagrams(a: number, b: number): Promise<HexagramComparison> {
  return apiGet<HexagramComparison>(`/hexagrams/compare?a=${a}&b=${b}`)
}
