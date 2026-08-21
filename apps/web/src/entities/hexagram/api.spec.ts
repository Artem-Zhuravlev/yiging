import { describe, it, expect, vi, afterEach } from 'vitest'
import { fetchHexagrams, fetchHexagram, computeHexagramFromLines, compareHexagrams } from './api'
import type { Hexagram, HexagramComparison } from './model'

const sample: Hexagram = {
  kingWenNumber: 11,
  chineseName: '泰',
  pinyin: 'Tài',
  symbol: '䷊',
  lines: Array.from({ length: 6 }, (_, i) => ({ position: i + 1, polarity: 'yang' as const })),
  upperTrigram: { id: 'Kun', name: 'Kun', chineseName: '坤', pinyin: 'Kūn', symbol: '☷' },
  lowerTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
  judgment: null,
  image: null,
  lineStatements: null,
  relationships: {
    nuclear: { kingWenNumber: 54, chineseName: '歸妹', pinyin: 'Guī Mèi' },
    reversed: { kingWenNumber: 12, chineseName: '否', pinyin: 'Pǐ' },
    complement: { kingWenNumber: 12, chineseName: '否', pinyin: 'Pǐ' },
  },
  favorite: false,
}

describe('entities/hexagram api', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('fetchHexagrams gets /hexagrams', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve([sample]),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchHexagrams()

    expect(result).toEqual([sample])
    expect(fetchMock).toHaveBeenCalledWith('/api/hexagrams')
  })

  it('fetchHexagram gets /hexagrams/{id} and round-trips relationships', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve(sample),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchHexagram(11)

    expect(result).toEqual(sample)
    expect(result.relationships.nuclear.kingWenNumber).toBe(54)
    expect(result.relationships.reversed.kingWenNumber).toBe(12)
    expect(result.relationships.complement.kingWenNumber).toBe(12)
    expect(fetchMock).toHaveBeenCalledWith('/api/hexagrams/11')
  })

  it('computeHexagramFromLines gets /hexagrams/from-lines with a comma-separated query', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve(sample),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await computeHexagramFromLines([
      'yang',
      'yang',
      'yang',
      'yin',
      'yin',
      'yin',
    ])

    expect(result).toEqual(sample)
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/hexagrams/from-lines?lines=yang,yang,yang,yin,yin,yin',
    )
  })

  it('compareHexagrams gets /hexagrams/compare with both King Wen numbers', async () => {
    const comparison: HexagramComparison = {
      a: sample,
      b: { ...sample, kingWenNumber: 44, chineseName: '姤', pinyin: 'Gòu' },
      lineComparisons: [
        { position: 1, aPolarity: 'yang', bPolarity: 'yin', changed: true },
        { position: 2, aPolarity: 'yang', bPolarity: 'yang', changed: false },
        { position: 3, aPolarity: 'yang', bPolarity: 'yang', changed: false },
        { position: 4, aPolarity: 'yin', bPolarity: 'yang', changed: true },
        { position: 5, aPolarity: 'yin', bPolarity: 'yang', changed: true },
        { position: 6, aPolarity: 'yin', bPolarity: 'yang', changed: true },
      ],
      upperTrigramDiffers: true,
      lowerTrigramDiffers: true,
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve(comparison),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await compareHexagrams(11, 44)

    expect(result).toEqual(comparison)
    expect(fetchMock).toHaveBeenCalledWith('/api/hexagrams/compare?a=11&b=44')
  })
})
