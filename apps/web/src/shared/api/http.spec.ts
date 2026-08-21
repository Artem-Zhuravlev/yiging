import { describe, it, expect, vi, afterEach } from 'vitest'
import { apiGet, apiPost, apiPatch, apiPut, apiDelete, ApiError } from './http'

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

describe('apiPost', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends a JSON body and resolves parsed JSON on a successful response', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ id: '1' }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await apiPost<{ id: string }>('/things', { name: 'test' })

    expect(result).toEqual({ id: '1' })
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/things',
      expect.objectContaining({
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: 'test' }),
      }),
    )
  })

  it('throws an ApiError with the status and message on a non-2xx response', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        json: () => Promise.resolve({ error: 'Invalid input.' }),
      }),
    )

    await expect(apiPost('/things', {})).rejects.toMatchObject({
      status: 422,
      message: 'Invalid input.',
    })
  })
})

describe('apiPatch', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends a JSON body via PATCH and resolves parsed JSON on a successful response', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ id: '1', tags: ['career'] }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await apiPatch<{ id: string }>('/things/1', { tag: 'career' })

    expect(result).toEqual({ id: '1', tags: ['career'] })
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/things/1',
      expect.objectContaining({
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tag: 'career' }),
      }),
    )
  })

  it('throws an ApiError with the status and message on a non-2xx response', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        json: () => Promise.resolve({ error: 'Invalid input.' }),
      }),
    )

    await expect(apiPatch('/things/1', {})).rejects.toMatchObject({
      status: 422,
      message: 'Invalid input.',
    })
  })
})

describe('apiPut', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends a PUT request and resolves without attempting to parse a body', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true })
    vi.stubGlobal('fetch', fetchMock)

    await expect(apiPut('/things/1/favorite')).resolves.toBeUndefined()
    expect(fetchMock).toHaveBeenCalledWith('/api/things/1/favorite', { method: 'PUT' })
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

    await expect(apiPut('/things/1/favorite')).rejects.toMatchObject({
      status: 404,
      message: 'Not Found',
    })
  })
})

describe('apiDelete', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends a DELETE request and resolves without attempting to parse a body', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true })
    vi.stubGlobal('fetch', fetchMock)

    await expect(apiDelete('/things/1/favorite')).resolves.toBeUndefined()
    expect(fetchMock).toHaveBeenCalledWith('/api/things/1/favorite', { method: 'DELETE' })
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

    await expect(apiDelete('/things/1/favorite')).rejects.toMatchObject({
      status: 404,
      message: 'Not Found',
    })
  })
})
