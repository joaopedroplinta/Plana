'use client'

import { useEffect, useState } from 'react'
import { useParams, useRouter, usePathname } from 'next/navigation'
import Link from 'next/link'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
import {
  LayoutDashboard,
  Scissors,
  Package,
  Users,
  Calendar,
  LogOut,
  Menu,
  X,
} from 'lucide-react'

interface NavItem {
  label: string
  href: string
  icon: React.ReactNode
}

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''
  const router = useRouter()
  const pathname = usePathname()
  const { user, isLoading, isAuthenticated, logout } = useAuth()
  const [sidebarOpen, setSidebarOpen] = useState(false)

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      router.replace('/login')
    }
  }, [isLoading, isAuthenticated, router])

  // Role guard: apenas salon_owner e salon_staff acessam o dashboard
  useEffect(() => {
    if (!isLoading && isAuthenticated && user) {
      const hasAccess = user.roles?.some((r) =>
        ['salon_owner', 'salon_staff'].includes(r.name),
      )
      if (!hasAccess) {
        router.push(`/${slug}`)
      }
    }
  }, [isLoading, isAuthenticated, user, router, slug])

  const navItems: NavItem[] = [
    {
      label: 'Visão Geral',
      href: `/${slug}/dashboard`,
      icon: <LayoutDashboard className="h-4 w-4" />,
    },
    {
      label: 'Serviços',
      href: `/${slug}/dashboard/services`,
      icon: <Scissors className="h-4 w-4" />,
    },
    {
      label: 'Pacotes',
      href: `/${slug}/dashboard/packages`,
      icon: <Package className="h-4 w-4" />,
    },
    {
      label: 'Profissionais',
      href: `/${slug}/dashboard/professionals`,
      icon: <Users className="h-4 w-4" />,
    },
    {
      label: 'Agenda',
      href: `/${slug}/dashboard/schedule`,
      icon: <Calendar className="h-4 w-4" />,
    },
  ]

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-50">
        <p className="text-sm text-gray-500">Carregando...</p>
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

  const salonName = user?.tenant?.name ?? slug

  return (
    <div className="flex min-h-screen bg-gray-50">
      {/* Mobile overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-20 bg-black/40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={[
          'fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r bg-white transition-transform duration-200 lg:static lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        ].join(' ')}
      >
        {/* Sidebar header */}
        <div className="flex h-16 items-center justify-between border-b px-5">
          <span className="text-base font-bold text-gray-900 truncate">{salonName}</span>
          <button
            className="rounded p-1 text-gray-400 hover:text-gray-600 lg:hidden"
            onClick={() => setSidebarOpen(false)}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Nav items */}
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
          {navItems.map((item) => {
            const isActive =
              item.href === `/${slug}/dashboard`
                ? pathname === `/${slug}/dashboard`
                : pathname.startsWith(item.href)
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setSidebarOpen(false)}
                className={[
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
                ].join(' ')}
              >
                {item.icon}
                {item.label}
              </Link>
            )
          })}
        </nav>

        {/* Logout */}
        <div className="border-t p-4">
          <Button
            variant="ghost"
            className="w-full justify-start gap-3 text-sm text-gray-600 hover:text-red-600"
            onClick={handleLogout}
          >
            <LogOut className="h-4 w-4" />
            Sair
          </Button>
        </div>
      </aside>

      {/* Main area */}
      <div className="flex flex-1 flex-col min-w-0">
        {/* Top header */}
        <header className="flex h-16 items-center gap-4 border-b bg-white px-6">
          <button
            className="rounded p-1.5 text-gray-400 hover:text-gray-600 lg:hidden"
            onClick={() => setSidebarOpen(true)}
          >
            <Menu className="h-5 w-5" />
          </button>
          <div className="flex flex-1 items-center justify-between">
            <span className="text-sm text-gray-500">
              Bem-vindo, <span className="font-medium text-gray-900">{user?.name}</span>
            </span>
            <span className="hidden text-xs text-gray-400 sm:block">{salonName}</span>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 overflow-auto p-6">{children}</main>
      </div>
    </div>
  )
}
