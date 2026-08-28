import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import JournalPage from './JournalPage.vue'
import { createJournalEntry, fetchJournalEntries } from '../../entities/journal/api'
import type { JournalEntry, JournalPage as JournalPageModel } from '../../entities/journal/model'

vi.mock('../../entities/journal/api', () => ({
  createJournalEntry: vi.fn(),
  fetchJournalEntries: vi.fn(),
}))

function sample(id: string, text: string, createdAt = '2026-08-14T10:00:00+00:00'): JournalEntry {
  return { id, text, createdAt }
}

function page(items: JournalEntry[], nextCursor: string | null = null): JournalPageModel {
  return { items, nextCursor }
}

describe('JournalPage', () => {
  beforeEach(() => {
    vi.mocked(createJournalEntry).mockReset()
    vi.mocked(fetchJournalEntries).mockReset()
  })

  it('shows an empty state when there are no entries', async () => {
    vi.mocked(fetchJournalEntries).mockResolvedValue(page([]))

    const wrapper = mount(JournalPage)
    await flushPromises()

    expect(wrapper.text()).toContain('No journal entries yet')
  })

  it('groups entries under a date heading per distinct local calendar day', async () => {
    vi.mocked(fetchJournalEntries).mockResolvedValue(
      page([
        sample('1', 'Same day, first', '2026-08-14T18:00:00+00:00'),
        sample('2', 'Same day, second', '2026-08-14T10:00:00+00:00'),
        sample('3', 'Different day', '2026-08-10T10:00:00+00:00'),
      ]),
    )

    const wrapper = mount(JournalPage)
    await flushPromises()

    expect(wrapper.findAll('h2')).toHaveLength(2)
  })

  it('adds an entry and clears the form on success, showing it immediately without a refetch', async () => {
    vi.mocked(fetchJournalEntries).mockResolvedValue(page([sample('1', 'Existing entry')]))
    vi.mocked(createJournalEntry).mockResolvedValue(sample('2', 'A new reflection.', '2026-08-21T10:00:00+00:00'))

    const wrapper = mount(JournalPage)
    await flushPromises()
    vi.mocked(fetchJournalEntries).mockClear()

    await wrapper.find('textarea').setValue('A new reflection.')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(createJournalEntry).toHaveBeenCalledWith({ text: 'A new reflection.' })
    expect(fetchJournalEntries).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('A new reflection.')
    expect(wrapper.text()).toContain('Existing entry')
    expect((wrapper.find('textarea').element as HTMLTextAreaElement).value).toBe('')
  })

  it('shows an inline error when adding an entry fails, without disturbing the rest of the page', async () => {
    vi.mocked(fetchJournalEntries).mockResolvedValue(page([sample('1', 'Existing entry')]))
    vi.mocked(createJournalEntry).mockRejectedValue(new Error('entry rejected'))

    const wrapper = mount(JournalPage)
    await flushPromises()

    await wrapper.find('textarea').setValue('Bad entry.')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('entry rejected')
    expect(wrapper.text()).toContain('Existing entry')
  })

  it('appends the next page when "Load more" is clicked, passing the cursor', async () => {
    vi.mocked(fetchJournalEntries).mockResolvedValueOnce(page([sample('1', 'Newest entry')], 'cursor-xyz'))

    const wrapper = mount(JournalPage)
    await flushPromises()

    vi.mocked(fetchJournalEntries).mockResolvedValueOnce(page([sample('2', 'Older entry')], null))
    const loadMore = wrapper.findAll('button').find((b) => b.text() === 'Load more')!
    await loadMore.trigger('click')
    await flushPromises()

    expect(fetchJournalEntries).toHaveBeenLastCalledWith(expect.objectContaining({ cursor: 'cursor-xyz' }))
    expect(wrapper.text()).toContain('Newest entry')
    expect(wrapper.text()).toContain('Older entry')
    expect(wrapper.findAll('button').some((b) => b.text() === 'Load more')).toBe(false)
  })

  it('shows an error message when the fetch fails', async () => {
    vi.mocked(fetchJournalEntries).mockRejectedValue(new Error('network down'))

    const wrapper = mount(JournalPage)
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
