import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ConsultationHistoryPage from './ConsultationHistoryPage.vue'
import {
  exportConsultationsBackup,
  fetchConsultations,
  fetchConsultationTags,
  fetchConsultationsForExport,
  importConsultationsBackup,
} from '../../entities/consultation/api'
import type { ConsultationListItem, ConsultationListPage } from '../../entities/consultation/model'

vi.mock('../../entities/consultation/api', () => ({
  fetchConsultations: vi.fn(),
  fetchConsultationTags: vi.fn(),
  fetchConsultationsForExport: vi.fn(),
  exportConsultationsBackup: vi.fn(),
  importConsultationsBackup: vi.fn(),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

function item(id: string, question: string, overrides: Partial<ConsultationListItem> = {}): ConsultationListItem {
  return {
    id,
    question,
    method: 'three_coins',
    primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    changingLinePositions: [],
    resultingHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    createdAt: '2026-08-14T10:00:00+00:00',
    tags: [],
    favorite: false,
    ...overrides,
  }
}

function page(items: ConsultationListItem[], nextCursor: string | null = null): ConsultationListPage {
  return { items, nextCursor }
}

const wait = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms))

beforeEach(() => {
  vi.mocked(fetchConsultations).mockReset()
  vi.mocked(fetchConsultationTags).mockReset().mockResolvedValue([])
  vi.mocked(fetchConsultationsForExport).mockReset()
  vi.mocked(exportConsultationsBackup).mockClear()
  vi.mocked(importConsultationsBackup).mockReset()
})

describe('ConsultationHistoryPage', () => {
  it('renders the page the server returns, linking each to its detail page', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'First?'), item('2', 'Second?')]))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.findAll('a')).toHaveLength(2)
    expect(wrapper.text()).toContain('First?')
    expect(wrapper.text()).toContain('Second?')
  })

  it('shows the "cast your first one" empty state when the history is empty and no filter is active', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([]))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('No consultations yet')
  })

  it('shows an error message when the initial fetch fails', async () => {
    vi.mocked(fetchConsultations).mockRejectedValue(new Error('network down'))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })

  it('groups consultations under a heading per distinct local calendar day', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(
      page([
        item('1', 'Same day, first', { createdAt: '2026-08-14T18:00:00+00:00' }),
        item('2', 'Same day, second', { createdAt: '2026-08-14T10:00:00+00:00' }),
        item('3', 'Different day', { createdAt: '2026-08-10T10:00:00+00:00' }),
      ]),
    )

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.findAll('h2')).toHaveLength(2)
  })

  it('renders a tag chip per name returned by the tags endpoint', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'A')]))
    vi.mocked(fetchConsultationTags).mockResolvedValue(['career', 'health'])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const chips = wrapper.findAll('button[aria-pressed]').filter((c) => !c.text().includes('Favorites'))
    expect(chips.map((c) => c.text())).toEqual(['career', 'health'])
  })

  it('refetches page 1 with the selected tags when a tag chip is toggled', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'A', { tags: ['career'] })]))
    vi.mocked(fetchConsultationTags).mockResolvedValue(['career', 'health'])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()
    vi.mocked(fetchConsultations).mockClear()

    const careerChip = wrapper.findAll('button[aria-pressed]').find((b) => b.text() === 'career')!
    await careerChip.trigger('click')
    await flushPromises()

    expect(fetchConsultations).toHaveBeenCalledWith(
      expect.objectContaining({ tags: ['career'], cursor: null }),
    )
  })

  it('refetches page 1 with favorite=true when the favorites toggle is turned on', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'A', { favorite: true })]))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()
    vi.mocked(fetchConsultations).mockClear()

    const favoritesToggle = wrapper.findAll('button').find((b) => b.text().includes('Favorites only'))!
    await favoritesToggle.trigger('click')
    await flushPromises()

    expect(fetchConsultations).toHaveBeenCalledWith(expect.objectContaining({ favorite: true }))
  })

  it('refetches page 1 with the debounced search query', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'Should I take the new offer?')]))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()
    vi.mocked(fetchConsultations).mockClear()

    await wrapper.find('input[type="search"]').setValue('offer')
    await wait(350)
    await flushPromises()

    expect(fetchConsultations).toHaveBeenCalledWith(expect.objectContaining({ q: 'offer' }))
  })

  it('shows the "nothing matches" empty state when a filter is active and the page is empty', async () => {
    vi.mocked(fetchConsultations).mockResolvedValueOnce(page([item('1', 'A')]))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    vi.mocked(fetchConsultations).mockResolvedValueOnce(page([]))
    await wrapper.find('input[type="search"]').setValue('zzz-nope')
    await wait(350)
    await flushPromises()

    expect(wrapper.text()).toContain('No consultations match the selected tags.')
    expect(wrapper.text()).not.toContain('No consultations yet')
  })

  it('appends the next page when "Load more" is clicked, passing the cursor', async () => {
    vi.mocked(fetchConsultations).mockResolvedValueOnce(page([item('1', 'Newest?')], 'cursor-abc'))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    vi.mocked(fetchConsultations).mockResolvedValueOnce(page([item('2', 'Older?')], null))
    const loadMore = wrapper.findAll('button').find((b) => b.text() === 'Load more')!
    await loadMore.trigger('click')
    await flushPromises()

    expect(fetchConsultations).toHaveBeenLastCalledWith(expect.objectContaining({ cursor: 'cursor-abc' }))
    expect(wrapper.text()).toContain('Newest?')
    expect(wrapper.text()).toContain('Older?')
    expect(wrapper.findAll('button').some((b) => b.text() === 'Load more')).toBe(false)
  })

  async function selectFile(wrapper: ReturnType<typeof mount>, contents: string): Promise<void> {
    const input = wrapper.find('input[type="file"]').element as HTMLInputElement
    const file = new File([contents], 'backup.json', { type: 'application/json' })
    Object.defineProperty(input, 'files', { value: [file], configurable: true })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()
  }

  it('exports the full history via the dedicated export endpoint', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'A?')]))
    const fullBackup = [{ id: '1', question: 'A?' }] as never
    vi.mocked(fetchConsultationsForExport).mockResolvedValue(fullBackup)

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const button = wrapper.findAll('button').find((b) => b.text() === 'Export Backup (JSON)')!
    await button.trigger('click')
    await flushPromises()

    expect(fetchConsultationsForExport).toHaveBeenCalled()
    expect(exportConsultationsBackup).toHaveBeenCalledWith(fullBackup)
  })

  it('imports a valid backup file, shows a success message, and refreshes page 1', async () => {
    vi.mocked(fetchConsultations).mockResolvedValueOnce(page([item('1', 'Original?')]))
    vi.mocked(importConsultationsBackup).mockResolvedValue({ imported: 2 })

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    vi.mocked(fetchConsultations).mockResolvedValueOnce(page([item('1', 'Original?'), item('2', 'Restored?')]))
    await selectFile(wrapper, '[{"id":"2","question":"Restored?"}]')

    expect(importConsultationsBackup).toHaveBeenCalledWith([{ id: '2', question: 'Restored?' }])
    expect(wrapper.text()).toContain('Imported 2 consultations.')
    expect(wrapper.text()).toContain('Restored?')
  })

  it('shows an error and never calls the API when the selected file is not valid JSON', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'A?')]))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    await selectFile(wrapper, 'not json at all')

    expect(importConsultationsBackup).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('not valid JSON')
  })

  it('shows an error and never calls the API when the file is valid JSON but not an array', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'A?')]))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    await selectFile(wrapper, '{"not": "an array"}')

    expect(importConsultationsBackup).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('does not contain a backup array')
  })

  it('shows an inline error when the import API call fails', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue(page([item('1', 'A?')]))
    vi.mocked(importConsultationsBackup).mockRejectedValue(new Error('duplicate ids'))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    await selectFile(wrapper, '[]')

    expect(wrapper.text()).toContain('duplicate ids')
  })
})
