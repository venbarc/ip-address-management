import { useDeferredValue, useEffect, useState, useTransition } from 'react'
import type {
  ButtonHTMLAttributes,
  FormEvent,
  ReactNode,
} from 'react'
import {
  ApiError,
  auditApi,
  authApi,
  clearStoredSession,
  getStoredSession,
  ipApi,
} from './lib/api'
import type {
  AuditDashboard,
  AuditEvent,
  AuditFilters,
  AuthProfile,
  Credentials,
  IpAddressInput,
  IpAddressRecord,
  IpAddressUpdateInput,
  StoredAuth,
} from './types'

type ToastTone = 'success' | 'error' | 'neutral'

type ToastItem = {
  id: string
  tone: ToastTone
  title: string
  description?: string
}

type InventoryView = 'all' | 'ipv4' | 'ipv6' | 'mine'

const demoAccounts: Credentials[] = [
  { email: 'superadmin@example.com', password: 'password123' },
  { email: 'user@example.com', password: 'password123' },
]

const emptyRecordForm: IpAddressInput = {
  address: '',
  label: '',
  comment: '',
}

const emptyAuditFilters: AuditFilters = {
  session_uuid: '',
  event: '',
}

const exitAnimationDuration = 220
const entryAnimationDuration = 1800

function App() {
  const [booting, setBooting] = useState(true)
  const [session, setSession] = useState<StoredAuth | null>(() => authApi.hydrate())
  const [profile, setProfile] = useState<AuthProfile | null>(null)
  const [records, setRecords] = useState<IpAddressRecord[]>([])
  const [dashboard, setDashboard] = useState<AuditDashboard | null>(null)
  const [toasts, setToasts] = useState<ToastItem[]>([])

  const [loginForm, setLoginForm] = useState<Credentials>({
    email: '',
    password: '',
  })
  const [recordForm, setRecordForm] = useState<IpAddressInput>(emptyRecordForm)
  const [auditFilters, setAuditFilters] = useState<AuditFilters>(emptyAuditFilters)
  const [search, setSearch] = useState('')
  const [inventoryView, setInventoryView] = useState<InventoryView>('all')

  const [editTarget, setEditTarget] = useState<IpAddressRecord | null>(null)
  const [editForm, setEditForm] = useState<IpAddressUpdateInput>({
    label: '',
    comment: '',
  })
  const [deleteTarget, setDeleteTarget] = useState<IpAddressRecord | null>(null)

  const [loginPending, setLoginPending] = useState(false)
  const [signOutPending, setSignOutPending] = useState(false)
  const [createPending, setCreatePending] = useState(false)
  const [editPending, setEditPending] = useState(false)
  const [deletePending, setDeletePending] = useState(false)
  const [auditPending, setAuditPending] = useState(false)
  const [refreshPending, setRefreshPending] = useState(false)

  const [newlyAddedIds, setNewlyAddedIds] = useState<string[]>([])
  const [removingIds, setRemovingIds] = useState<string[]>([])
  const [isPending, startTransition] = useTransition()

  const deferredSearch = useDeferredValue(search)
  const superAdmin = profile?.user.role === 'super-admin'
  const loadingWorkspace = booting || refreshPending || isPending
  const hasActiveAuditFilters = Object.values(auditFilters).some(
    (value) => value.trim().length > 0,
  )

  const ipv4Count = records.filter((record) => record.version === 4).length
  const ipv6Count = records.filter((record) => record.version === 6).length
  const ownCount = records.filter(
    (record) => record.created_by_user_id === profile?.user.id,
  ).length
  const documentedCount = records.filter((record) => record.comment?.trim()).length
  const recentChangesCount = records.filter((record) => {
    const updatedAt = new Date(record.updated_at).getTime()
    const oneDay = 1000 * 60 * 60 * 24
    return Date.now() - updatedAt <= oneDay
  }).length

  const filteredRecords = records
    .filter((record) => {
      if (inventoryView === 'ipv4') {
        return record.version === 4
      }

      if (inventoryView === 'ipv6') {
        return record.version === 6
      }

      if (inventoryView === 'mine') {
        return record.created_by_user_id === profile?.user.id
      }

      return true
    })
    .filter((record) => {
      const haystack = [
        record.address,
        record.label,
        record.comment ?? '',
        record.created_by_name,
        record.created_by_email,
      ]
        .join(' ')
        .toLowerCase()

      return haystack.includes(deferredSearch.trim().toLowerCase())
    })

  useEffect(() => {
    let active = true

    const bootstrap = async () => {
      const existing = authApi.hydrate()

      if (!existing) {
        setBooting(false)
        return
      }

      try {
        const nextProfile = await authApi.me()
        const nextSession = getStoredSession()
        const nextRecords = await ipApi.list()
        const nextDashboard =
          nextProfile.user.role === 'super-admin'
            ? await auditApi.dashboard(emptyAuditFilters)
            : null

        if (!active) {
          return
        }

        startTransition(() => {
          setSession(nextSession)
          setProfile(nextProfile)
          setRecords(nextRecords)
          setDashboard(nextDashboard)
        })
      } catch (error) {
        clearStoredSession()

        if (active) {
          pushToast('Session expired', 'error', getErrorMessage(error))
          setSession(null)
          setProfile(null)
          setRecords([])
          setDashboard(null)
        }
      } finally {
        if (active) {
          setBooting(false)
        }
      }
    }

    void bootstrap()

    return () => {
      active = false
    }
  }, [])

  function pushToast(title: string, tone: ToastTone, description?: string) {
    const id = window.crypto.randomUUID()

    setToasts((current) => [...current, { id, tone, title, description }])

    window.setTimeout(() => {
      setToasts((current) => current.filter((toast) => toast.id !== id))
    }, 3400)
  }

  function dismissToast(id: string) {
    setToasts((current) => current.filter((toast) => toast.id !== id))
  }

  function flagNewRecord(id: string) {
    setNewlyAddedIds((current) => [...current, id])

    window.setTimeout(() => {
      setNewlyAddedIds((current) => current.filter((value) => value !== id))
    }, entryAnimationDuration)
  }

  async function syncAudit(nextFilters = auditFilters) {
    if (!superAdmin) {
      startTransition(() => {
        setDashboard(null)
      })
      return
    }

    const nextDashboard = await auditApi.dashboard(nextFilters)

    startTransition(() => {
      setDashboard(nextDashboard)
    })
  }

  async function refreshRecordList() {
    setRefreshPending(true)

    try {
      const nextRecords = await ipApi.list()

      startTransition(() => {
        setRecords(nextRecords)
      })
    } finally {
      setRefreshPending(false)
    }
  }

  async function handleLoginSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoginPending(true)

    try {
      const bundle = await authApi.login(loginForm)
      const nextSession = getStoredSession()
      const nextProfile: AuthProfile = {
        user: bundle.user,
        session: {
          id: bundle.session_id,
          expires_at: bundle.expires_at,
        },
      }

      const nextRecords = await ipApi.list()
      const nextDashboard =
        bundle.user.role === 'super-admin'
          ? await auditApi.dashboard(emptyAuditFilters)
          : null

      startTransition(() => {
        setSession(nextSession)
        setProfile(nextProfile)
        setRecords(nextRecords)
        setDashboard(nextDashboard)
        setLoginForm({ email: '', password: '' })
        setAuditFilters(emptyAuditFilters)
      })

      pushToast('Access granted', 'success', `Welcome back, ${bundle.user.name}.`)
    } catch (error) {
      pushToast('Sign in failed', 'error', getErrorMessage(error))
    } finally {
      setLoginPending(false)
    }
  }

  async function handleLogout() {
    setSignOutPending(true)

    try {
      await authApi.logout()

      startTransition(() => {
        setSession(null)
        setProfile(null)
        setRecords([])
        setDashboard(null)
        setDeleteTarget(null)
        setEditTarget(null)
      })

      pushToast('Signed out', 'neutral', 'Your session has been closed.')
    } catch (error) {
      pushToast('Could not sign out', 'error', getErrorMessage(error))
    } finally {
      setSignOutPending(false)
    }
  }

  async function handleCreateRecord(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setCreatePending(true)

    try {
      const created = await ipApi.create(recordForm)

      startTransition(() => {
        setRecords((current) => [created, ...current])
        setRecordForm(emptyRecordForm)
      })

      flagNewRecord(created.id)
      pushToast('IP address added', 'success', `${created.address} is now tracked.`)

      if (superAdmin) {
        await syncAudit()
      }
    } catch (error) {
      pushToast('Could not add IP address', 'error', getErrorMessage(error))
    } finally {
      setCreatePending(false)
    }
  }

  function openEditModal(record: IpAddressRecord) {
    setEditTarget(record)
    setEditForm({
      label: record.label,
      comment: record.comment ?? '',
    })
  }

  function closeEditModal() {
    if (editPending) {
      return
    }

    setEditTarget(null)
    setEditForm({
      label: '',
      comment: '',
    })
  }

  async function handleEditSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!editTarget) {
      return
    }

    setEditPending(true)

    try {
      const updated = await ipApi.update(editTarget.id, editForm)

      startTransition(() => {
        setRecords((current) =>
          current.map((record) => (record.id === updated.id ? updated : record)),
        )
        setEditTarget(null)
        setEditForm({
          label: '',
          comment: '',
        })
      })

      pushToast('Record updated', 'success', `${updated.address} has been revised.`)

      if (superAdmin) {
        await syncAudit()
      }
    } catch (error) {
      pushToast('Update failed', 'error', getErrorMessage(error))
    } finally {
      setEditPending(false)
    }
  }

  function openDeleteModal(record: IpAddressRecord) {
    setDeleteTarget(record)
  }

  function closeDeleteModal() {
    if (deletePending) {
      return
    }

    setDeleteTarget(null)
  }

  async function handleDeleteConfirm() {
    if (!deleteTarget) {
      return
    }

    setDeletePending(true)

    try {
      await ipApi.remove(deleteTarget.id)

      setDeleteTarget(null)
      setRemovingIds((current) => [...current, deleteTarget.id])

      await wait(exitAnimationDuration)

      startTransition(() => {
        setRecords((current) =>
          current.filter((record) => record.id !== deleteTarget.id),
        )
        setRemovingIds((current) =>
          current.filter((value) => value !== deleteTarget.id),
        )
      })

      pushToast(
        'Record removed',
        'success',
        `${deleteTarget.address} has been deleted.`,
      )

      if (superAdmin) {
        await syncAudit()
      }
    } catch (error) {
      pushToast('Delete failed', 'error', getErrorMessage(error))
      setRemovingIds((current) =>
        current.filter((value) => value !== deleteTarget.id),
      )
    } finally {
      setDeletePending(false)
    }
  }

  async function handleAuditSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setAuditPending(true)

    try {
      await syncAudit(auditFilters)
      pushToast('Audit refreshed', 'neutral', 'Dashboard filters have been applied.')
    } catch (error) {
      pushToast('Audit refresh failed', 'error', getErrorMessage(error))
    } finally {
      setAuditPending(false)
    }
  }

  async function handleAuditReset() {
    setAuditPending(true)

    try {
      startTransition(() => {
        setAuditFilters(emptyAuditFilters)
      })

      await syncAudit(emptyAuditFilters)
      pushToast('Filters cleared', 'neutral', 'Showing the full audit history again.')
    } catch (error) {
      pushToast('Could not reset filters', 'error', getErrorMessage(error))
    } finally {
      setAuditPending(false)
    }
  }

  if (booting) {
    return (
      <>
        <main className="boot-screen">
          <div className="boot-panel">
            <ShieldMark />
            <p className="overline">IP Address Management</p>
            <h1>Restoring the latest session.</h1>
            <p className="muted-copy">
              Checking the gateway token and refreshing your workspace.
            </p>
          </div>
        </main>
        <ToastViewport toasts={toasts} onDismiss={dismissToast} />
      </>
    )
  }

  if (!session || !profile) {
    return (
      <>
        <main className="auth-shell">
          <section className="auth-layout">
            <div className="brand-stack">
              <div className="brand-mark">
                <ShieldMark />
              </div>
              <h1>IP Address Management</h1>
              <p className="brand-subtitle">Address Management Console</p>
            </div>

            <section className="auth-card">
              <div className="auth-card-head">
                <div>
                  <h2>Secure Access</h2>
                  <p className="muted-copy">Authorized personnel only.</p>
                </div>
              </div>

              <form className="form-stack" onSubmit={handleLoginSubmit}>
                <label className="field-stack">
                  <span>Corporate email</span>
                  <input
                    type="email"
                    value={loginForm.email}
                    onChange={(event) =>
                      setLoginForm((current) => ({
                        ...current,
                        email: event.target.value,
                      }))
                    }
                    placeholder="name@organization.com"
                    autoComplete="username"
                  />
                </label>

                <label className="field-stack">
                  <span>Password</span>
                  <input
                    type="password"
                    value={loginForm.password}
                    onChange={(event) =>
                      setLoginForm((current) => ({
                        ...current,
                        password: event.target.value,
                      }))
                    }
                    placeholder="Enter your password"
                    autoComplete="current-password"
                  />
                </label>

                <LoadingButton
                  className="primary-button full-width"
                  type="submit"
                  loading={loginPending}
                >
                  Log In
                </LoadingButton>
              </form>

              <div className="divider" />

              <div className="account-picker">
                <p className="muted-copy small">Seeded exam accounts</p>
                <p className="muted-copy account-note">
                  Run the database seeders first, then use one of the accounts below.
                </p>
                {demoAccounts.map((account) => (
                  <button
                    key={account.email}
                    className="account-row"
                    type="button"
                    onClick={() => setLoginForm(account)}
                  >
                    <span>{account.email}</span>
                    <small>{account.password}</small>
                  </button>
                ))}
              </div>
            </section>

            <div className="trust-strip">
              <TrustItem label="Encrypted" />
              <TrustItem label="Audited" />
              <TrustItem label="Auto-renew" />
            </div>

            <div className="system-pill">IP Address Management v4.0</div>
          </section>
        </main>
        <ToastViewport toasts={toasts} onDismiss={dismissToast} />
      </>
    )
  }

  return (
    <>
      <main className="workspace-shell">
        <section className="workspace-main">
          <header className="workspace-topbar surface">
            <div className="workspace-heading">
              <p className="overline">Network operations</p>
              <h1>IP inventory</h1>
              <p className="muted-copy">
                Track ownership, change history, and role-based actions from one
                console.
              </p>
            </div>

            <div className="workspace-utility">
              <div className="header-permission-notice">
                <button
                  className="ghost-button topbar-action-button header-permission-trigger"
                  type="button"
                  aria-label="View permission notice"
                >
                  Permissions
                  <span className="header-permission-dot" aria-hidden="true" />
                </button>

                <div className="permission-popover" role="note">
                  <strong>Permissions</strong>
                  <p className="muted-copy">Role-based access for this workspace.</p>
                  <ul className="permission-popover-list">
                    <li className="permission-popover-item">
                      <span className="permission-popover-tag">View</span>
                      <span>All users can browse every record in the inventory.</span>
                    </li>
                    <li className="permission-popover-item">
                      <span className="permission-popover-tag">Edit</span>
                      <span>Regular users can revise only the records they created.</span>
                    </li>
                    <li className="permission-popover-item">
                      <span className="permission-popover-tag">Delete</span>
                      <span>Only super-admins can delete records and inspect audit logs.</span>
                    </li>
                  </ul>
                </div>
              </div>

              <div className="workspace-user-chip">
                <div className="user-avatar">{getInitials(profile.user.name)}</div>
                <div className="user-chip-copy">
                  <strong>{profile.user.name}</strong>
                  <small>{formatRole(profile.user.role)}</small>
                </div>
              </div>

              <LoadingButton
                className="ghost-button topbar-action-button"
                type="button"
                loading={signOutPending}
                onClick={handleLogout}
              >
                Sign out
              </LoadingButton>
            </div>
          </header>

          <section id="overview" className="metric-grid">
            <MetricCard
              label="Tracked records"
              value={String(records.length)}
              detail={`${documentedCount} documented / ${records.length - documentedCount} pending notes`}
              tone="blue"
            />
            <MetricCard
              label="Editable by you"
              value={String(ownCount)}
              detail="Records currently under your ownership"
              tone="emerald"
            />
            <MetricCard
              label="IPv6 coverage"
              value={String(ipv6Count)}
              detail={`${ipv4Count} IPv4 records remain in the inventory`}
              tone="slate"
            />
            <MetricCard
              label={superAdmin ? 'Audit events' : 'Recent changes'}
              value={String(
                superAdmin ? (dashboard?.summary.total_events ?? 0) : recentChangesCount,
              )}
              detail={
                superAdmin
                  ? 'Immutable activity across auth and IP services'
                  : 'Records updated during the last 24 hours'
              }
              tone="amber"
            />
          </section>

          <section className="dashboard-layout">
            <aside id="composer" className="surface composer-panel">
              <div className="panel-head">
                <div>
                  <p className="overline">New record</p>
                  <h2>Add an IP address</h2>
                  <p className="muted-copy composer-copy">
                    Record IPv4 or IPv6 addresses with a clear label and an optional
                    operational note.
                  </p>
                </div>
                {loadingWorkspace ? <span className="status-pill">Syncing</span> : null}
              </div>

              <form className="form-stack composer-form" onSubmit={handleCreateRecord}>
                <label className="field-stack">
                  <span>IP address</span>
                  <input
                    type="text"
                    value={recordForm.address}
                    onChange={(event) =>
                      setRecordForm((current) => ({
                        ...current,
                        address: event.target.value,
                      }))
                    }
                    placeholder="10.0.0.15 or 2001:db8::10"
                  />
                </label>

                <label className="field-stack">
                  <span>Label</span>
                  <input
                    type="text"
                    value={recordForm.label}
                    onChange={(event) =>
                      setRecordForm((current) => ({
                        ...current,
                        label: event.target.value,
                      }))
                    }
                    placeholder="Edge router uplink"
                  />
                </label>

                <label className="field-stack">
                  <span>Comment</span>
                  <textarea
                    rows={4}
                    value={recordForm.comment}
                    onChange={(event) =>
                      setRecordForm((current) => ({
                        ...current,
                        comment: event.target.value,
                      }))
                    }
                    placeholder="Optional implementation note"
                  />
                </label>

                <LoadingButton
                  className="primary-button primary-button-strong full-width form-submit-button"
                  type="submit"
                  loading={createPending}
                >
                  Save IP address
                </LoadingButton>
              </form>

            </aside>

            <section id="inventory" className="surface inventory-panel">
              <div className="inventory-panel-head">
                <div>
                  <p className="overline">Inventory board</p>
                  <h2>Recorded IP addresses</h2>
                  <p className="muted-copy inventory-copy">
                    Search, filter, and update records from a single operational
                    view.
                  </p>
                </div>
                <div className="inventory-panel-side">
                  <strong>{filteredRecords.length} visible</strong>
                  <small>
                    {deferredSearch.trim()
                      ? 'Filtered by the current search query'
                      : 'Showing the current inventory scope'}
                  </small>
                </div>
              </div>

              <div className="inventory-toolbar">
                <label className="inventory-search">
                  <span>Search inventory</span>
                  <input
                    className="search-field inventory-search-input"
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search IP, label, owner, or comment"
                  />
                </label>

                <LoadingButton
                  className="ghost-button toolbar-button"
                  type="button"
                  loading={refreshPending}
                  onClick={() => void refreshRecordList()}
                >
                  Refresh
                </LoadingButton>
              </div>

              <div className="inventory-filter-row">
                <button
                  className={`filter-chip ${inventoryView === 'all' ? 'filter-chip-active' : ''}`}
                  type="button"
                  onClick={() => setInventoryView('all')}
                >
                  All records
                  <span>{records.length}</span>
                </button>
                <button
                  className={`filter-chip ${inventoryView === 'ipv4' ? 'filter-chip-active' : ''}`}
                  type="button"
                  onClick={() => setInventoryView('ipv4')}
                >
                  IPv4
                  <span>{ipv4Count}</span>
                </button>
                <button
                  className={`filter-chip ${inventoryView === 'ipv6' ? 'filter-chip-active' : ''}`}
                  type="button"
                  onClick={() => setInventoryView('ipv6')}
                >
                  IPv6
                  <span>{ipv6Count}</span>
                </button>
                <button
                  className={`filter-chip ${inventoryView === 'mine' ? 'filter-chip-active' : ''}`}
                  type="button"
                  onClick={() => setInventoryView('mine')}
                >
                  Mine
                  <span>{ownCount}</span>
                </button>
              </div>

              <div className="inventory-table">
                <div className="inventory-table-head">
                  <span>Record</span>
                  <span>Owner</span>
                  <span>Last update</span>
                  <span>Actions</span>
                </div>

                {filteredRecords.length === 0 ? (
                  <div className="empty-state inventory-empty-state">
                    <strong>No records match this view.</strong>
                    <span>Try a different filter or add a new IP record.</span>
                  </div>
                ) : null}

                {filteredRecords.map((record) => {
                  const canEdit =
                    superAdmin || record.created_by_user_id === profile.user.id
                  const canDelete = superAdmin
                  const isEntering = newlyAddedIds.includes(record.id)
                  const isLeaving = removingIds.includes(record.id)

                  return (
                    <article
                      key={record.id}
                      className={[
                        'inventory-row',
                        isEntering ? 'record-card-enter' : '',
                        isLeaving ? 'record-card-exit' : '',
                      ]
                        .filter(Boolean)
                        .join(' ')}
                    >
                      <div
                        className="inventory-cell inventory-cell-label"
                        data-label="Record"
                      >
                        <strong>{record.label}</strong>
                        <small>{record.comment?.trim() || 'No operational note yet.'}</small>
                        <div className="inventory-record-meta">
                          <span className="inventory-address">{record.address}</span>
                          <span className="inline-pill">IPv{record.version}</span>
                        </div>
                      </div>

                      <div
                        className="inventory-cell inventory-cell-owner"
                        data-label="Owner"
                      >
                        <strong>{record.created_by_name}</strong>
                        <small>{record.created_by_email}</small>
                      </div>

                      <div
                        className="inventory-cell inventory-cell-updated"
                        data-label="Last update"
                      >
                        <strong>{formatTimestamp(record.updated_at)}</strong>
                        <small>by {record.updated_by_name ?? 'Original author'}</small>
                      </div>

                      <div
                        className="inventory-cell inventory-cell-actions"
                        data-label="Actions"
                      >
                        <button
                          className="table-icon-button"
                          type="button"
                          onClick={() => openEditModal(record)}
                          disabled={!canEdit || editPending || deletePending}
                          aria-label={`Edit ${record.label}`}
                          title="Edit record"
                        >
                          <PencilIcon />
                        </button>
                        {canDelete ? (
                          <button
                            className="table-icon-button table-icon-button-danger"
                            type="button"
                            onClick={() => openDeleteModal(record)}
                            disabled={deletePending}
                            aria-label={`Delete ${record.label}`}
                            title="Delete record"
                          >
                            <TrashIcon />
                          </button>
                        ) : null}
                      </div>
                    </article>
                  )
                })}
              </div>
            </section>
          </section>

          {superAdmin ? (
            <section id="audit" className="surface audit-panel">
              <div className="inventory-panel-head">
                <div>
                  <p className="overline">Super-admin audit</p>
                  <h2>Session and lifetime activity</h2>
                  <p className="muted-copy audit-panel-copy">
                    Narrow the stream by session or event, then inspect immutable
                    activity across the auth and IP services.
                  </p>
                </div>
                <div className="inventory-panel-side">
                  <strong>{dashboard?.summary.total_events ?? 0} visible</strong>
                  <small>{dashboard?.summary.sessions_seen ?? 0} sessions in scope</small>
                </div>
              </div>

              <section className="audit-summary-grid">
                <AuditSummaryCard
                  label="Visible events"
                  value={dashboard?.summary.total_events ?? 0}
                  detail="Events in the current result set"
                />
                <AuditSummaryCard
                  label="Sessions"
                  value={dashboard?.summary.sessions_seen ?? 0}
                  detail="Distinct sessions in the current result set"
                />
                <AuditSummaryCard
                  label="Auth events"
                  value={dashboard?.summary.auth_events ?? 0}
                  detail="Login, refresh, and logout activity"
                />
                <AuditSummaryCard
                  label="IP events"
                  value={dashboard?.summary.ip_events ?? 0}
                  detail="Create, update, and delete activity"
                />
              </section>

              <form className="audit-controls" onSubmit={handleAuditSubmit}>
                <div className="audit-field-row">
                  <label className="audit-form-field">
                    <span>Session ID</span>
                    <input
                      type="text"
                      value={auditFilters.session_uuid}
                      onChange={(event) =>
                        setAuditFilters((current) => ({
                          ...current,
                          session_uuid: event.target.value,
                        }))
                      }
                      placeholder="Partial match is okay"
                    />
                  </label>
                  <label className="audit-form-field">
                    <span>Event name</span>
                    <input
                      type="text"
                      value={auditFilters.event}
                      onChange={(event) =>
                        setAuditFilters((current) => ({
                          ...current,
                          event: event.target.value,
                        }))
                      }
                      placeholder="auth.login or ip.updated"
                    />
                  </label>
                </div>

                <div className="audit-action-row">
                  <LoadingButton
                    className="primary-button filter-action-button"
                    type="submit"
                    loading={auditPending}
                  >
                    Apply filters
                  </LoadingButton>
                  <button
                    className="ghost-button filter-clear-button"
                    type="button"
                    onClick={() => void handleAuditReset()}
                    disabled={auditPending || !hasActiveAuditFilters}
                  >
                    Clear
                  </button>
                </div>
              </form>

              <div className="audit-guide">
                <span className="audit-guide-chip">Session ID accepts partial text</span>
                <span className="audit-guide-chip">
                  Event examples: auth.login, ip.created, ip.deleted
                </span>
              </div>

              <div className="audit-list">
                {!dashboard?.events.length ? (
                  <div className="empty-state empty-state-audit">
                    <strong>
                      {hasActiveAuditFilters
                        ? 'No audit events match the current filters.'
                        : 'No audit events yet.'}
                    </strong>
                    <span>
                      {hasActiveAuditFilters
                        ? 'Try part of the session ID or event name, or clear the filters.'
                        : 'Create logins or IP changes first, then refresh this dashboard.'}
                    </span>
                    {hasActiveAuditFilters ? (
                      <button
                        className="ghost-button"
                        type="button"
                        onClick={() => void handleAuditReset()}
                        disabled={auditPending}
                      >
                        Show all audit events
                      </button>
                    ) : null}
                  </div>
                ) : null}

                {dashboard?.events.map((event) => (
                  <AuditTimelineItem key={event.id} event={event} />
                ))}
              </div>
            </section>
          ) : null}
        </section>
      </main>

      <ModalShell
        open={editTarget !== null}
        title="Edit IP record"
        description={editTarget ? editTarget.address : 'Update the selected entry.'}
        onClose={closeEditModal}
      >
        <form className="form-stack" onSubmit={handleEditSubmit}>
          <label className="field-stack">
            <span>Label</span>
            <input
              type="text"
              value={editForm.label}
              onChange={(event) =>
                setEditForm((current) => ({
                  ...current,
                  label: event.target.value,
                }))
              }
            />
          </label>

          <label className="field-stack">
            <span>Comment</span>
            <textarea
              rows={4}
              value={editForm.comment}
              onChange={(event) =>
                setEditForm((current) => ({
                  ...current,
                  comment: event.target.value,
                }))
              }
            />
          </label>

          <div className="modal-actions">
            <button
              className="ghost-button"
              type="button"
              onClick={closeEditModal}
              disabled={editPending}
            >
              Cancel
            </button>
            <LoadingButton
              className="primary-button"
              type="submit"
              loading={editPending}
            >
              Save changes
            </LoadingButton>
          </div>
        </form>
      </ModalShell>

      <ModalShell
        open={deleteTarget !== null}
        title="Delete record"
        description={
          deleteTarget
            ? `Remove ${deleteTarget.address} from the inventory? This action cannot be undone.`
            : 'Confirm deletion.'
        }
        onClose={closeDeleteModal}
      >
        <div className="confirmation-block">
          <p>
            The audit history stays intact, but the live IP entry will be removed
            from the management list.
          </p>
        </div>

        <div className="modal-actions">
          <button
            className="ghost-button"
            type="button"
            onClick={closeDeleteModal}
            disabled={deletePending}
          >
            Keep record
          </button>
          <LoadingButton
            className="danger-button"
            type="button"
            loading={deletePending}
            onClick={handleDeleteConfirm}
          >
            Delete record
          </LoadingButton>
        </div>
      </ModalShell>

      <ToastViewport toasts={toasts} onDismiss={dismissToast} />
    </>
  )
}

