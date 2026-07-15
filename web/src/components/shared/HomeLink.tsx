'use client'

import Link from 'next/link'
import { useAuth } from '@/hooks/useAuth'
import { resolveHomePath } from '@/lib/auth-routes'
import { Logo, type LogoProps } from '@/components/shared/Logo'

export function HomeLink(props: LogoProps) {
  const { user } = useAuth()

  return (
    <Link href={resolveHomePath(user)} className="inline-flex items-center">
      <Logo {...props} />
    </Link>
  )
}
