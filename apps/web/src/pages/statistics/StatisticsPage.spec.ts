import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import StatisticsPage from './StatisticsPage.vue'
import { fetchStatistics } from '../../entities/statistics/api'
import type { Statistics } from '../../entities/statistics/model'

vi.mock('../../entities/statistics/api', () => ({
  fetchStatistics: vi.fn(),
}))

const sample: Statistics = {
  totalConsultations: 3,
  hexagramFrequency: [
    { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián', count: 2 },
    { kingWenNumber: 2, chineseName: '坤', pinyin: 'Kūn', count: 1 },
  ],
  yinYangRatio: { yin: 6, yang: 12 },
  tagFrequency: [{ name: 'career', count: 2 }],
}

describe('StatisticsPage', () => {
  it('renders total count, hexagram frequency, yin/yang ratio, and tag frequency', async () => {
    vi.mocked(fetchStatistics).mockResolvedValue(sample)

    const wrapper = mount(StatisticsPage)
    await flushPromises()

    expect(wrapper.text()).toContain('3 consultations')
    expect(wrapper.text()).toContain('1. 乾 (Qián)')
    expect(wrapper.text()).toContain('2. 坤 (Kūn)')
    expect(wrapper.text()).toContain('6 yin / 12 yang (33% / 67%)')
    expect(wrapper.text()).toContain('career')
  })

  it('shows a distinct empty-history message when there are no consultations', async () => {
    vi.mocked(fetchStatistics).mockResolvedValue({
      totalConsultations: 0,
      hexagramFrequency: [],
      yinYangRatio: { yin: 0, yang: 0 },
      tagFrequency: [],
    })

    const wrapper = mount(StatisticsPage)
    await flushPromises()

    expect(wrapper.text()).toContain('No consultations yet')
    expect(wrapper.text()).not.toContain('Hexagram frequency')
  })

  it('omits the tag frequency section when there are no tags', async () => {
    vi.mocked(fetchStatistics).mockResolvedValue({ ...sample, tagFrequency: [] })

    const wrapper = mount(StatisticsPage)
    await flushPromises()

    expect(wrapper.text()).not.toContain('Tag frequency')
  })

  it('shows an error message when the fetch fails', async () => {
    vi.mocked(fetchStatistics).mockRejectedValue(new Error('network down'))

    const wrapper = mount(StatisticsPage)
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
