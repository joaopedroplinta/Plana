'use client'

import { useEffect, useState } from 'react'
import { useParams, useRouter, usePathname } from 'next/navigation'
import Link from 'next/link'
import { useAuth } from '@/hooks/useAuth'
import { useTenant } from '@/hooks/useTenant'
import { Button } from '@/components/ui/button'
import { ThemeToggle } from '@/components/theme-toggle'
import { Footer } from '@/components/shared/Footer'
import {
  LayoutDashboard,
  Scissors,
  Package,
  Users,
  UserPlus,
  Calendar,
  CreditCard,
  LogOut,
  Menu,
  Store,
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
  const { user, isLoading: isAuthLoading, isAuthenticated, logout } = useAuth()
  const { tenant, isLoading: isTenantLoading } = useTenant(slug)
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const isLoading = isAuthLoading || isTenantLoading

  useEffect(() => {
    if (!isAuthLoading && !isAuthenticated) {
      router.replace('/login')
    }
  }, [isAuthLoading, isAuthenticated, router])

  // Role guard: apenas owner e staff do tenant atual acessam o dashboard
  useEffect(() => {
    if (!isLoading && isAuthenticated && tenant) {
      const role = tenant.current_tenant_role
      const hasAccess = role === 'owner' || role === 'staff'
      if (!hasAccess) {
        router.push(`/${slug}`)
      }
    }
  }, [isLoading, isAuthenticated, tenant, router, slug])

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
      label: 'Equipe',
      href: `/${slug}/dashboard/team`,
      icon: <UserPlus className="h-4 w-4" />,
    },
    {
      label: 'Agenda',
      href: `/${slug}/dashboard/schedule`,
      icon: <Calendar className="h-4 w-4" />,
    },
    {
      label: 'Meu negócio',
      href: `/${slug}/dashboard/settings`,
      icon: <Store className="h-4 w-4" />,
    },
    {
      label: 'Planos',
      href: `/${slug}/dashboard/planos`,
      icon: <CreditCard className="h-4 w-4" />,
    },
  ]

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

  const salonName = user?.tenant?.name ?? slug

  return (
    <div className="flex min-h-screen bg-muted">
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
          'fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r border-border bg-card transition-transform duration-200 lg:static lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        ].join(' ')}
      >
        {/* Sidebar header */}
        <div className="flex h-16 items-center justify-between border-b border-border px-5">
          <div className="flex items-center gap-2 min-w-0">
            <span className="text-base font-bold text-foreground truncate">{salonName}</span>
            {user?.tenant?.plan && <PlanBadge plan={user.tenant.plan} />}
          </div>
          <button
            className="rounded p-1 text-muted-foreground hover:text-foreground lg:hidden"
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
                    ? 'bg-secondary text-secondary-foreground dark:bg-primary/15 dark:text-primary'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                ].join(' ')}
              >
                {item.icon}
                {item.label}
              </Link>
            )
          })}
        </nav>

        {/* Logout */}
        <div className="border-t border-border p-4">
          <Button
            variant="ghost"
            className="w-full justify-start gap-3 text-sm text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
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
              <span className="hidden text-xs text-muted-foreground sm:block">{salonName}</span>
              <ThemeToggle />
            </div>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 overflow-auto p-6">{children}</main>
        <Footer variant="admin" />
      </div>
    </div>
  )
}

function PlanBadge({ plan }: { plan: 'starter' | 'pro' | 'enterprise' }) {
  const styles: Record<string, string> = {
    starter: 'bg-muted text-muted-foreground',
    pro: 'bg-secondary text-secondary-foreground dark:bg-primary/15 dark:text-primary',
    enterprise: 'bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300',
  }
  return (
    <span
      className={`shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ${styles[plan]}`}
    >
      {plan}
    </span>
  )
}
