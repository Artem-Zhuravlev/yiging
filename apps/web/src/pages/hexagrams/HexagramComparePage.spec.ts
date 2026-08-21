import { describe, it, expect, vi, afterEach } from 'vitest'
import { reactive } from 'vue'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import HexagramComparePage from './HexagramComparePage.vue'
import { compareHexagrams } from '../../entities/hexagram/api'
import type { Hexagram, HexagramComparison } from '../../entities/hexagram/model'

vi.mock('../../entities/hexagram/api', () => ({
  compareHexagrams: vi.fn(),
}))

const route = reactive({ query: {} as Record<string, string> })
const routerPush = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ push: routerPush }),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

function sampleHexagram(kingWenNumber: number, chineseName: string): Hexagram {
  return {
    kingWenNumber,
    chineseName,
    pinyin: 'x',
    symbol: '䷀',
    lines: Array.from({ length: 6 }, (_, i) => ({ position: i + 1, polarity: 'yang' as const })),
    upperTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
    lowerTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
    judgment: 'judgment text',
    image: null,
    lineStatements: null,
    relationships: {
      nuclear: { kingWenNumber: 54, chineseName: 'x', pinyin: 'x' },
      reversed: { kingWenNumber: kingWenNumber, chineseName, pinyin: 'x' },
      complement: { kingWenNumber: 2, chineseName: 'x', pinyin: 'x' },
    },
    favorite: false,
  }
}

const comparison: HexagramComparison = {
  a: sampleHexagram(11, '泰'),
  b: { ...sampleHexagram(54, '歸妹'), relationships: sampleHexagram(11, '泰').relationships },
  lineComparisons: [
    { position: 1, aPolarity: 'yang', bPolarity: 'yin', changed: true },
    { position: 2, aPolarity: 'yang', bPolarity: 'yin', changed: true },
    { position: 3, aPolarity: 'yang', bPolarity: 'yang', changed: false },
    { position: 4, aPolarity: 'yin', bPolarity: 'yin', changed: false },
    { position: 5, aPolarity: 'yin', bPolarity: 'yang', changed: true },
    { position: 6, aPolarity: 'yin', bPolarity: 'yin', changed: false },
  ],
  upperTrigramDiffers: true,
  lowerTrigramDiffers: false,
}

describe('HexagramComparePage', () => {
  let wrapper: VueWrapper | undefined

  afterEach(() => {
    wrapper?.unmount()
    route.query = {}
    routerPush.mockClear()
  })

  it('defaults to comparing hexagrams 1 and 2 when no query params are present', async () => {
    vi.mocked(compareHexagrams).mockResolvedValue(comparison)

    wrapper = mount(HexagramComparePage, { global: { stubs } })
    await flushPromises()

    expect(compareHexagrams).toHaveBeenCalledWith(1, 2)
  })

  it('compares the hexagrams named in the query and renders the line table', async () => {
    route.query = { a: '11', b: '54' }
    vi.mocked(compareHexagrams).mockResolvedValue(comparison)

    wrapper = mount(HexagramComparePage, { global: { stubs } })
    await flushPromises()

    expect(compareHexagrams).toHaveBeenCalledWith(11, 54)
    expect(wrapper.text()).toContain('泰')
    expect(wrapper.text()).toContain('歸妹')
    expect(wrapper.findAll('tbody tr')).toHaveLength(6)
    expect(wrapper.text()).toContain('Differ') // upperTrigramDiffers
    expect(wrapper.text()).toContain('Match') // lowerTrigramDiffers
  })

  it('shows a relationship note when one hexagram is related to the other', async () => {
    route.query = { a: '11', b: '54' }
    vi.mocked(compareHexagrams).mockResolvedValue(comparison)

    wrapper = mount(HexagramComparePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain("54 is 11's nuclear hexagram.")
  })

  it('submitting the form navigates with the new a/b query params', async () => {
    vi.mocked(compareHexagrams).mockResolvedValue(comparison)

    wrapper = mount(HexagramComparePage, { global: { stubs } })
    await flushPromises()

    await wrapper.find('input[min="1"]').setValue(11)
    await wrapper.findAll('input[min="1"]')[1]!.setValue(54)
    await wrapper.find('form').trigger('submit')

    expect(routerPush).toHaveBeenCalledWith({ query: { a: '11', b: '54' } })
  })

  it('shows an error message when the comparison fails', async () => {
    vi.mocked(compareHexagrams).mockRejectedValue(new Error('comparison failed'))

    wrapper = mount(HexagramComparePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('comparison failed')
  })
})
