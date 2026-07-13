'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'

interface MinhaContaTabsProps {
  slug: string
}

export function MinhaContaTabs({ slug }: MinhaContaTabsProps) {
  const pathname = usePathname()
  const isPacotes = pathname?.endsWith('/pacotes') ?? false

  const tabs = [
    { href: `/${slug}/minha-conta`, label: 'Agendamentos', active: !isPacotes },
    { href: `/${slug}/minha-conta/pacotes`, label: 'Pacotes', active: isPacotes },
  ]

  return (
    <div className="mb-6 flex gap-1 border-b">
      {tabs.map((tab) => (
        <Link
          key={tab.href}
          href={tab.href}
          className={`border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
            tab.active
              ? 'border-primary text-primary'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          {tab.label}
        </Link>
      ))}
    </div>
  )
}
