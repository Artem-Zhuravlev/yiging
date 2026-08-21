import { describe, it, expect, vi, afterEach } from 'vitest'
import { fetchInterpretationProfile, updateInterpretationProfile } from './api'
import type { InterpretationProfile } from './model'

const sample: InterpretationProfile = { tone: 'neutral', length: 'standard', notes: null }

describe('entities/interpretation-profile api', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('fetchInterpretationProfile gets /interpretation-profile', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(sample) })
    vi.stubGlobal('fetch', fetchMock)

    const result = await fetchInterpretationProfile()

    expect(result).toEqual(sample)
    expect(fetchMock).toHaveBeenCalledWith('/api/interpretation-profile')
  })

  it('updateInterpretationProfile patches /interpretation-profile with the given subset', async () => {
    const updated = { ...sample, tone: 'poetic' as const }
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(updated) })
    vi.stubGlobal('fetch', fetchMock)

    const result = await updateInterpretationProfile({ tone: 'poetic' })

    expect(result).toEqual(updated)
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/interpretation-profile',
      expect.objectContaining({ method: 'PATCH', body: JSON.stringify({ tone: 'poetic' }) }),
    )
  })
})
