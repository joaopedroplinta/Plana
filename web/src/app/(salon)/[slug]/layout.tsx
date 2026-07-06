'use client'

import { use } from 'react'
import { usePathname } from 'next/navigation'
import { useAuth } from '@/hooks/useAuth'
import { useTenant } from '@/hooks/useTenant'

interface SalonLayoutProps {
  children: React.ReactNode
  params: Promise<{ slug: string }>
}

export default function SalonLayout({ children, params }: SalonLayoutProps) {
  const { slug } = use(params)
  const pathname = usePathname()
  const { tenant, isLoading } = useTenant(slug)
  const { user, isAuthenticated } = useAuth()

  const displayName = isLoading ? '...' : (tenant?.name ?? slug)
  const isStaff = user?.roles?.some((r) => ['salon_owner', 'salon_staff'].includes(r.name)) ?? false
  const isDashboard = pathname?.includes('/dashboard')

  // O dashboard tem layout próprio com sidebar — não duplicar o header.
  if (isDashboard) {
    return <>{children}</>
  }

  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b bg-white px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <a href={`/${slug}`} className="text-lg font-bold text-gray-900">
            {displayName}
          </a>
          <nav className="flex items-center gap-4">
            {isAuthenticated ? (
              <a
                href={`/${slug}/minha-conta`}
                className="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
              >
                Meus agendamentos
              </a>
            ) : (
              <a
                href={`/login?redirect=${encodeURIComponent(`/${slug}`)}`}
                className="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
              >
                Entrar
              </a>
            )}
            {isStaff && (
              <a
                href={`/${slug}/dashboard`}
                className="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
              >
                Dashboard
              </a>
            )}
            <a
              href={`/${slug}/booking`}
              className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 transition-colors"
            >
              Agendar
            </a>
          </nav>
        </div>
      </header>
      <main className="flex flex-1 flex-col">{children}</main>
    </div>
  )
}
