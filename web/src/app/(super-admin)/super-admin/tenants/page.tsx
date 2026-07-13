'use client'

import { useEffect, useState } from 'react'
import { adminService } from '@/services/admin'
import { formatDate } from '@/lib/format'
import type { AdminTenant, PaginatedResponse } from '@/types'

const PLAN_OPTIONS = ['starter', 'pro', 'enterprise'] as const

const PLAN_LABELS: Record<string, string> = {
  starter: 'Starter',
  pro: 'Pro',
  enterprise: 'Enterprise',
}

const PLAN_COLORS: Record<string, string> = {
  starter: 'bg-muted text-foreground',
  pro: 'bg-secondary dark:bg-primary/15 text-secondary-foreground dark:text-primary',
  enterprise: 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400',
}

export default function TenantsPage() {
  const [data, setData] = useState<PaginatedResponse<AdminTenant> | null>(null)
  const [page, setPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [updating, setUpdating] = useState<string | null>(null)

  useEffect(() => {
    adminService
      .listTenants(page)
      .then((res) => setData(res.data))
      .catch(() => setError('Erro ao carregar salões'))
      .finally(() => setIsLoading(false))
  }, [page])

  async function handlePlanChange(tenant: AdminTenant, plan: string) {
    setUpdating(tenant.id)
    try {
      await adminService.updateTenant(tenant.id, { plan })
      setData((prev) =>
        prev
          ? {
              ...prev,
              data: prev.data.map((t) => (t.id === tenant.id ? { ...t, plan: plan as AdminTenant['plan'] } : t)),
            }
          : prev,
      )
    } catch {
      // silently fail — value resets on next fetch
    } finally {
      setUpdating(null)
    }
  }

  async function handleToggleActive(tenant: AdminTenant) {
    setUpdating(tenant.id)
    try {
      await adminService.updateTenant(tenant.id, { active: !tenant.active })
      setData((prev) =>
        prev
          ? {
              ...prev,
              data: prev.data.map((t) =>
                t.id === tenant.id ? { ...t, active: !t.active } : t,
              ),
            }
          : prev,
      )
    } catch {
      // silently fail
    } finally {
      setUpdating(null)
    }
  }

  const meta = data?.meta

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Salões</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Gerencie todos os salões cadastrados na plataforma.
        </p>
      </div>

      {error && (
        <div className="rounded-md bg-red-50 dark:bg-red-950/40 p-4 text-sm text-red-700 dark:text-red-400">{error}</div>
      )}

      <div className="rounded-lg border bg-card">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted">
              <th className="px-5 py-3 text-left font-medium text-muted-foreground">Negócio</th>
              <th className="px-5 py-3 text-left font-medium text-muted-foreground">Plano</th>
              <th className="px-5 py-3 text-center font-medium text-muted-foreground">Status</th>
              <th className="px-5 py-3 text-right font-medium text-muted-foreground">Usuários</th>
              <th className="px-5 py-3 text-right font-medium text-muted-foreground">Criado em</th>
            </tr>
          </thead>
          <tbody>
            {isLoading
              ? Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i} className="border-b animate-pulse">
                    <td className="px-5 py-4">
                      <div className="h-4 w-32 rounded bg-muted" />
                      <div className="mt-1 h-3 w-20 rounded bg-muted" />
                    </td>
                    <td className="px-5 py-4">
                      <div className="h-5 w-16 rounded-full bg-muted" />
                    </td>
                    <td className="px-5 py-4 text-center">
                      <div className="mx-auto h-5 w-10 rounded-full bg-muted" />
                    </td>
                    <td className="px-5 py-4 text-right">
                      <div className="ml-auto h-4 w-6 rounded bg-muted" />
                    </td>
                    <td className="px-5 py-4 text-right">
                      <div className="ml-auto h-4 w-20 rounded bg-muted" />
                    </td>
                  </tr>
                ))
              : data?.data.map((tenant) => (
                  <tr key={tenant.id} className="border-b last:border-0 hover:bg-muted">
                    <td className="px-5 py-4">
                      <p className="font-medium text-foreground">{tenant.name}</p>
                      <p className="text-xs text-muted-foreground">{tenant.slug}</p>
                    </td>
                    <td className="px-5 py-4">
                      <select
                        value={tenant.plan}
                        disabled={updating === tenant.id}
                        onChange={(e) => handlePlanChange(tenant, e.target.value)}
                        className={`rounded-full px-2.5 py-0.5 text-xs font-semibold border-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 ${PLAN_COLORS[tenant.plan] ?? 'bg-muted text-foreground'}`}
                      >
                        {PLAN_OPTIONS.map((p) => (
                          <option key={p} value={p}>
                            {PLAN_LABELS[p]}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="px-5 py-4 text-center">
                      <button
                        onClick={() => handleToggleActive(tenant)}
                        disabled={updating === tenant.id}
                        className={`inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 ${tenant.active ? 'bg-green-500' : 'bg-muted'}`}
                        title={tenant.active ? 'Desativar' : 'Ativar'}
                      >
                        <span
                          className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${tenant.active ? 'translate-x-4' : 'translate-x-0.5'}`}
                        />
                      </button>
                    </td>
                    <td className="px-5 py-4 text-right text-foreground">{tenant.user_count}</td>
                    <td className="px-5 py-4 text-right text-muted-foreground">
                      {formatDate(tenant.created_at)}
                    </td>
                  </tr>
                ))}
          </tbody>
        </table>

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t px-5 py-3">
            <p className="text-xs text-muted-foreground">
              {meta.total} salões · página {meta.current_page} de {meta.last_page}
            </p>
            <div className="flex gap-2">
              <button
                onClick={() => { setIsLoading(true); setPage((p) => p - 1) }}
                disabled={meta.current_page === 1}
                className="rounded-md border px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Anterior
              </button>
              <button
                onClick={() => { setIsLoading(true); setPage((p) => p + 1) }}
                disabled={meta.current_page === meta.last_page}
                className="rounded-md border px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Próximo
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
