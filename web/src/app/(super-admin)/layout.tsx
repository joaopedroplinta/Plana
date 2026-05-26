import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Super Admin | Agendei',
}

export default function SuperAdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col bg-gray-50">
      <header className="border-b bg-gray-900 px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <div className="flex items-center gap-3">
            <span className="text-lg font-bold text-white">Agendei</span>
            <span className="rounded bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">
              ADMIN
            </span>
          </div>
          <nav className="flex items-center gap-4">
            <a
              href="/super-admin"
              className="text-sm font-medium text-gray-300 hover:text-white transition-colors"
            >
              Dashboard
            </a>
          </nav>
        </div>
      </header>
      <main className="flex flex-1 flex-col">{children}</main>
    </div>
  )
}
