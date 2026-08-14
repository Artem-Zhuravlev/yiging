import { describe, it, expect, vi, afterEach } from 'vitest'
import { createConsultation, fetchConsultations, fetchConsultation } from './api'
import type { Consultation } from './model'

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
      json: () => Promise.resolve(sample),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchConsultation('abc-123')

    expect(result).toEqual(sample)
    expect(fetchMock).toHaveBeenCalledWith('/api/consultations/abc-123')
  })
})
