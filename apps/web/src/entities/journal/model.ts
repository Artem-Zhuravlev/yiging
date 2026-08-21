export interface JournalEntry {
  id: string
  text: string
  createdAt: string
}

export interface NewJournalEntryRequest {
  text: string
}
