export type Role = 'super-admin' | 'user'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  last_login_at?: string | null
}

export interface SessionInfo {
  id: string
  expires_at?: string | null
  last_seen_at?: string | null
}

export interface AuthBundle {
  user: User
  session_id: string
  access_token: string
  token_type: string
  expires_at: string
  expires_in: number
  refresh_token: string
  refresh_token_expires_at: string
}

export interface StoredAuth {
  accessToken: string
  refreshToken: string
  expiresAt: string
  refreshTokenExpiresAt: string
  sessionId: string
  user: User
}

export interface AuthProfile {
  user: User
  session: SessionInfo
  claims?: Record<string, unknown>
}

export interface Credentials {
  email: string
  password: string
}

export interface IpAddressInput {
  address: string
  label: string
  comment: string
}

export interface IpAddressUpdateInput {
  label: string
  comment: string
}

export interface IpAddressRecord {
  id: string
  address: string
  version: number
  label: string
  comment: string | null
  created_by_user_id: number
  created_by_name: string
  created_by_email: string
  updated_by_user_id: number | null
  updated_by_name: string | null
  updated_by_email: string | null
  created_at: string
  updated_at: string
}

export interface AuditChangeSet {
  before: unknown
  after: unknown
}

export interface AuditEvent {
  id: string
  category: string
  event: string
  actor_user_id?: number | null
  actor_name?: string | null
  actor_email?: string | null
  actor_role?: string | null
  session_uuid?: string | null
  subject_type: string
  subject_id?: string | null
  changes?: AuditChangeSet | null
  context?: Record<string, unknown> | null
  occurred_at: string
}

export interface AuditSummary {
  total_events: number
  auth_events: number
  ip_events: number
  sessions_seen: number
}

export interface AuditDashboard {
  summary: AuditSummary
  events: AuditEvent[]
}

export interface AuditFilters {
  session_uuid: string
  event: string
}

export interface ApiEnvelope<T> {
  data: T
}
