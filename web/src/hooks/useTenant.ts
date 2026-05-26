'use client'

import { useState, useEffect } from 'react'
import type { Tenant } from '@/types/index'

interface UseTenantReturn {
  tenant: Tenant | null
  isLoading: boolean
  error: string | null
}

const MOCK_TENANT: Tenant = {
  id: 'mock-tenant-id',
  name: 'Salão Exemplo',
  slug: 'salao-exemplo',
  plan: 'pro',
  active: true,
}

export function useTenant(slug: string): UseTenantReturn {
  const [tenant, setTenant] = useState<Tenant | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!slug) {
      setIsLoading(false)
      setError('Slug não informado')
      return
    }

    // TODO: substituir mock pela chamada real quando a rota existir:
    // tenantsService.show(slug).then(r => setTenant(r.data.data)).catch(...)
    const timer = setTimeout(() => {
      setTenant({ ...MOCK_TENANT, slug, name: `Salão ${slug}` })
      setIsLoading(false)
    }, 0)

    return () => clearTimeout(timer)
  }, [slug])

  return { tenant, isLoading, error }
}
