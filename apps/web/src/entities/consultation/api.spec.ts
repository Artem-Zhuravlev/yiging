import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  createConsultation,
  fetchConsultations,
  fetchConsultationTags,
  fetchConsultationsForExport,
  fetchConsultation,
  updateConsultation,
  importConsultationsBackup,
  fetchDueReminders,
  setReflectionReminder,
  clearReflectionReminder,
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
  readingGuidance: { changingLineCount: 0, rule: 'no-changing-lines', refs: [], specialText: null },
  reminder: null,
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

  it('fetchConsultations gets a page from /consultations with no query string by default', async () => {
    const pageBody = { items: [], nextCursor: null }
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(pageBody) })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchConsultations()

    expect(result).toEqual(pageBody)
    expect(fetchMock).toHaveBeenCalledWith('/api/consultations')
  })

  it('fetchConsultations builds a query string from cursor / q / tags / favorite', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ items: [], nextCursor: null }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await fetchConsultations({ cursor: 'c1', q: ' offer ', tags: ['career', 'money'], favorite: true })

    const url = fetchMock.mock.calls[0]![0] as string
    expect(url.startsWith('/api/consultations?')).toBe(true)
    const params = new URLSearchParams(url.slice(url.indexOf('?') + 1))
    expect(params.get('cursor')).toBe('c1')
    expect(params.get('q')).toBe('offer')
    expect(params.get('tags')).toBe('career,money')
    expect(params.get('favorite')).toBe('1')
  })

  it('fetchConsultationTags gets /consultations/tags', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(['career', 'money']) })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchConsultationTags()

    expect(result).toEqual(['career', 'money'])
    expect(fetchMock).toHaveBeenCalledWith('/api/consultations/tags')
  })

  it('fetchConsultationsForExport gets /consultations/export', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([sample]) })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchConsultationsForExport()

    expect(result).toEqual([sample])
    expect(fetchMock).toHaveBeenCalledWith('/api/consultations/export')
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

  it('fetchDueReminders gets /consultations/reminders', async () => {
    const due = [
      {
        id: 'abc-123',
        question: 'Should I take the offer?',
        primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
        resultingHexagram: { kingWenNumber: 2, chineseName: '坤', pinyin: 'Kūn' },
        remindAt: '2026-08-01T00:00:00+00:00',
        createdAt: '2026-07-01T00:00:00+00:00',
      },
    ]
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(due) })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchDueReminders()

    expect(result).toEqual(due)
    expect(fetchMock).toHaveBeenCalledWith('/api/consultations/reminders')
  })

  it('setReflectionReminder PUTs the date to /consultations/{id}/reminder', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ remindAt: '2026-09-15T00:00:00+00:00' }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await setReflectionReminder('abc-123', '2026-09-15')

    expect(result).toEqual({ remindAt: '2026-09-15T00:00:00+00:00' })
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/consultations/abc-123/reminder',
      expect.objectContaining({
        method: 'PUT',
        body: JSON.stringify({ remindAt: '2026-09-15' }),
      }),
    )
  })

  it('clearReflectionReminder DELETEs /consultations/{id}/reminder', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true })
    vi.stubGlobal('fetch', fetchMock)

    await clearReflectionReminder('abc-123')

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/consultations/abc-123/reminder',
      expect.objectContaining({ method: 'DELETE' }),
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
