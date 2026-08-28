import { apiGet, apiPost } from '../../shared/api/http'
import type { JournalEntry, JournalListParams, JournalPage, NewJournalEntryRequest } from './model'

export function createJournalEntry(request: NewJournalEntryRequest): Promise<JournalEntry> {
  return apiPost<JournalEntry>('/journal', request)
}

/** One page of entries, newest-first (SPEC-041). Pass `params.cursor` from a previous page's
 * `nextCursor` to page down. */
export function fetchJournalEntries(params: JournalListParams = {}): Promise<JournalPage> {
  const search = new URLSearchParams()
  if (params.limit !== undefined) search.set('limit', String(params.limit))
  if (params.cursor) search.set('cursor', params.cursor)

  const query = search.toString()
  return apiGet<JournalPage>(`/journal${query === '' ? '' : `?${query}`}`)
}
