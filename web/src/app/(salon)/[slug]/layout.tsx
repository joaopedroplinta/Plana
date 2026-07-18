'use client'

import { use } from 'react'
import { usePathname, useRouter } from 'next/navigation'
import { useAuth } from '@/hooks/useAuth'
import { useTenant } from '@/hooks/useTenant'
import { ThemeToggle } from '@/components/theme-toggle'
import { Footer } from '@/components/shared/Footer'

interface SalonLayoutProps {
  children: React.ReactNode
  params: Promise<{ slug: string }>
}

export default function SalonLayout({ children, params }: SalonLayoutProps) {
  const { slug } = use(params)
  const pathname = usePathname()
  const router = useRouter()
  const { tenant, isLoading } = useTenant(slug)
  const { isAuthenticated, logout } = useAuth()

  async function handleLogout() {
    await logout()
    router.push(`/${slug}`)
  }

  const displayName = isLoading ? '...' : (tenant?.name ?? slug)
  const isStaff =
    tenant?.current_tenant_role === 'owner' || tenant?.current_tenant_role === 'staff'
  const isDashboard = pathname?.includes('/dashboard')

  // O dashboard tem layout próprio com sidebar — não duplicar o header.
  if (isDashboard) {
    return <>{children}</>
  }

  // Cor da marca do salão sobrescreve --primary (Tailwind v4) em toda a área
  // pública (header, conteúdo e footer). Sem cor definida, mantém o tema padrão.
  const brandStyle = tenant?.brand_color
    ? ({ '--primary': tenant.brand_color } as React.CSSProperties)
    : undefined

  return (
    <div className="flex min-h-screen flex-col" style={brandStyle}>
      <header className="border-b border-border bg-background px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <a href={`/${slug}`} className="text-lg font-bold text-foreground">
            {displayName}
          </a>
          <nav className="flex items-center gap-4">
            {isAuthenticated ? (
              <>
                <a
                  href={`/${slug}/minha-conta`}
                  className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                >
                  Meus agendamentos
                </a>
                <a
                  href={`/${slug}/minha-conta/perfil`}
                  className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                >
                  Perfil
                </a>
                <button
                  type="button"
                  onClick={handleLogout}
                  className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                >
                  Sair
                </button>
              </>
            ) : (
              <a
                href={`/login?redirect=${encodeURIComponent(`/${slug}`)}`}
                className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
              >
                Entrar
              </a>
            )}
            {isStaff && (
              <a
                href={`/${slug}/dashboard`}
                className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
              >
                Dashboard
              </a>
            )}
            <a
              href={`/${slug}/booking`}
              className="rounded-full bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
            >
              Agendar
            </a>
            <ThemeToggle />
          </nav>
        </div>
      </header>
      <main className="flex flex-1 flex-col">{children}</main>
      <Footer variant="public" isAuthenticated={isAuthenticated} />
    </div>
  )
}
