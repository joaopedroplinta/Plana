import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Dashboard | Super Admin',
}

const stats = [
  { label: 'Salões ativos', value: '—' },
  { label: 'Agendamentos hoje', value: '—' },
  { label: 'Receita do mês', value: '—' },
  { label: 'Novos cadastros', value: '—' },
]

export default function SuperAdminDashboard() {
  return (
    <div className="mx-auto w-full max-w-7xl px-6 py-10">
      <h1 className="text-2xl font-bold text-gray-900">Dashboard da Plataforma</h1>
      <p className="mt-1 text-sm text-gray-500">
        Visão geral de todos os salões e atividade da plataforma.
      </p>

      <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <div
            key={stat.label}
            className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"
          >
            <p className="text-sm text-gray-500">{stat.label}</p>
            <p className="mt-2 text-3xl font-bold text-gray-900">{stat.value}</p>
          </div>
        ))}
      </div>

      <div className="mt-10 rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-sm">
        <p className="text-gray-400">Tabela de salões — em desenvolvimento</p>
      </div>
    </div>
  )
}
