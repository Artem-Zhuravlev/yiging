export interface HexagramFrequency {
  kingWenNumber: number
  chineseName: string
  pinyin: string
  count: number
}

export interface TagFrequency {
  name: string
  count: number
}

/** One aggregate snapshot over the whole consultation history (SPEC-024) — hexagram frequency
 * and the yin/yang ratio are both computed over each consultation's primary (as-cast) hexagram. */
export interface Statistics {
  totalConsultations: number
  hexagramFrequency: HexagramFrequency[]
  yinYangRatio: { yin: number; yang: number }
  tagFrequency: TagFrequency[]
}
