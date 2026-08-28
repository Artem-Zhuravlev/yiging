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
  it('renders total count, hexagram + tag bar charts, and the yin/yang donut', async () => {
    vi.mocked(fetchStatistics).mockResolvedValue(sample)

    const wrapper = mount(StatisticsPage)
    await flushPromises()

    expect(wrapper.text()).toContain('3 consultations')
    // hexagram frequency is now a bar chart (table): row headers "1. 乾" / "2. 坤"
    const rowHeaders = wrapper.findAll('th[scope="row"]').map((th) => th.text())
    expect(rowHeaders).toContain('1. 乾')
    expect(rowHeaders).toContain('2. 坤')
    expect(rowHeaders).toContain('career')
    // yin/yang ratio is a donut with the count/percent line as its caption + an aria summary
    const donut = wrapper.find('svg[role="img"]')
    expect(donut.attributes('aria-label')).toContain('33% Yin')
    expect(donut.attributes('aria-label')).toContain('67% Yang')
    expect(wrapper.text()).toContain('6 yin / 12 yang (33% / 67%)')
  })

  it('caps the hexagram chart at 12 rows and notes how many more there are', async () => {
    vi.mocked(fetchStatistics).mockResolvedValue({
      ...sample,
      hexagramFrequency: Array.from({ length: 15 }, (_, i) => ({
        kingWenNumber: i + 1,
        chineseName: `H${i + 1}`,
        pinyin: 'x',
        count: 15 - i,
      })),
    })

    const wrapper = mount(StatisticsPage)
    await flushPromises()

    expect(wrapper.findAll('th[scope="row"]').filter((th) => /\bH\d+$/.test(th.text()))).toHaveLength(12)
    expect(wrapper.text()).toContain('+3 more')
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
