import { apiGet, apiPost } from '../../shared/api/http'
import type { Consultation, NewConsultationRequest } from './model'

export function createConsultation(request: NewConsultationRequest): Promise<Consultation> {
  return apiPost<Consultation>('/consultations', request)
}

export function fetchConsultations(): Promise<Consultation[]> {
  return apiGet<Consultation[]>('/consultations')
}

export function fetchConsultation(id: string): Promise<Consultation> {
  return apiGet<Consultation>(`/consultations/${id}`)
}
