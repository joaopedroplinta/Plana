'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/hooks/useAuth'
import { resolveHomePath } from '@/lib/auth-routes'
import { ThemeToggle } from '@/components/theme-toggle'

export function PublicNav() {
  const router = useRouter()
  const { isAuthenticated, user, logout } = useAuth()

  async function handleLogout() {
    await logout()
    router.push('/')
  }

  return (
    <nav className="flex items-center gap-6">
      <Link
        href="/#planos"
        className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
      >
        Preços
      </Link>
      {isAuthenticated ? (
        <>
          <Link
            href={resolveHomePath(user)}
            className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            Minha conta
          </Link>
          <button
            type="button"
            onClick={handleLogout}
            className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            Sair
          </button>
        </>
      ) : (
        <>
          <Link
            href="/register"
            className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            Criar conta
          </Link>
          <Link
            href="/login"
            className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            Entrar
          </Link>
        </>
      )}
      <ThemeToggle />
    </nav>
  )
}
