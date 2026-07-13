import Link from 'next/link'
import type { Metadata } from 'next'
import { ThemeToggle } from '@/components/theme-toggle'
import { Footer } from '@/components/shared/Footer'
import { Logo } from '@/components/shared/Logo'

export const metadata: Metadata = {
  title: 'Plana',
  description: 'Plataforma de agendamentos para salões de beleza',
}

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b border-border bg-background px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <Logo className="text-lg text-foreground" markSize={24} />
          <nav className="flex items-center gap-6">
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
            <ThemeToggle />
          </nav>
        </div>
      </header>
      <main className="flex flex-1 flex-col">{children}</main>
      <Footer variant="public" />
    </div>
  )
}
