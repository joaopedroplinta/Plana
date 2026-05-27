import { Card } from '@/components/ui/card'
import { Scissors, Users, Calendar } from 'lucide-react'

interface MetricCardProps {
  title: string
  value: string | number
  icon: React.ReactNode
  description: string
}

function MetricCard({ title, value, icon, description }: MetricCardProps) {
  return (
    <Card className="p-6">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-500">{title}</p>
          <p className="mt-2 text-3xl font-bold text-gray-900">{value}</p>
          <p className="mt-1 text-xs text-gray-400">{description}</p>
        </div>
        <div className="rounded-full bg-indigo-50 p-3 text-indigo-600">{icon}</div>
      </div>
    </Card>
  )
}

export default function DashboardPage() {
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
          value={0}
          icon={<Scissors className="h-6 w-6" />}
          description="Serviços cadastrados"
        />
        <MetricCard
          title="Profissionais"
          value={0}
          icon={<Users className="h-6 w-6" />}
          description="Profissionais ativos"
        />
        <MetricCard
          title="Agendamentos Hoje"
          value={0}
          icon={<Calendar className="h-6 w-6" />}
          description="Agendamentos do dia"
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
