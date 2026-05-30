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
  starter: 'bg-gray-100 text-gray-700',
  pro: 'bg-indigo-100 text-indigo-700',
  enterprise: 'bg-amber-100 text-amber-700',
}

function SummaryCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border bg-white p-5">
      <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
      <p className="mt-2 text-2xl font-bold text-gray-900">{value}</p>
    </div>
  )
}

function SummaryCardSkeleton() {
  return (
    <div className="rounded-lg border bg-white p-5 animate-pulse">
      <div className="h-3 w-24 rounded bg-gray-200" />
      <div className="mt-3 h-7 w-16 rounded bg-gray-200" />
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
        <h1 className="text-2xl font-bold text-gray-900">Dashboard da Plataforma</h1>
        <p className="mt-1 text-sm text-gray-500">
          Visão consolidada de todos os salões e atividade da plataforma.
        </p>
      </div>

      {error && (
        <div className="rounded-md bg-red-50 p-4 text-sm text-red-700">{error}</div>
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
        <div className="rounded-lg border bg-white">
          <div className="border-b p-5">
            <h2 className="text-sm font-semibold text-gray-700">Distribuição por Plano</h2>
          </div>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-gray-50">
                <th className="px-5 py-3 text-left font-medium text-gray-600">Plano</th>
                <th className="px-5 py-3 text-right font-medium text-gray-600">Salões</th>
              </tr>
            </thead>
            <tbody>
              {metrics.tenants_by_plan.map((row) => (
                <tr key={row.plan} className="border-b last:border-0 hover:bg-gray-50">
                  <td className="px-5 py-3">
                    <span
                      className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${PLAN_COLORS[row.plan] ?? 'bg-gray-100 text-gray-700'}`}
                    >
                      {PLAN_LABELS[row.plan] ?? row.plan}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-right font-semibold text-gray-900">
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
