import Link from 'next/link'
import { Logo } from '@/components/shared/Logo'

interface FooterProps {
  /**
   * `public` — rodapé completo para páginas públicas (landing, salão, booking).
   * `admin` — rodapé discreto para áreas administrativas (dashboard, super-admin).
   */
  variant?: 'public' | 'admin'
}

export function Footer({ variant = 'public' }: FooterProps) {
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
        <Logo className="text-sm text-foreground" markSize={18} />
        <p className="text-sm text-muted-foreground">
          &copy; {year} Plana. Todos os direitos reservados.
        </p>
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
      </div>
    </footer>
  )
}