function ShieldMark() {
  return (
    <svg
      className="shield-icon"
      viewBox="0 0 48 48"
      fill="none"
      aria-hidden="true"
    >
      <rect x="4" y="4" width="40" height="40" rx="12" fill="url(#shield-fill)" />
      <path
        d="M24 13L32 16.2V22.8C32 28.2 28.4 32.9 24 34.6C19.6 32.9 16 28.2 16 22.8V16.2L24 13Z"
        fill="#0F5FC6"
      />
      <path
        d="M24 19.1C25.3 17.5 28 18.1 28 20.5C28 23.2 24.9 24.8 24 26C23.1 24.8 20 23.2 20 20.5C20 18.1 22.7 17.5 24 19.1Z"
        fill="white"
      />
      <defs>
        <linearGradient id="shield-fill" x1="6" y1="6" x2="42" y2="42">
          <stop stopColor="#F3F7FC" />
          <stop offset="1" stopColor="#E6EDF7" />
        </linearGradient>
      </defs>
    </svg>
  )
}

function PencilIcon() {
  return (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <path
        d="M4 20H8L18.3 9.7C18.7 9.3 18.7 8.7 18.3 8.3L15.7 5.7C15.3 5.3 14.7 5.3 14.3 5.7L4 16V20Z"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M12.5 7.5L16.5 11.5"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function TrashIcon() {
  return (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <path
        d="M5 7H19"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
      />
      <path
        d="M9 7V5.8C9 5.36 9.36 5 9.8 5H14.2C14.64 5 15 5.36 15 5.8V7"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M7 7L7.7 18.2C7.75 19 8.42 19.6 9.2 19.6H14.8C15.58 19.6 16.25 19 16.3 18.2L17 7"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M10 10.5V16"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
      />
      <path
        d="M14 10.5V16"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
      />
    </svg>
  )
}

function TrustItem({ label }: { label: string }) {
  return (
    <div className="trust-item">
      <span className="trust-icon" />
      <small>{label}</small>
    </div>
  )
}

function MetricCard(props: {
  label: string
  value: string
  detail: string
  tone: 'blue' | 'emerald' | 'slate' | 'amber'
}) {
  return (
    <article className={`surface metric-card metric-card-${props.tone}`}>
      <span>{props.label}</span>
      <strong>{props.value}</strong>
      <small>{props.detail}</small>
      <div className="metric-card-bar" aria-hidden="true" />
    </article>
  )
}

function AuditSummaryCard(props: {
  label: string
  value: number
  detail: string
}) {
  return (
    <article
      className={[
        'audit-summary-card',
        props.value === 0 ? 'audit-summary-card-muted' : '',
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <span>{props.label}</span>
      <strong>{props.value}</strong>
      <small className="audit-summary-detail">{props.detail}</small>
    </article>
  )
}

function AuditTimelineItem({ event }: { event: AuditEvent }) {
  return (
    <article className="audit-item">
      <div className="audit-item-top">
        <div>
          <p className="audit-event-name">{event.event}</p>
          <small className="audit-meta-line">
            {event.actor_name || 'System'} | {event.actor_role || 'n/a'} |{' '}
            {formatTimestamp(event.occurred_at)}
          </small>
        </div>
        <span className="inline-pill">{humanizeSubjectType(event.subject_type)}</span>
      </div>
      <p className="audit-item-copy">{formatAuditDescription(event)}</p>
      <div className="audit-item-footer">
        {event.session_uuid ? (
          <small className="audit-footnote">Session {event.session_uuid}</small>
        ) : (
          <small className="audit-footnote">No linked session</small>
        )}
      </div>
    </article>
  )
}

type LoadingButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  loading?: boolean
  children: ReactNode
}

function LoadingButton({
  loading = false,
  children,
  className,
  disabled,
  ...props
}: LoadingButtonProps) {
  return (
    <button
      {...props}
      className={className}
      disabled={disabled || loading}
    >
      <span className="button-inner">
        {loading ? <span className="button-spinner" aria-hidden="true" /> : null}
        <span>{children}</span>
      </span>
    </button>
  )
}

function ModalShell(props: {
  open: boolean
  title: string
  description: string
  onClose: () => void
  children: ReactNode
}) {
  if (!props.open) {
    return null
  }

  return (
    <div className="modal-backdrop" onClick={props.onClose}>
      <section
        className="modal-panel"
        role="dialog"
        aria-modal="true"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="modal-head">
          <div>
            <h2>{props.title}</h2>
            <p className="muted-copy">{props.description}</p>
          </div>
          <button className="icon-button" type="button" onClick={props.onClose}>
            x
          </button>
        </div>
        {props.children}
      </section>
    </div>
  )
}

function ToastViewport(props: {
  toasts: ToastItem[]
  onDismiss: (id: string) => void
}) {
  return (
    <div className="toast-stack">
      {props.toasts.map((toast) => (
        <div key={toast.id} className={`toast toast-${toast.tone}`}>
          <div>
            <strong>{toast.title}</strong>
            {toast.description ? <p>{toast.description}</p> : null}
          </div>
          <button
            className="icon-button"
            type="button"
            onClick={() => props.onDismiss(toast.id)}
          >
            x
          </button>
        </div>
      ))}
    </div>
  )
}

function formatRole(value: string) {
  if (value === 'super-admin') {
    return 'Super Admin'
  }

  return 'Regular User'
}

function formatTimestamp(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatAuditDescription(event: AuditEvent) {
  if (event.context?.message && typeof event.context.message === 'string') {
    return event.context.message
  }

  const before = toPlainObject(event.changes?.before)
  const after = toPlainObject(event.changes?.after)

  if (!before && after) {
    return `Created ${String(after.label ?? after.address ?? event.subject_id ?? 'record')}.`
  }

  if (before && !after) {
    return `Deleted ${String(before.label ?? before.address ?? event.subject_id ?? 'record')}.`
  }

  if (before && after) {
    const labelChanged = before.label !== after.label
    const commentChanged = (before.comment ?? '') !== (after.comment ?? '')

    if (labelChanged && commentChanged) {
      return `Updated label and comment for ${String(after.address ?? event.subject_id ?? 'record')}.`
    }

    if (labelChanged) {
      return `Changed label from "${String(before.label ?? 'n/a')}" to "${String(after.label ?? 'n/a')}".`
    }

    if (commentChanged) {
      return `Updated the comment on ${String(after.address ?? event.subject_id ?? 'record')}.`
    }
  }

  return 'Recorded a new audit entry.'
}

function getInitials(value: string) {
  const parts = value.trim().split(/\s+/).filter(Boolean)

  return parts
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

function humanizeSubjectType(value: string) {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase())
}

function toPlainObject(value: unknown): Record<string, unknown> | null {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return value as Record<string, unknown>
  }

  return null
}

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    return error.message
  }

  if (error instanceof Error) {
    return error.message
  }

  return 'Something went wrong.'
}

function wait(duration: number) {
  return new Promise<void>((resolve) => {
    window.setTimeout(resolve, duration)
  })
}

export default App
