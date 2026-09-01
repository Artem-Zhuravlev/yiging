import { apiDelete, apiGet, apiPatch, apiPost, apiPutJson } from '../../shared/api/http'
import type {
  Consultation,
  ConsultationDetail,
  ConsultationListPage,
  ConsultationListParams,
  ConsultationPatch,
  DueReminder,
  NewConsultationRequest,
  ReflectionReminder,
  TagWithCount,
} from './model'

export function createConsultation(request: NewConsultationRequest): Promise<Consultation> {
  return apiPost<Consultation>('/consultations', request)
}

/** One page of history (SPEC-041). Server-side paginated + filtered — pass `params.cursor` from a
 * previous page's `nextCursor` to page down; `q`/`tags`/`favorite` filter the whole history. */
export function fetchConsultations(params: ConsultationListParams = {}): Promise<ConsultationListPage> {
  const search = new URLSearchParams()
  if (params.limit !== undefined) search.set('limit', String(params.limit))
  if (params.cursor) search.set('cursor', params.cursor)
  if (params.q && params.q.trim() !== '') search.set('q', params.q.trim())
  if (params.tags && params.tags.length > 0) search.set('tags', params.tags.join(','))
  if (params.favorite) search.set('favorite', '1')

  const query = search.toString()
  return apiGet<ConsultationListPage>(`/consultations${query === '' ? '' : `?${query}`}`)
}

/** Every distinct tag name used across the history (SPEC-041) — for the History page's filter
 * chips, which must show the full vocabulary, not just tags on the loaded page. */
export function fetchConsultationTags(): Promise<string[]> {
  return apiGet<string[]>('/consultations/tags')
}

/** Every used tag with its consultation count — for the "Manage tags" panel (SPEC-050). */
export function fetchTagsWithCounts(): Promise<TagWithCount[]> {
  return apiGet<TagWithCount[]>('/consultations/tags?counts=1')
}

/** Renames a tag; if `newName` already names a tag the two are merged (SPEC-050). */
export function renameTag(name: string, newName: string): Promise<{ renamed: boolean; merged: boolean }> {
  return apiPatch<{ renamed: boolean; merged: boolean }>(
    `/consultations/tags/${encodeURIComponent(name)}`,
    { newName },
  )
}

/** Removes a tag and every consultation's link to it (SPEC-050). */
export function deleteTag(name: string): Promise<void> {
  return apiDelete(`/consultations/tags/${encodeURIComponent(name)}`)
}

/** The full, fully-populated history — for the "Export Backup (JSON)" download only (SPEC-028/041).
 * The paginated list endpoint returns a lean projection unsuitable for a round-trippable backup. */
export function fetchConsultationsForExport(): Promise<Consultation[]> {
  return apiGet<Consultation[]>('/consultations/export')
}

export function fetchConsultation(id: string, lang = 'en'): Promise<ConsultationDetail> {
  const q = lang && lang !== 'en' ? `?lang=${lang}` : ''
  return apiGet<ConsultationDetail>(`/consultations/${id}${q}`)
}

export function updateConsultation(id: string, patch: ConsultationPatch): Promise<Consultation> {
  return apiPatch<Consultation>(`/consultations/${id}`, patch)
}

/** The due reflection reminders (SPEC-054) — consultations whose reminder date has passed and
 * which still have no recorded outcome, oldest first. For the Home dashboard. */
export function fetchDueReminders(): Promise<DueReminder[]> {
  return apiGet<DueReminder[]>('/consultations/reminders')
}

/** Sets (or replaces) the reflection reminder for a consultation (SPEC-054). `remindAt` may be
 * a `YYYY-MM-DD` date or a full ISO-8601 instant. */
export function setReflectionReminder(id: string, remindAt: string): Promise<ReflectionReminder> {
  return apiPutJson<ReflectionReminder>(`/consultations/${id}/reminder`, { remindAt })
}

/** Clears the reflection reminder for a consultation (SPEC-054). Not an error when none is set. */
export function clearReflectionReminder(id: string): Promise<void> {
  return apiDelete(`/consultations/${id}/reminder`)
}

/** Downloads the given consultations as a JSON file — pure client-side, no request (SPEC-028).
 * Takes data already in memory rather than re-fetching, since the caller already has it loaded. */
export function exportConsultationsBackup(consultations: Consultation[]): void {
  const blob = new Blob([JSON.stringify(consultations, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  const today = new Date().toISOString().slice(0, 10)
  link.href = url
  link.download = `yijing-backup-${today}.json`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export function importConsultationsBackup(items: unknown[]): Promise<{ imported: number }> {
  return apiPost<{ imported: number }>('/consultations/import', items)
}
