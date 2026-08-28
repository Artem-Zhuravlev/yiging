export interface JournalEntry {
  id: string
  text: string
  createdAt: string
}

export interface NewJournalEntryRequest {
  text: string
}

/** One page of `GET /api/journal` (SPEC-041). `nextCursor` is non-null exactly when older
 * entries exist after this page; pass it back as `cursor` to page down. */
export interface JournalPage {
  items: JournalEntry[]
  nextCursor: string | null
}

export interface JournalListParams {
  limit?: number
  cursor?: string | null
}
