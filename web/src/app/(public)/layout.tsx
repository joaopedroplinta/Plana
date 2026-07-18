import type { Metadata } from 'next'
import { HomeLink } from '@/components/shared/HomeLink'
import { PublicNav } from '@/components/shared/PublicNav'
import { PublicFooter } from '@/components/shared/PublicFooter'

export const metadata: Metadata = {
  title: 'Plana',
  description: 'Plataforma de agendamentos para salões de beleza',
}

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b border-border bg-background px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between">
          <HomeLink className="text-lg text-foreground" markSize={24} />
          <PublicNav />
        </div>
      </header>
      <main className="flex flex-1 flex-col">{children}</main>
      <PublicFooter />
    </div>
  )
}
