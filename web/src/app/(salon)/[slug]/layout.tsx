'use client'

import { useTenant } from '@/hooks/useTenant'
import { use } from 'react'

interface SalonLayoutProps {
  children: React.ReactNode
  params: Promise<{ slug: string }>
}

export default function SalonLayout({ children, params }: SalonLayoutProps) {
  const { slug } = use(params)
  const { tenant, isLoading } = useTenant(slug)

  const displayName = isLoading ? '...' : (tenant?.name ?? slug)

  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b bg-white px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <span className="text-lg font-bold text-gray-900">{displayName}</span>
          <nav className="flex items-center gap-4">
            <a
              href={`/${slug}`}
              className="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
            >
              Início
            </a>
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
