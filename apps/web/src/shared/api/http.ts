const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api'

export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

interface ErrorBody {
  error?: string
}

async function handleResponse<T>(path: string, response: Response): Promise<T> {
  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as ErrorBody | null
    throw new ApiError(
      response.status,
      body?.error ?? `Request to ${path} failed with status ${response.status}`,
    )
  }

  return (await response.json()) as T
}

export async function apiGet<T>(path: string): Promise<T> {
  const response = await fetch(`${BASE_URL}${path}`)

  return handleResponse<T>(path, response)
}

export async function apiPost<T>(path: string, body: unknown): Promise<T> {
  const response = await fetch(`${BASE_URL}${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })

  return handleResponse<T>(path, response)
}

export async function apiPatch<T>(path: string, body: unknown): Promise<T> {
  const response = await fetch(`${BASE_URL}${path}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })

  return handleResponse<T>(path, response)
}

/** For endpoints that respond 204 No Content on success — unlike apiGet/apiPost/apiPatch,
 * this never attempts to parse a response body (there isn't one). */
async function handleNoContentResponse(path: string, response: Response): Promise<void> {
  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as ErrorBody | null
    throw new ApiError(
      response.status,
      body?.error ?? `Request to ${path} failed with status ${response.status}`,
    )
  }
}

export async function apiPut(path: string): Promise<void> {
  const response = await fetch(`${BASE_URL}${path}`, { method: 'PUT' })

  return handleNoContentResponse(path, response)
}

/** PUT with a JSON body and a JSON response — for idempotent upserts that return the stored
 * resource (unlike apiPut, which is the bodyless 204 variant). */
export async function apiPutJson<T>(path: string, body: unknown): Promise<T> {
  const response = await fetch(`${BASE_URL}${path}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })

  return handleResponse<T>(path, response)
}

export async function apiDelete(path: string): Promise<void> {
  const response = await fetch(`${BASE_URL}${path}`, { method: 'DELETE' })

  return handleNoContentResponse(path, response)
}
