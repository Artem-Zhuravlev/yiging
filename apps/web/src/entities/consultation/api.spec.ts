import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  createConsultation,
  fetchConsultations,
  fetchConsultation,
  updateConsultation,
  importConsultationsBackup,
} from './api'
import type { Consultation, ConsultationDetail } from './model'

const sample: Consultation = {
  id: 'abc-123',
  question: 'Should I take the offer?',
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
}

const sampleDetail: ConsultationDetail = {
  ...sample,
  repeats: { primaryHexagram: [], resultingHexagram: [], changingLines: [] },
}

describe('entities/consultation api', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('createConsultation posts to /consultations and resolves the created consultation', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve(sample),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await createConsultation({ question: sample.question, method: 'three_coins' })

    expect(result).toEqual(sample)
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/consultations',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('fetchConsultations gets /consultations', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve([sample]),
      }),
    )

    const result = await fetchConsultations()

    expect(result).toEqual([sample])
  })

  it('fetchConsultation gets /consultations/{id}', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve(sampleDetail),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchConsultation('abc-123')

    expect(result).toEqual(sampleDetail)
    expect(fetchMock).toHaveBeenCalledWith('/api/consultations/abc-123')
  })

  it('updateConsultation patches /consultations/{id} and resolves the updated consultation', async () => {
    const updated = { ...sample, tags: ['career'] }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve(updated),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await updateConsultation('abc-123', { tag: 'career' })

    expect(result).toEqual(updated)
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/consultations/abc-123',
      expect.objectContaining({ method: 'PATCH', body: JSON.stringify({ tag: 'career' }) }),
    )
  })

  it('importConsultationsBackup posts to /consultations/import and resolves the import count', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ imported: 2 }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await importConsultationsBackup([sample])

    expect(result).toEqual({ imported: 2 })
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/consultations/import',
      expect.objectContaining({ method: 'POST', body: JSON.stringify([sample]) }),
    )
  })
})
