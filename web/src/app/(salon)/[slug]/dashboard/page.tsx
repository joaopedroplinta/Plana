'use client'

import { useEffect, useState } from 'react'
import { useParams } from 'next/navigation'
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
} from 'recharts'
import { metricsService } from '@/services/metrics'
import { formatPrice } from '@/lib/format'
import type { DashboardMetrics } from '@/types'

const STATUS_COLORS: Record<string, string> = {
  completed: 'var(--primary)',
  confirmed: 'var(--lima-500)',
  pending: 'var(--chart-3)',
  cancelled: 'var(--chart-4)',
}

const STATUS_LABELS: Record<string, string> = {
  completed: 'Concluído',
  confirmed: 'Confirmado',
  pending: 'Pendente',
  cancelled: 'Cancelado',
}

function formatShortDate(dateStr: string): string {
  const parts = dateStr.split('-')
  return `${parts[2]}/${parts[1]}`
}

function SummaryCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border bg-card p-5">
      <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="mt-2 text-2xl font-bold tabular-nums text-foreground">{value}</p>
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

export default function DashboardPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  const [metrics, setMetrics] = useState<DashboardMetrics | null>(null)
  const [period, setPeriod] = useState(30)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!slug) return
    metricsService
      .dashboard(slug, period)
      .then((res) => setMetrics(res.data.data))
      .catch(() => setError('Erro ao carregar métricas'))
      .finally(() => setIsLoading(false))
  }, [slug, period])

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-foreground">Visão Geral</h1>
        <select
          value={period}
          onChange={(e) => {
            setIsLoading(true)
            setError(null)
            setMetrics(null)
            setPeriod(Number(e.target.value))
          }}
          className="rounded-md border border-border bg-card px-3 py-1.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value={7}>Últimos 7 dias</option>
          <option value={30}>Últimos 30 dias</option>
          <option value={90}>Últimos 90 dias</option>
        </select>
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
            <SummaryCard
              label="Agendamentos"
              value={String(metrics.summary.total_appointments)}
            />
            <SummaryCard
              label="Receita do mês"
              value={formatPrice(metrics.summary.revenue_this_month)}
            />
            <SummaryCard
              label="Hoje"
              value={String(metrics.summary.appointments_today)}
            />
            <SummaryCard
              label="Clientes únicos"
              value={String(metrics.summary.total_clients)}
            />
          </>
        ) : null}
      </div>

      {/* Charts row */}
      {!isLoading && metrics && (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {/* Revenue line chart */}
          <div className="rounded-lg border bg-card p-5">
            <h2 className="mb-4 text-sm font-semibold text-foreground">Receita Diária</h2>
            {metrics.revenue_by_day.length === 0 ? (
              <p className="py-12 text-center text-sm text-muted-foreground">Sem dados no período</p>
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <LineChart data={metrics.revenue_by_day}>
                  <XAxis
                    dataKey="date"
                    tickFormatter={formatShortDate}
                    tick={{ fontSize: 11 }}
                    tickLine={false}
                  />
                  <YAxis
                    tickFormatter={(v: number) => `R$${(v / 100).toFixed(0)}`}
                    tick={{ fontSize: 11 }}
                    tickLine={false}
                    axisLine={false}
                  />
                  <Tooltip
                    formatter={(v) => [formatPrice(Number(v)), 'Receita']}
                    labelFormatter={(label) => formatShortDate(String(label ?? ''))}
                  />
                  <Line
                    type="monotone"
                    dataKey="revenue"
                    stroke="var(--primary)"
                    strokeWidth={2}
                    dot={false}
                  />
                </LineChart>
              </ResponsiveContainer>
            )}
          </div>

          {/* Status pie chart */}
          <div className="rounded-lg border bg-card p-5">
            <h2 className="mb-4 text-sm font-semibold text-foreground">
              Agendamentos por Status
            </h2>
            {metrics.appointments_by_status.length === 0 ? (
              <p className="py-12 text-center text-sm text-muted-foreground">Sem agendamentos</p>
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <PieChart>
                  <Pie
                    data={metrics.appointments_by_status}
                    dataKey="count"
                    nameKey="status"
                    cx="50%"
                    cy="50%"
                    outerRadius={75}
                    label={(props) => {
                      const status = (props as { status?: string }).status ?? ''
                      const percent = (props as { percent?: number }).percent ?? 0
                      return `${STATUS_LABELS[status] ?? status} ${(percent * 100).toFixed(0)}%`
                    }}
                  >
                    {metrics.appointments_by_status.map((entry) => (
                      <Cell
                        key={entry.status}
                        fill={STATUS_COLORS[entry.status] ?? 'var(--muted-foreground)'}
                      />
                    ))}
                  </Pie>
                  <Tooltip formatter={(v) => [`${Number(v)} agendamentos`, '']} />
                </PieChart>
              </ResponsiveContainer>
            )}
          </div>
        </div>
      )}

      {/* Top services bar chart */}
      {!isLoading && metrics && metrics.top_services.length > 0 && (
        <div className="rounded-lg border bg-card p-5">
          <h2 className="mb-4 text-sm font-semibold text-foreground">Top Serviços</h2>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={metrics.top_services} layout="vertical">
              <XAxis type="number" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
              <YAxis
                type="category"
                dataKey="name"
                width={140}
                tick={{ fontSize: 11 }}
                tickLine={false}
                axisLine={false}
              />
              <Tooltip formatter={(v) => [`${Number(v)} agendamentos`, '']} />
              <Bar dataKey="count" fill="var(--primary)" radius={[0, 4, 4, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Professionals table */}
      {!isLoading && metrics && metrics.appointments_by_professional.length > 0 && (
        <div className="rounded-lg border bg-card">
          <div className="border-b p-5">
            <h2 className="text-sm font-semibold text-foreground">Profissionais</h2>
          </div>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted">
                <th className="px-5 py-3 text-left font-medium text-muted-foreground">
                  Profissional
                </th>
                <th className="px-5 py-3 text-right font-medium text-muted-foreground">
                  Agendamentos
                </th>
                <th className="px-5 py-3 text-right font-medium text-muted-foreground">Receita</th>
              </tr>
            </thead>
            <tbody>
              {metrics.appointments_by_professional.map((p) => (
                <tr key={p.name} className="border-b last:border-0 hover:bg-muted">
                  <td className="px-5 py-3 text-foreground">{p.name}</td>
                  <td className="px-5 py-3 text-right tabular-nums text-foreground">{p.count}</td>
                  <td className="px-5 py-3 text-right tabular-nums text-foreground">
                    {formatPrice(p.revenue)}
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
