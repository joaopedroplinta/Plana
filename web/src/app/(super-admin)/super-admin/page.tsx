'use client'

import { useEffect, useState } from 'react'
import { adminService } from '@/services/admin'
import { formatPrice } from '@/lib/format'
import type { AdminMetrics } from '@/types'

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

function SummaryCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border bg-card p-5">
      <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="mt-2 text-2xl font-bold text-foreground">{value}</p>
    </div>
  )
}

function SummaryCardSkeleton() {
  return (
    <div className="rounded-lg border bg-card p-5 animate-pulse">
      <div className="h-3 w-24 rounded bg-muted" />
      <div className="mt-3 h-7 w-16 rounded bg-muted" />
    </div>
  )
}

export default function SuperAdminDashboard() {
  const [metrics, setMetrics] = useState<AdminMetrics | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    adminService
      .metrics()
      .then((res) => setMetrics(res.data.data))
      .catch(() => setError('Erro ao carregar métricas'))
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Dashboard da Plataforma</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Visão consolidada de todos os salões e atividade da plataforma.
        </p>
      </div>

      {error && (
        <div className="rounded-md bg-red-50 dark:bg-red-950/40 p-4 text-sm text-red-700 dark:text-red-400">{error}</div>
      )}

      {/* Summary cards */}
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {isLoading ? (
          Array.from({ length: 4 }).map((_, i) => <SummaryCardSkeleton key={i} />)
        ) : metrics ? (
          <>
            <SummaryCard label="Total de Salões" value={String(metrics.total_tenants)} />
            <SummaryCard label="Salões Ativos" value={String(metrics.active_tenants)} />
            <SummaryCard label="Total de Usuários" value={String(metrics.total_users)} />
            <SummaryCard
              label="Receita da Plataforma"
              value={formatPrice(metrics.total_revenue)}
            />
          </>
        ) : null}
      </div>

      {/* Tenants by plan */}
      {!isLoading && metrics && metrics.tenants_by_plan.length > 0 && (
        <div className="rounded-lg border bg-card">
          <div className="border-b p-5">
            <h2 className="text-sm font-semibold text-foreground">Distribuição por Plano</h2>
          </div>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted">
                <th className="px-5 py-3 text-left font-medium text-muted-foreground">Plano</th>
                <th className="px-5 py-3 text-right font-medium text-muted-foreground">Salões</th>
              </tr>
            </thead>
            <tbody>
              {metrics.tenants_by_plan.map((row) => (
                <tr key={row.plan} className="border-b last:border-0 hover:bg-muted">
                  <td className="px-5 py-3">
                    <span
                      className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${PLAN_COLORS[row.plan] ?? 'bg-muted text-foreground'}`}
                    >
                      {PLAN_LABELS[row.plan] ?? row.plan}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-right font-semibold text-foreground">
                    {row.count}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
