import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ConsultationHistoryPage from './ConsultationHistoryPage.vue'
import {
  exportConsultationsBackup,
  fetchConsultations,
  importConsultationsBackup,
} from '../../entities/consultation/api'
import type { Consultation } from '../../entities/consultation/model'

vi.mock('../../entities/consultation/api', () => ({
  fetchConsultations: vi.fn(),
  exportConsultationsBackup: vi.fn(),
  importConsultationsBackup: vi.fn(),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

function sample(id: string, question: string, overrides: Partial<Consultation> = {}): Consultation {
  return {
    id,
    question,
    method: 'three_coins',
    primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    changingLinePositions: [],
    resultingHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    createdAt: '2026-08-14T10:00:00+00:00',
    notes: [],
    tags: [],
    context: null,
    whatHappenedBefore: null,
    whatUserWantsToUnderstand: null,
    backgroundInformation: null,
    initialInterpretation: null,
    outcome: null,
    followUpTo: null,
    followUps: [],
    favorite: false,
    ...overrides,
  }
}

describe('ConsultationHistoryPage', () => {
  beforeEach(() => {
    vi.mocked(exportConsultationsBackup).mockClear()
    vi.mocked(importConsultationsBackup).mockClear()
  })

  it('renders every consultation, linking to its detail page', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'First?'),
      sample('2', 'Second?'),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.findAll('a')).toHaveLength(2)
    expect(wrapper.text()).toContain('First?')
    expect(wrapper.text()).toContain('Second?')
  })

  it('shows an empty state when there are no consultations', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('No consultations yet')
  })

  it('shows an error message when the fetch fails', async () => {
    vi.mocked(fetchConsultations).mockRejectedValue(new Error('network down'))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })

  it('groups consultations under a heading per distinct local calendar day', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'Same day, first', { createdAt: '2026-08-14T18:00:00+00:00' }),
      sample('2', 'Same day, second', { createdAt: '2026-08-14T10:00:00+00:00' }),
      sample('3', 'Different day', { createdAt: '2026-08-10T10:00:00+00:00' }),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const headings = wrapper.findAll('h2')
    expect(headings).toHaveLength(2)
    expect(wrapper.text()).toContain('Same day, first')
    expect(wrapper.text()).toContain('Same day, second')
    expect(wrapper.text()).toContain('Different day')
  })

  it('renders a tag chip per distinct tag, and none when nothing is tagged', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'A', { tags: ['career', 'health'] }),
      sample('2', 'B', { tags: ['career'] }),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const chips = wrapper.findAll('button[aria-pressed]').filter((c) => !c.text().includes('Favorites'))
    expect(chips.map((c) => c.text())).toEqual(['career', 'health'])
  })

  it('narrows the list to consultations having every selected tag (AND)', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'Both tags', { tags: ['career', 'health'] }),
      sample('2', 'Only career', { tags: ['career'] }),
      sample('3', 'Only health', { tags: ['health'] }),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const tagChips = wrapper.findAll('button[aria-pressed]').filter((c) => !c.text().includes('Favorites'))
    const [careerChip, healthChip] = tagChips
    await careerChip!.trigger('click')
    expect(wrapper.text()).toContain('Both tags')
    expect(wrapper.text()).toContain('Only career')
    expect(wrapper.text()).not.toContain('Only health')

    await healthChip!.trigger('click')
    expect(wrapper.text()).toContain('Both tags')
    expect(wrapper.text()).not.toContain('Only career')
    expect(wrapper.text()).not.toContain('Only health')

    await careerChip!.trigger('click')
    await healthChip!.trigger('click')
    expect(wrapper.text()).toContain('Only career')
    expect(wrapper.text()).toContain('Only health')
  })

  it('narrows the list to favorite consultations only, combining with an active tag filter', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'Favorite, tagged', { favorite: true, tags: ['career'] }),
      sample('2', 'Favorite, untagged', { favorite: true }),
      sample('3', 'Not favorite, tagged', { favorite: false, tags: ['career'] }),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const favoritesToggle = wrapper.findAll('button').find((b) => b.text().includes('Favorites only'))!
    await favoritesToggle.trigger('click')

    expect(wrapper.text()).toContain('Favorite, tagged')
    expect(wrapper.text()).toContain('Favorite, untagged')
    expect(wrapper.text()).not.toContain('Not favorite, tagged')

    const careerChip = wrapper.findAll('button[aria-pressed]').find((b) => b.text() === 'career')!
    await careerChip.trigger('click')

    expect(wrapper.text()).toContain('Favorite, tagged')
    expect(wrapper.text()).not.toContain('Favorite, untagged')
    expect(wrapper.text()).not.toContain('Not favorite, tagged')
  })

  it('shows the "Favorites only" toggle even when nothing is tagged', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([sample('1', 'A')])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.findAll('button').some((b) => b.text().includes('Favorites only'))).toBe(true)
  })

  it('filters by question text, case-insensitively', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'Should I take the new offer?'),
      sample('2', 'Is this relationship healthy?'),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    await wrapper.find('input[type="search"]').setValue('OFFER')

    expect(wrapper.text()).toContain('Should I take the new offer?')
    expect(wrapper.text()).not.toContain('Is this relationship healthy?')
  })

  it('filters by note text', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'First question?', {
        notes: [{ label: 'after', text: 'It went great in the end.', createdAt: '2026-08-14T09:00:00+00:00' }],
      }),
      sample('2', 'Second question?'),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    await wrapper.find('input[type="search"]').setValue('went great')

    expect(wrapper.text()).toContain('First question?')
    expect(wrapper.text()).not.toContain('Second question?')
  })

  it('composes search with an active tag filter and favorites-only', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'Offer from company A?', { favorite: true, tags: ['career'] }),
      sample('2', 'Offer from company B?', { favorite: false, tags: ['career'] }),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const favoritesToggle = wrapper.findAll('button').find((b) => b.text().includes('Favorites only'))!
    await favoritesToggle.trigger('click')
    await wrapper.find('input[type="search"]').setValue('offer')

    expect(wrapper.text()).toContain('Offer from company A?')
    expect(wrapper.text()).not.toContain('Offer from company B?')
  })

  it('restores the full list when the search is cleared', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([sample('1', 'A?'), sample('2', 'B?')])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const input = wrapper.find('input[type="search"]')
    await input.setValue('nonexistent')
    expect(wrapper.text()).toContain('No consultations match the selected tags.')

    await input.setValue('')
    expect(wrapper.text()).toContain('A?')
    expect(wrapper.text()).toContain('B?')
  })

  it('shows a distinct message when the tag filter matches nothing', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'Only career', { tags: ['career'] }),
      sample('2', 'Only health', { tags: ['health'] }),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const tagChips = wrapper.findAll('button[aria-pressed]').filter((c) => !c.text().includes('Favorites'))
    const [careerChip, healthChip] = tagChips
    await careerChip!.trigger('click')
    await healthChip!.trigger('click')

    expect(wrapper.text()).toContain('No consultations match the selected tags.')
    expect(wrapper.text()).not.toContain('Only career')
    expect(wrapper.text()).not.toContain('No consultations yet')
  })

  async function selectFile(wrapper: ReturnType<typeof mount>, contents: string): Promise<void> {
    const input = wrapper.find('input[type="file"]').element as HTMLInputElement
    const file = new File([contents], 'backup.json', { type: 'application/json' })
    Object.defineProperty(input, 'files', { value: [file], configurable: true })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()
  }

  it('exports the currently loaded consultations', async () => {
    const consultations = [sample('1', 'A?'), sample('2', 'B?')]
    vi.mocked(fetchConsultations).mockResolvedValue(consultations)

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    const button = wrapper.findAll('button').find((b) => b.text() === 'Export Backup (JSON)')!
    await button.trigger('click')

    expect(exportConsultationsBackup).toHaveBeenCalledWith(consultations)
  })

  it('imports a valid backup file, shows a success message, and refreshes the list', async () => {
    vi.mocked(fetchConsultations).mockResolvedValueOnce([sample('1', 'Original?')])
    vi.mocked(importConsultationsBackup).mockResolvedValue({ imported: 2 })

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    vi.mocked(fetchConsultations).mockResolvedValueOnce([
      sample('1', 'Original?'),
      sample('2', 'Restored?'),
    ])
    await selectFile(wrapper, '[{"id":"2","question":"Restored?"}]')

    expect(importConsultationsBackup).toHaveBeenCalledWith([{ id: '2', question: 'Restored?' }])
    expect(wrapper.text()).toContain('Imported 2 consultations.')
    expect(wrapper.text()).toContain('Restored?')
  })

  it('shows an error and never calls the API when the selected file is not valid JSON', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([sample('1', 'A?')])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    await selectFile(wrapper, 'not json at all')

    expect(importConsultationsBackup).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('not valid JSON')
  })

  it('shows an error and never calls the API when the file is valid JSON but not an array', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([sample('1', 'A?')])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    await selectFile(wrapper, '{"not": "an array"}')

    expect(importConsultationsBackup).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('does not contain a backup array')
  })

  it('shows an inline error when the import API call fails', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([sample('1', 'A?')])
    vi.mocked(importConsultationsBackup).mockRejectedValue(new Error('duplicate ids'))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    await selectFile(wrapper, '[]')

    expect(wrapper.text()).toContain('duplicate ids')
  })
})
