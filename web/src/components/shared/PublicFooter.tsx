'use client'

import { useAuth } from '@/hooks/useAuth'
import { Footer } from '@/components/shared/Footer'

export function PublicFooter() {
  const { isAuthenticated } = useAuth()

  return <Footer variant="public" isAuthenticated={isAuthenticated} />
}
