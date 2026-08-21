import { apiGet, apiPost } from '../../shared/api/http'
import type { JournalEntry, NewJournalEntryRequest } from './model'

export function createJournalEntry(request: NewJournalEntryRequest): Promise<JournalEntry> {
  return apiPost<JournalEntry>('/journal', request)
}

export function fetchJournalEntries(): Promise<JournalEntry[]> {
  return apiGet<JournalEntry[]>('/journal')
}
