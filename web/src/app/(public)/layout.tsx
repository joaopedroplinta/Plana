import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Sistema de Agendamentos',
  description: 'Plataforma de agendamentos para salões de beleza',
}

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b bg-white px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <span className="text-lg font-bold text-gray-900">Agendei</span>
          <nav className="flex items-center gap-6">
            <a
              href="/login"
              className="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
            >
              Entrar
            </a>
          </nav>
        </div>
      </header>
      <main className="flex flex-1 flex-col">{children}</main>
    </div>
  )
}
