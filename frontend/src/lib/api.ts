import type {
  ApiEnvelope,
  AuditDashboard,
  AuditFilters,
  AuthBundle,
  AuthProfile,
  Credentials,
  IpAddressInput,
  IpAddressRecord,
  IpAddressUpdateInput,
  StoredAuth,
} from '../types'

const API_BASE_URL =
  (import.meta.env.VITE_API_BASE_URL as string | undefined) ??
  'http://localhost:8000/api'

const STORAGE_KEY = 'ipam.auth.session'

let cachedAuth = readStoredAuth()
let refreshPromise: Promise<StoredAuth | null> | null = null

export class ApiError extends Error {
  readonly status: number

  readonly payload?: unknown

  constructor(
    message: string,
    status: number,
    payload?: unknown,
  ) {
    super(message)
    this.status = status
    this.payload = payload
  }
}

type RequestOptions = Omit<RequestInit, 'body'> & {
  auth?: boolean
  retry?: boolean
  body?: unknown
}

function readStoredAuth(): StoredAuth | null {
  const raw = window.localStorage.getItem(STORAGE_KEY)

  if (!raw) {
    return null
  }

  try {
    return JSON.parse(raw) as StoredAuth
  } catch {
    window.localStorage.removeItem(STORAGE_KEY)
    return null
  }
}

function persistAuth(next: StoredAuth | null) {
  cachedAuth = next

  if (!next) {
    window.localStorage.removeItem(STORAGE_KEY)
    return
  }

  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
}

function mapAuthBundle(bundle: AuthBundle): StoredAuth {
  return {
    accessToken: bundle.access_token,
    refreshToken: bundle.refresh_token,
    expiresAt: bundle.expires_at,
    refreshTokenExpiresAt: bundle.refresh_token_expires_at,
    sessionId: bundle.session_id,
    user: bundle.user,
  }
}

async function parseResponse(response: Response): Promise<unknown> {
  if (response.status === 204) {
    return null
  }

  const text = await response.text()

  if (!text) {
    return null
  }

  try {
    return JSON.parse(text) as unknown
  } catch {
    return text
  }
}

function extractMessage(payload: unknown, fallback: string): string {
  if (typeof payload === 'string' && payload.trim().length > 0) {
    return payload
  }

  if (
    payload &&
    typeof payload === 'object' &&
    'message' in payload &&
    typeof payload.message === 'string'
  ) {
    return payload.message
  }

  return fallback
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const headers = new Headers(options.headers)
  const body = toRequestBody(options.body)

  headers.set('Accept', 'application/json')

  if (body && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }

  if (options.auth && cachedAuth?.accessToken) {
    headers.set('Authorization', `Bearer ${cachedAuth.accessToken}`)
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    body,
    headers,
  })

  if (response.status === 401 && options.auth && !options.retry && cachedAuth?.refreshToken) {
    const refreshed = await refreshSession()

    if (refreshed) {
      return request<T>(path, { ...options, retry: true })
    }
  }

  const payload = await parseResponse(response)

  if (!response.ok) {
    throw new ApiError(
      extractMessage(payload, 'The request could not be completed.'),
      response.status,
      payload,
    )
  }

  return payload as T
}

function toRequestBody(value: unknown): BodyInit | null | undefined {
  if (value === undefined || value === null) {
    return value
  }

  if (
    value instanceof FormData ||
    value instanceof URLSearchParams ||
    value instanceof Blob ||
    value instanceof ArrayBuffer
  ) {
    return value
  }

  if (typeof value === 'string') {
    return value
  }

  return JSON.stringify(value)
}

async function refreshSession(): Promise<StoredAuth | null> {
  if (!cachedAuth?.refreshToken) {
    return null
  }

  if (!refreshPromise) {
    refreshPromise = (async () => {
      try {
        const response = await request<ApiEnvelope<AuthBundle>>('/auth/refresh', {
          method: 'POST',
          body: {
            refresh_token: cachedAuth?.refreshToken,
          },
        })

        const next = mapAuthBundle(response.data)
        persistAuth(next)
        return next
      } catch {
        persistAuth(null)
        return null
      } finally {
        refreshPromise = null
      }
    })()
  }

  return refreshPromise
}

export function getStoredSession(): StoredAuth | null {
  return cachedAuth
}

export function clearStoredSession() {
  persistAuth(null)
}

export const authApi = {
  hydrate(): StoredAuth | null {
    cachedAuth = readStoredAuth()
    return cachedAuth
  },

  async login(credentials: Credentials): Promise<AuthBundle> {
    const response = await request<ApiEnvelope<AuthBundle>>('/auth/login', {
      method: 'POST',
      body: credentials,
    })

    persistAuth(mapAuthBundle(response.data))
    return response.data
  },

  async me(): Promise<AuthProfile> {
    const response = await request<ApiEnvelope<AuthProfile>>('/auth/me', {
      auth: true,
    })

    if (cachedAuth) {
      persistAuth({
        ...cachedAuth,
        user: response.data.user,
        sessionId: response.data.session.id,
        expiresAt: response.data.session.expires_at ?? cachedAuth.expiresAt,
      })
    }

    return response.data
  },

  async logout(): Promise<void> {
    try {
      await request('/auth/logout', {
        method: 'POST',
        auth: true,
      })
    } finally {
      persistAuth(null)
    }
  },
}

export const ipApi = {
  async list(): Promise<IpAddressRecord[]> {
    const response = await request<ApiEnvelope<IpAddressRecord[]>>('/ip-addresses', {
      auth: true,
    })

    return response.data
  },

  async create(payload: IpAddressInput): Promise<IpAddressRecord> {
    const response = await request<ApiEnvelope<IpAddressRecord>>('/ip-addresses', {
      method: 'POST',
      auth: true,
      body: payload,
    })

    return response.data
  },

  async update(id: string, payload: IpAddressUpdateInput): Promise<IpAddressRecord> {
    const response = await request<ApiEnvelope<IpAddressRecord>>(`/ip-addresses/${id}`, {
      method: 'PATCH',
      auth: true,
      body: payload,
    })

    return response.data
  },

  async remove(id: string): Promise<void> {
    await request(`/ip-addresses/${id}`, {
      method: 'DELETE',
      auth: true,
    })
  },
}

export const auditApi = {
  async dashboard(filters: AuditFilters): Promise<AuditDashboard> {
    const params = new URLSearchParams()

    Object.entries(filters).forEach(([key, value]) => {
      if (value.trim()) {
        params.set(key, value.trim())
      }
    })

    const queryString = params.toString()
    const suffix = queryString ? `?${queryString}` : ''
    const response = await request<ApiEnvelope<AuditDashboard>>(
      `/audit/dashboard${suffix}`,
      {
        auth: true,
      },
    )

    return response.data
  },
}
