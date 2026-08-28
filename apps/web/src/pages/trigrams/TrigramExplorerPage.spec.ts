import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import TrigramExplorerPage from './TrigramExplorerPage.vue'
import { fetchTrigrams } from '../../entities/trigram/api'
import type { Trigram } from '../../entities/trigram/model'

vi.mock('../../entities/trigram/api', () => ({ fetchTrigrams: vi.fn() }))

const trigrams: Trigram[] = [
  { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰', element: 'Metal', familyMember: 'Father', direction: 'Northwest', image: 'Heaven' },
  { id: 'Kun', name: 'Kun', chineseName: '坤', pinyin: 'Kūn', symbol: '☷', element: 'Earth', familyMember: 'Mother', direction: 'Southwest', image: 'Earth' },
  { id: 'Zhen', name: 'Zhen', chineseName: '震', pinyin: 'Zhèn', symbol: '☳', element: 'Wood', familyMember: 'Eldest Son', direction: 'East', image: 'Thunder' },
  { id: 'Kan', name: 'Kan', chineseName: '坎', pinyin: 'Kǎn', symbol: '☵', element: 'Water', familyMember: 'Middle Son', direction: 'North', image: 'Water' },
  { id: 'Gen', name: 'Gen', chineseName: '艮', pinyin: 'Gèn', symbol: '☶', element: 'Earth', familyMember: 'Youngest Son', direction: 'Northeast', image: 'Mountain' },
  { id: 'Xun', name: 'Xun', chineseName: '巽', pinyin: 'Xùn', symbol: '☴', element: 'Wood', familyMember: 'Eldest Daughter', direction: 'Southeast', image: 'Wind' },
  { id: 'Li', name: 'Li', chineseName: '離', pinyin: 'Lí', symbol: '☲', element: 'Fire', familyMember: 'Middle Daughter', direction: 'South', image: 'Fire' },
  { id: 'Dui', name: 'Dui', chineseName: '兌', pinyin: 'Duì', symbol: '☱', element: 'Metal', familyMember: 'Youngest Daughter', direction: 'West', image: 'Lake' },
]

describe('TrigramExplorerPage', () => {
  let wrapper: VueWrapper | undefined

  beforeEach(() => {
    vi.mocked(fetchTrigrams).mockReset()
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
  })

  it('shows a loading skeleton before the fetch resolves', async () => {
    let resolve!: (value: Trigram[]) => void
    vi.mocked(fetchTrigrams).mockReturnValue(new Promise<Trigram[]>((r) => (resolve = r)))

    wrapper = mount(TrigramExplorerPage)
    await flushPromises()

    expect(wrapper.find('.p-skeleton').exists()).toBe(true)
    expect(wrapper.text()).toContain('Loading')

    resolve(trigrams)
  })

  it('renders a card per trigram with its symbol, names and attributes', async () => {
    vi.mocked(fetchTrigrams).mockResolvedValue(trigrams)

    wrapper = mount(TrigramExplorerPage)
    await flushPromises()

    const cards = wrapper.findAll('ul.grid > li')
    expect(cards).toHaveLength(8)

    const qian = cards[0]!
    expect(qian.text()).toContain('☰')
    expect(qian.text()).toContain('乾')
    expect(qian.text()).toContain('Qián')
    expect(qian.text()).toContain('Heaven')
    expect(qian.text()).toContain('Father')
  })

  it('places each trigram in the Later Heaven cell matching its direction, centre empty', async () => {
    vi.mocked(fetchTrigrams).mockResolvedValue(trigrams)

    wrapper = mount(TrigramExplorerPage)
    await flushPromises()

    const cells = wrapper.findAll('.arrangement-cell')
    expect(cells).toHaveLength(9)
    expect(cells[0]!.text()).toContain('乾')
    expect(cells[7]!.text()).toContain('離')
    expect(cells[4]!.text()).toBe('')
  })

  it('shows an inline error when the fetch fails', async () => {
    vi.mocked(fetchTrigrams).mockReturnValue(Promise.reject(new Error('trigrams down')))

    wrapper = mount(TrigramExplorerPage)
    await flushPromises()

    expect(wrapper.text()).toContain('trigrams down')
    expect(wrapper.findAll('ul.grid > li')).toHaveLength(0)
  })
})
