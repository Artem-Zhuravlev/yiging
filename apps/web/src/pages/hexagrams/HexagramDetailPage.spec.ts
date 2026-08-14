import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import HexagramDetailPage from './HexagramDetailPage.vue'
import { fetchHexagram } from '../../entities/hexagram/api'
import { ApiError } from '../../shared/api/http'
import type { Hexagram } from '../../entities/hexagram/model'

vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagram: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { number: '1' } }),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

const sampleHexagram: Hexagram = {
  kingWenNumber: 1,
  chineseName: '乾',
  pinyin: 'Qián',
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
  it('renders the fetched hexagram, with a placeholder for null classical text', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    const wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('乾')
    expect(wrapper.text()).toContain('Not yet available.')
  })

  it('shows a not-found state on a 404', async () => {
    vi.mocked(fetchHexagram).mockRejectedValue(new ApiError(404, 'Not Found'))

    const wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text().toLowerCase()).toContain('not found')
  })

  it('shows a generic error state for a non-404 failure', async () => {
    vi.mocked(fetchHexagram).mockRejectedValue(new Error('network down'))

    const wrapper = mount(HexagramDetailPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
