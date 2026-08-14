import { describe, it, expect, vi, afterEach } from 'vitest'
import { apiGet, ApiError } from './http'

describe('apiGet', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('resolves parsed JSON on a successful response', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ hello: 'world' }),
      }),
    )

    const result = await apiGet<{ hello: string }>('/ping')

    expect(result).toEqual({ hello: 'world' })
  })

  it('throws an ApiError with the status and message on a non-2xx response', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 404,
        json: () => Promise.resolve({ error: 'Not Found' }),
      }),
    )

    await expect(apiGet('/missing')).rejects.toMatchObject({
      status: 404,
      message: 'Not Found',
    })
  })

  it('falls back to a generic message when the error body has no "error" field', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
        json: () => Promise.reject(new Error('not json')),
      }),
    )

    await expect(apiGet('/broken')).rejects.toBeInstanceOf(ApiError)
  })
})
