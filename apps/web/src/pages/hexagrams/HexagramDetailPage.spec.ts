import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { reactive } from 'vue'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import HexagramDetailPage from './HexagramDetailPage.vue'
import {
  fetchHexagram,
  markHexagramFavorite,
  unmarkHexagramFavorite,
} from '../../entities/hexagram/api'
import { ApiError } from '../../shared/api/http'
import type { Hexagram } from '../../entities/hexagram/model'

vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagram: vi.fn(),
  markHexagramFavorite: vi.fn(),
  unmarkHexagramFavorite: vi.fn(),
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
  favorite: false,
}

describe('HexagramDetailPage', () => {
  let wrapper: VueWrapper | undefined

  beforeEach(() => {
    route.params.number = '1'
    vi.mocked(markHexagramFavorite).mockClear().mockResolvedValue(undefined)
    vi.mocked(unmarkHexagramFavorite).mockClear().mockResolvedValue(undefined)
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

  it('toggles the favorite state via the toggle button', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    const button = wrapper.findAll('button').find((b) => b.text().includes('Add to Favorites'))!
    await button.trigger('click')
    await flushPromises()

    expect(markHexagramFavorite).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('★ Favorited')
  })

  it('shows an inline error when toggling favorite fails', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)
    vi.mocked(markHexagramFavorite).mockRejectedValue(new Error('favorite toggle failed'))

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    const button = wrapper.findAll('button').find((b) => b.text().includes('Add to Favorites'))!
    await button.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('favorite toggle failed')
  })

  it('reveals a line statement inline when its diagram line is clicked, and hides it on a second click', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue({
      ...sampleHexagram,
      lineStatements: ['first', 'second', 'third', 'fourth', 'fifth', 'sixth'],
    })

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    const lineButtons = wrapper.findAll('button[aria-label^="Line "]')
    expect(lineButtons).toHaveLength(6)

    // Buttons render top-to-bottom: index 0 is line 6, index 4 is line 2.
    const lineTwo = lineButtons.find((b) => b.attributes('aria-label') === 'Line 2')!
    await lineTwo.trigger('click')

    const panel = wrapper.findAll('h3').find((h) => h.text() === 'Line 2')!.element.parentElement!
    expect(panel.textContent).toContain('second')
    expect(lineTwo.attributes('aria-pressed')).toBe('true')
    // The matching entry in the bottom list is highlighted.
    const highlighted = wrapper.findAll('ol li').filter((li) => li.classes().includes('line-text-selected'))
    expect(highlighted).toHaveLength(1)
    expect(highlighted[0]!.text()).toContain('Line 2')

    await lineTwo.trigger('click')
    expect(wrapper.findAll('h3').some((h) => h.text() === 'Line 2')).toBe(false)
  })

  it('renders the line-dynamics section: the 2–5 correspondence row and per-line placement', async () => {
    // Ji Ji-style: every line correctly placed, every pair corresponds.
    const partner: Record<number, number> = { 1: 4, 2: 5, 3: 6, 4: 1, 5: 2, 6: 3 }
    const lineDynamics = Array.from({ length: 6 }, (_, i) => {
      const position = i + 1
      return {
        position,
        correctPosition: true,
        central: position === 2 || position === 5,
        centralAndCorrect: position === 2 || position === 5,
        correspondsWith: partner[position]!,
        corresponds: true,
        ridesFirmBelow: false,
        supportsFirmAbove: false,
      }
    })

    vi.mocked(fetchHexagram).mockResolvedValue({
      ...sampleHexagram,
      lineStatements: ['a', 'b', 'c', 'd', 'e', 'f'],
      lineDynamics,
    })

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Line dynamics')
    // correspondence summary lists the three pairs
    expect(wrapper.text()).toContain('Lines 2 & 5')
    expect(wrapper.text()).toContain('correspond (正應)')
    // per-line table: 6 rows, position 5 marked central & correct
    const rows = wrapper.findAll('.line-dynamics-table tbody tr')
    expect(rows).toHaveLength(6)
    const rowFive = rows.find((r) => r.attributes('data-position') === '5')!
    expect(rowFive.text()).toContain('central & correct (中正)')
    expect(rowFive.text()).toContain('correct (當位)')
  })

  it('does not make the diagram interactive when the hexagram has no classical line text', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.findAll('button[aria-label^="Line "]')).toHaveLength(0)
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
