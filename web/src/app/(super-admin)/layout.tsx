'use client'

import { useEffect, useState } from 'react'
import { useRouter, usePathname } from 'next/navigation'
import Link from 'next/link'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
import { ThemeToggle } from '@/components/theme-toggle'
import { Footer } from '@/components/shared/Footer'
import { LayoutDashboard, Building2, LogOut, Menu, X } from 'lucide-react'

interface NavItem {
  label: string
  href: string
  icon: React.ReactNode
}

const navItems: NavItem[] = [
  { label: 'Dashboard', href: '/super-admin', icon: <LayoutDashboard className="h-4 w-4" /> },
  { label: 'Salões', href: '/super-admin/tenants', icon: <Building2 className="h-4 w-4" /> },
]

export default function SuperAdminLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter()
  const pathname = usePathname()
  const { user, isLoading, isAuthenticated, logout } = useAuth()
  const [sidebarOpen, setSidebarOpen] = useState(false)

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      router.replace('/login')
    }
  }, [isLoading, isAuthenticated, router])

  useEffect(() => {
    if (!isLoading && isAuthenticated && user) {
      const hasAccess = user.roles?.includes('super_admin') ?? false
      if (!hasAccess) {
        router.push('/')
      }
    }
  }, [isLoading, isAuthenticated, user, router])

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-muted">
        <p className="text-sm text-muted-foreground">Carregando...</p>
      </div>
    )
  }

  if (!isAuthenticated) {
    return null
  }

  async function handleLogout() {
    await logout()
    router.replace('/login')
  }

  return (
    <div className="flex min-h-screen bg-muted">
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-20 bg-black/40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={[
          'fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r bg-gray-900 transition-transform duration-200 lg:static lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        ].join(' ')}
      >
        {/* Header */}
        <div className="flex h-16 items-center justify-between border-b border-gray-700 px-5">
          <div className="flex items-center gap-2">
            <span className="text-base font-bold text-white">Agendei</span>
            <span className="rounded bg-indigo-600 px-1.5 py-0.5 text-xs font-semibold text-white">
              ADMIN
            </span>
          </div>
          <button
            className="rounded p-1 text-gray-400 hover:text-gray-200 lg:hidden"
            onClick={() => setSidebarOpen(false)}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Nav */}
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
          {navItems.map((item) => {
            const isActive =
              item.href === '/super-admin'
                ? pathname === '/super-admin'
                : pathname.startsWith(item.href)
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setSidebarOpen(false)}
                className={[
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-400 hover:bg-gray-800 hover:text-white',
                ].join(' ')}
              >
                {item.icon}
                {item.label}
              </Link>
            )
          })}
        </nav>

        {/* Logout */}
        <div className="border-t border-gray-700 p-4">
          <Button
            variant="ghost"
            className="w-full justify-start gap-3 text-sm text-gray-400 hover:text-red-400"
            onClick={handleLogout}
          >
            <LogOut className="h-4 w-4" />
            Sair
          </Button>
        </div>
      </aside>

      {/* Main */}
      <div className="flex flex-1 flex-col min-w-0">
        <header className="flex h-16 items-center gap-4 border-b border-border bg-card px-6">
          <button
            className="rounded p-1.5 text-muted-foreground hover:text-foreground lg:hidden"
            onClick={() => setSidebarOpen(true)}
          >
            <Menu className="h-5 w-5" />
          </button>
          <div className="flex flex-1 items-center justify-between">
            <span className="text-sm text-muted-foreground">
              Bem-vindo, <span className="font-medium text-foreground">{user?.name}</span>
            </span>
            <div className="flex items-center gap-3">
              <span className="hidden text-xs text-muted-foreground sm:block">
                Plataforma Agendei
              </span>
              <ThemeToggle />
            </div>
          </div>
        </header>

        <main className="flex-1 overflow-auto p-6">{children}</main>
        <Footer variant="admin" />
      </div>
    </div>
  )
}
