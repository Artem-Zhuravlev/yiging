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

/** The classical intra-hexagram relationships for one line (SPEC-053) — position, centrality,
 * correspondence with its partner, and riding/receiving the firm. Present only on the
 * single-hexagram and editor-preview responses, not the 64-item list. */
export interface LineDynamic {
  position: number
  correctPosition: boolean
  central: boolean
  centralAndCorrect: boolean
  correspondsWith: number
  corresponds: boolean
  ridesFirmBelow: boolean
  supportsFirmAbove: boolean
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
  /** 6 entries, position order — only on `GET /api/hexagrams/{id}` and `.../from-lines`. */
  lineDynamics?: LineDynamic[]
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
