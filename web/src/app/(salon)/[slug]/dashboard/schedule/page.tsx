import { Calendar } from 'lucide-react'

export default function SchedulePage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Agenda</h1>
        <p className="mt-1 text-sm text-gray-500">
          Visualize e gerencie os agendamentos do seu salão
        </p>
      </div>

      <div className="flex flex-col items-center justify-center rounded-xl border bg-white py-24 text-center">
        <div className="rounded-full bg-indigo-50 p-4">
          <Calendar className="h-10 w-10 text-indigo-400" />
        </div>
        <h2 className="mt-4 text-lg font-semibold text-gray-700">
          Configuração de horários — em breve
        </h2>
        <p className="mt-2 max-w-sm text-sm text-gray-400">
          O calendário completo de agendamentos estará disponível em breve.
          Aguarde as próximas atualizações da plataforma.
        </p>
      </div>
    </div>
  )
}
