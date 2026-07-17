import Link from 'next/link'
import { HomeLink } from '@/components/shared/HomeLink'

interface FooterProps {
  /**
   * `public` — rodapé completo para páginas públicas (landing, salão, booking).
   * `admin` — rodapé discreto para áreas administrativas (dashboard, super-admin).
   */
  variant?: 'public' | 'admin'
  /**
   * Quando `true`, oculta os links "Entrar / Criar conta" — o usuário já está
   * logado e essas ações não fazem sentido (o header cuida de sair/conta).
   */
  isAuthenticated?: boolean
}

export function Footer({ variant = 'public', isAuthenticated = false }: FooterProps) {
  const year = new Date().getFullYear()

  if (variant === 'admin') {
    return (
      <footer className="border-t border-border px-6 py-3">
        <p className="text-xs text-muted-foreground">
          &copy; {year} Plana. Todos os direitos reservados.
        </p>
      </footer>
    )
  }

  return (
    <footer className="border-t border-border bg-background px-6 py-8">
      <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 sm:flex-row">
        <HomeLink className="text-sm text-foreground" markSize={18} />
        <p className="text-sm text-muted-foreground">
          &copy; {year} Plana. Todos os direitos reservados.
        </p>
        {!isAuthenticated && (
          <nav className="flex items-center gap-4">
            <Link
              href="/login"
              className="text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              Entrar
            </Link>
            <Link
              href="/register"
              className="text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              Criar conta
            </Link>
          </nav>
        )}
      </div>
    </footer>
  )
}
