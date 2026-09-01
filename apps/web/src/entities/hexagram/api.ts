import { apiDelete, apiGet, apiPut } from '../../shared/api/http'
import type { Hexagram, HexagramComparison, LinePolarity } from './model'

/** `?lang=` for the classical text (Judgment / Image / line texts / sequence sentence) —
 * appended only when a non-English locale is requested (SPEC-057). */
function langQuery(lang: string, separator: '?' | '&' = '?'): string {
  return lang && lang !== 'en' ? `${separator}lang=${lang}` : ''
}

export function fetchHexagrams(lang = 'en'): Promise<Hexagram[]> {
  return apiGet<Hexagram[]>(`/hexagrams${langQuery(lang)}`)
}

export function fetchHexagram(kingWenNumber: number, lang = 'en'): Promise<Hexagram> {
  return apiGet<Hexagram>(`/hexagrams/${kingWenNumber}${langQuery(lang)}`)
}

/** @param polarities exactly 6 entries, bottom to top */
export function computeHexagramFromLines(polarities: LinePolarity[]): Promise<Hexagram> {
  return apiGet<Hexagram>(`/hexagrams/from-lines?lines=${polarities.join(',')}`)
}

export function compareHexagrams(a: number, b: number, lang = 'en'): Promise<HexagramComparison> {
  return apiGet<HexagramComparison>(`/hexagrams/compare?a=${a}&b=${b}${langQuery(lang, '&')}`)
}

export function markHexagramFavorite(kingWenNumber: number): Promise<void> {
  return apiPut(`/hexagrams/${kingWenNumber}/favorite`)
}

export function unmarkHexagramFavorite(kingWenNumber: number): Promise<void> {
  return apiDelete(`/hexagrams/${kingWenNumber}/favorite`)
}
