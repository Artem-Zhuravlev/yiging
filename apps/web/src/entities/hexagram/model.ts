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

export interface Hexagram {
  kingWenNumber: number
  chineseName: string
  pinyin: string
  lines: HexagramLine[]
  upperTrigram: TrigramSummary
  lowerTrigram: TrigramSummary
  judgment: string | null
  image: string | null
  lineStatements: string[] | null
}
