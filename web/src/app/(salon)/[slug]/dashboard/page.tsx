'use client'

import { useState, useEffect } from 'react'
import { useParams } from 'next/navigation'
import { Card } from '@/components/ui/card'
import { Scissors, Users, Calendar } from 'lucide-react'
import { servicesService } from '@/services/services'
import { professionalsService } from '@/services/professionals'
import { appointmentsService } from '@/services/appointments'

interface MetricCardProps {
  title: string
  value: string | number
  icon: React.ReactNode
  description: string
  isLoading?: boolean
}

function MetricCard({ title, value, icon, description, isLoading = false }: MetricCardProps) {
  return (
    <Card className="p-6">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-500">{title}</p>
          {isLoading ? (
            <div className="mt-2 h-9 w-16 animate-pulse rounded-md bg-gray-100" />
          ) : (
            <p className="mt-2 text-3xl font-bold text-gray-900">{value}</p>
          )}
          <p className="mt-1 text-xs text-gray-400">{description}</p>
        </div>
        <div className="rounded-full bg-indigo-50 p-3 text-indigo-600">{icon}</div>
      </div>
    </Card>
  )
}

interface Metrics {
  totalServices: number
  totalProfessionals: number
  appointmentsToday: number
}

export default function DashboardPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  const [metrics, setMetrics] = useState<Metrics>({
    totalServices: 0,
    totalProfessionals: 0,
    appointmentsToday: 0,
  })
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    if (!slug) return

    const today = new Date().toISOString().split('T')[0]

    Promise.all([
      servicesService.list(slug),
      professionalsService.list(slug),
      appointmentsService.list(slug, { date: today, status: 'pending,confirmed' }),
    ])
      .then(([svcRes, proRes, apptRes]) => {
        setMetrics({
          totalServices: svcRes.data.meta.total,
          totalProfessionals: proRes.data.data.length,
          appointmentsToday: apptRes.data.meta.total,
        })
      })
      .catch(() => {
        // Silently fail — metrics remain at 0
      })
      .finally(() => {
        setIsLoading(false)
      })
  }, [slug])

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Visão Geral</h1>
        <p className="mt-1 text-sm text-gray-500">
          Resumo das informações do seu salão
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <MetricCard
          title="Total de Serviços"
          value={metrics.totalServices}
          icon={<Scissors className="h-6 w-6" />}
          description="Serviços cadastrados"
          isLoading={isLoading}
        />
        <MetricCard
          title="Profissionais"
          value={metrics.totalProfessionals}
          icon={<Users className="h-6 w-6" />}
          description="Profissionais ativos"
          isLoading={isLoading}
        />
        <MetricCard
          title="Agendamentos Hoje"
          value={metrics.appointmentsToday}
          icon={<Calendar className="h-6 w-6" />}
          description="Pendentes e confirmados"
          isLoading={isLoading}
        />
      </div>

      <div className="rounded-xl border bg-white p-6">
        <h2 className="text-base font-semibold text-gray-900">Próximas funcionalidades</h2>
        <ul className="mt-3 space-y-2 text-sm text-gray-500">
          <li className="flex items-center gap-2">
            <span className="h-1.5 w-1.5 rounded-full bg-indigo-400" />
            Calendário de agendamentos
          </li>
          <li className="flex items-center gap-2">
            <span className="h-1.5 w-1.5 rounded-full bg-indigo-400" />
            Relatórios financeiros
          </li>
          <li className="flex items-center gap-2">
            <span className="h-1.5 w-1.5 rounded-full bg-indigo-400" />
            Integração com pagamentos PIX
          </li>
        </ul>
      </div>
    </div>
  )
}
