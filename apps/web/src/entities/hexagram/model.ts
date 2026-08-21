export type LinePolarity = 'yin' | 'yang'

export interface HexagramLine {
  position: number
  polarity: LinePolarity
  /** Set only when this line is part of a cast's changing lines (a Consultation's primary
   * hexagram) — absent/false for plain structural browsing (SPEC-007). */
  changing?: boolean
}

export interface TrigramSummary {
  id: string
  name: string
  chineseName: string
  pinyin: string
  symbol: string
}

export interface HexagramSummary {
  kingWenNumber: number
  chineseName: string
  pinyin: string
}

export interface HexagramRelationships {
  nuclear: HexagramSummary
  reversed: HexagramSummary
  complement: HexagramSummary
}

export interface Hexagram {
  kingWenNumber: number
  chineseName: string
  pinyin: string
  symbol: string
  lines: HexagramLine[]
  upperTrigram: TrigramSummary
  lowerTrigram: TrigramSummary
  judgment: string | null
  image: string | null
  lineStatements: string[] | null
  relationships: HexagramRelationships
  favorite: boolean
}

export interface LineComparison {
  position: number
  aPolarity: LinePolarity
  bPolarity: LinePolarity
  changed: boolean
}

export interface HexagramComparison {
  a: Hexagram
  b: Hexagram
  lineComparisons: LineComparison[]
  upperTrigramDiffers: boolean
  lowerTrigramDiffers: boolean
}
