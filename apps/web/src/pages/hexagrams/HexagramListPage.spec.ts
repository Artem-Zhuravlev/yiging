import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import HexagramListPage from './HexagramListPage.vue'
import {
  fetchHexagrams,
  markHexagramFavorite,
  unmarkHexagramFavorite,
} from '../../entities/hexagram/api'
import type { Hexagram } from '../../entities/hexagram/model'

vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagrams: vi.fn(),
  markHexagramFavorite: vi.fn(),
  unmarkHexagramFavorite: vi.fn(),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

function sampleHexagram(kingWenNumber: number, overrides: Partial<Hexagram> = {}): Hexagram {
  return {
    kingWenNumber,
    chineseName: '乾',
    pinyin: 'Qián',
    symbol: '䷀',
    lines: Array.from({ length: 6 }, (_, i) => ({
      position: i + 1,
      polarity: 'yang' as const,
    })),
    upperTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
    lowerTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
    judgment: null,
    image: null,
    lineStatements: null,
    relationships: {
      nuclear: { kingWenNumber, chineseName: '乾', pinyin: 'Qián' },
      reversed: { kingWenNumber, chineseName: '乾', pinyin: 'Qián' },
      complement: { kingWenNumber, chineseName: '乾', pinyin: 'Qián' },
    },
    favorite: false,
    ...overrides,
  }
}

describe('HexagramListPage', () => {
  beforeEach(() => {
    vi.mocked(markHexagramFavorite).mockClear().mockResolvedValue(undefined)
    vi.mocked(unmarkHexagramFavorite).mockClear().mockResolvedValue(undefined)
  })

  it('links to the Visual Editor', () => {
    vi.mocked(fetchHexagrams).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(HexagramListPage, { global: { stubs } })

    expect(wrapper.findAll('a').some((a) => a.text() === 'Visual Editor')).toBe(true)
  })

  it('shows a loading state before the fetch resolves', () => {
    vi.mocked(fetchHexagrams).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(HexagramListPage, { global: { stubs } })

    expect(wrapper.text()).toContain('Loading')
  })

  it('renders every fetched hexagram once loaded', async () => {
    vi.mocked(fetchHexagrams).mockResolvedValue([sampleHexagram(1), sampleHexagram(2)])

    const wrapper = mount(HexagramListPage, { global: { stubs } })
    await flushPromises()

    const links = wrapper.findAll('ul a')
    expect(links).toHaveLength(2)
    expect(wrapper.text()).toContain('乾')
  })

  it('shows an error message when the fetch fails', async () => {
    vi.mocked(fetchHexagrams).mockRejectedValue(new Error('network down'))

    const wrapper = mount(HexagramListPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })

  it('filters by Chinese name, pinyin, Judgment, or Image text, case-insensitively', async () => {
    vi.mocked(fetchHexagrams).mockResolvedValue([
      sampleHexagram(1, { chineseName: '乾', pinyin: 'Qián', judgment: 'Great success.', image: null }),
      sampleHexagram(2, { chineseName: '坤', pinyin: 'Kūn', judgment: null, image: 'The Earth image.' }),
      sampleHexagram(3, { chineseName: '屯', pinyin: 'Zhūn', judgment: 'Difficulty.', image: 'Clouds.' }),
    ])

    const wrapper = mount(HexagramListPage, { global: { stubs } })
    await flushPromises()

    await wrapper.find('input[type="search"]').setValue('EARTH')

    expect(wrapper.text()).toContain('坤')
    expect(wrapper.text()).not.toContain('乾')
    expect(wrapper.text()).not.toContain('屯')
  })

  it('shows all hexagrams again when the search is cleared', async () => {
    vi.mocked(fetchHexagrams).mockResolvedValue([sampleHexagram(1), sampleHexagram(2)])

    const wrapper = mount(HexagramListPage, { global: { stubs } })
    await flushPromises()

    const input = wrapper.find('input[type="search"]')
    await input.setValue('nonexistent query')
    expect(wrapper.text()).toContain('No hexagrams match your search.')

    await input.setValue('')
    expect(wrapper.findAll('ul a')).toHaveLength(2)
  })

  it('toggles a hexagram favorite via its star button', async () => {
    vi.mocked(fetchHexagrams).mockResolvedValue([sampleHexagram(1, { favorite: false })])

    const wrapper = mount(HexagramListPage, { global: { stubs } })
    await flushPromises()

    const star = wrapper.find('button[aria-label="Add to favorites"]')
    await star.trigger('click')
    await flushPromises()

    expect(markHexagramFavorite).toHaveBeenCalledWith(1)
    expect(wrapper.find('button[aria-label="Remove from favorites"]').exists()).toBe(true)
  })

  it('narrows the grid to favorites only', async () => {
    vi.mocked(fetchHexagrams).mockResolvedValue([
      sampleHexagram(1, { chineseName: '乾', favorite: true }),
      sampleHexagram(2, { chineseName: '坤', favorite: false }),
    ])

    const wrapper = mount(HexagramListPage, { global: { stubs } })
    await flushPromises()

    const favoritesToggle = wrapper.findAll('button').find((b) => b.text().includes('Favorites only'))!
    await favoritesToggle.trigger('click')

    expect(wrapper.text()).toContain('乾')
    expect(wrapper.text()).not.toContain('坤')
  })
})
