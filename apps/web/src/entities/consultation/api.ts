import { apiGet, apiPatch, apiPost } from '../../shared/api/http'
import type { Consultation, ConsultationPatch, NewConsultationRequest } from './model'

export function createConsultation(request: NewConsultationRequest): Promise<Consultation> {
  return apiPost<Consultation>('/consultations', request)
}

export function fetchConsultations(): Promise<Consultation[]> {
  return apiGet<Consultation[]>('/consultations')
}

export function fetchConsultation(id: string): Promise<Consultation> {
  return apiGet<Consultation>(`/consultations/${id}`)
}

export function updateConsultation(id: string, patch: ConsultationPatch): Promise<Consultation> {
  return apiPatch<Consultation>(`/consultations/${id}`, patch)
}
