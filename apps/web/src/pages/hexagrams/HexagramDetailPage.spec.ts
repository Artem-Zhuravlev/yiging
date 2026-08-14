import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { reactive } from 'vue'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import HexagramDetailPage from './HexagramDetailPage.vue'
import { fetchHexagram } from '../../entities/hexagram/api'
import { ApiError } from '../../shared/api/http'
import type { Hexagram } from '../../entities/hexagram/model'

vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagram: vi.fn(),
}))

const route = reactive({ params: { number: '1' } })

vi.mock('vue-router', () => ({
  useRoute: () => route,
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

const sampleHexagram: Hexagram = {
  kingWenNumber: 1,
  chineseName: '乾',
  pinyin: 'Qián',
  symbol: '䷀',
  lines: Array.from({ length: 6 }, (_, i) => ({ position: i + 1, polarity: 'yang' as const })),
  upperTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
  lowerTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
  judgment: null,
  image: null,
  lineStatements: null,
  relationships: {
    nuclear: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    reversed: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    complement: { kingWenNumber: 2, chineseName: '坤', pinyin: 'Kūn' },
  },
}

describe('HexagramDetailPage', () => {
  let wrapper: VueWrapper | undefined

  beforeEach(() => {
    route.params.number = '1'
  })

  // Every mounted instance's watch() keeps listening on the shared `route` mock above until
  // unmounted — without this, a later test's route-param change would also re-trigger earlier
  // tests' still-live component instances, polluting the shared fetchHexagram mock's call queue.
  afterEach(() => {
    wrapper?.unmount()
  })

  it('renders the fetched hexagram, with a placeholder for null classical text', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('乾')
    expect(wrapper.text()).toContain('Not yet available.')
  })

  it('renders the hexagram symbol and a source attribution line', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('h1').text()).toContain('䷀')
    expect(wrapper.text()).toContain('James Legge')
    expect(wrapper.text()).toContain('1899')
  })

  it('shows a placeholder when lineStatements is null', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    const lineTextsHeading = wrapper.findAll('h2').find((h) => h.text() === 'Line Texts')!
    expect(lineTextsHeading.element.nextElementSibling?.textContent).toBe('Not yet available.')
  })

  it('renders all six line texts, position 6 first', async () => {
    const withLineStatements: Hexagram = {
      ...sampleHexagram,
      lineStatements: ['first', 'second', 'third', 'fourth', 'fifth', 'sixth'],
    }
    vi.mocked(fetchHexagram).mockResolvedValue(withLineStatements)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    const items = wrapper.findAll('ol li')
    expect(items).toHaveLength(6)
    expect(items[0]!.text()).toContain('Line 6')
    expect(items[0]!.text()).toContain('sixth')
    expect(items[5]!.text()).toContain('Line 1')
    expect(items[5]!.text()).toContain('first')
  })

  it('renders self-referential relationships as plain text, not links', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    // sampleHexagram is kingWenNumber 1, whose nuclear and reversed are both itself (1).
    const selfEntries = wrapper.findAll('dd span')
    expect(selfEntries).toHaveLength(2)
    expect(selfEntries[0]!.text()).toContain('(self)')
    expect(selfEntries[1]!.text()).toContain('(self)')
  })

  it('renders non-self relationships as navigable links', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    // complement is hexagram 2 (坤), distinct from the current hexagram 1 — must be a link.
    const relationshipLinks = wrapper.findAll('dd a')
    expect(relationshipLinks).toHaveLength(1)
    expect(relationshipLinks[0]!.text()).toContain('坤')
    expect(relationshipLinks[0]!.text()).not.toContain('(self)')
  })

  it('re-fetches and replaces the displayed hexagram when the route param changes', async () => {
    const otherHexagram: Hexagram = {
      ...sampleHexagram,
      kingWenNumber: 54,
      chineseName: '歸妹',
      pinyin: 'Guī Mèi',
      symbol: '䷻',
    }
    vi.mocked(fetchHexagram).mockResolvedValueOnce(sampleHexagram)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()
    expect(wrapper.find('h1').text()).toBe('䷀ 1. 乾')

    vi.mocked(fetchHexagram).mockResolvedValueOnce(otherHexagram)
    route.params.number = '54'
    await flushPromises()
    await flushPromises()

    expect(fetchHexagram).toHaveBeenCalledWith(54)
    expect(wrapper.find('h1').text()).toBe('䷻ 54. 歸妹')
  })

  it('shows a not-found state on a 404', async () => {
    vi.mocked(fetchHexagram).mockRejectedValue(new ApiError(404, 'Not Found'))

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text().toLowerCase()).toContain('not found')
  })

  it('shows a generic error state for a non-404 failure', async () => {
    vi.mocked(fetchHexagram).mockRejectedValue(new Error('network down'))

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
