import { describe, it, expect, vi, afterEach } from 'vitest'
import { fetchHexagrams, fetchHexagram } from './api'
import type { Hexagram } from './model'

const sample: Hexagram = {
  kingWenNumber: 11,
  chineseName: '泰',
  pinyin: 'Tài',
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
})
