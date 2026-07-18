'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { Button } from '@/components/ui/button'

const STORAGE_KEY = 'cookie-consent'

/**
 * Começa sempre escondido (mesmo valor no server e no client) e só decide se
 * mostra depois de montar — evita divergir a renderização inicial servidor
 * x cliente por causa do localStorage, que só existe no browser.
 */
export function CookieConsent() {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    Promise.resolve().then(() => {
      if (localStorage.getItem(STORAGE_KEY) !== 'accepted') {
        setVisible(true)
      }
    })
  }, [])

  if (!visible) return null

  function handleAccept() {
    localStorage.setItem(STORAGE_KEY, 'accepted')
    setVisible(false)
  }

  return (
    <div className="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background px-6 py-4 shadow-lg">
      <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 sm:flex-row">
        <p className="text-sm text-muted-foreground">
          Usamos cookies essenciais para manter você conectado e sua sessão funcionando. Saiba
          mais na nossa{' '}
          <Link href="/cookies" className="underline hover:text-foreground">
            Política de Cookies
          </Link>
          .
        </p>
        <Button size="sm" onClick={handleAccept} className="shrink-0">
          Entendi
        </Button>
      </div>
    </div>
  )
}
