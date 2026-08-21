import { apiGet, apiPatch, apiPost } from '../../shared/api/http'
import type { Consultation, ConsultationDetail, ConsultationPatch, NewConsultationRequest } from './model'

export function createConsultation(request: NewConsultationRequest): Promise<Consultation> {
  return apiPost<Consultation>('/consultations', request)
}

export function fetchConsultations(): Promise<Consultation[]> {
  return apiGet<Consultation[]>('/consultations')
}

export function fetchConsultation(id: string): Promise<ConsultationDetail> {
  return apiGet<ConsultationDetail>(`/consultations/${id}`)
}

export function updateConsultation(id: string, patch: ConsultationPatch): Promise<Consultation> {
  return apiPatch<Consultation>(`/consultations/${id}`, patch)
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
