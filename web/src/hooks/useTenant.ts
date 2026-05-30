'use client'

import { useState, useEffect } from 'react'
import type { Tenant } from '@/types/index'
import { tenantsService } from '@/services/tenants'

interface UseTenantReturn {
  tenant: Tenant | null
  isLoading: boolean
  error: string | null
}

export function useTenant(slug: string): UseTenantReturn {
  const [tenant, setTenant] = useState<Tenant | null>(null)
  const [isLoading, setIsLoading] = useState(!!slug)
  const [error, setError] = useState<string | null>(slug ? null : 'Slug nao informado')

  useEffect(() => {
    if (!slug) return

    setIsLoading(true)
    setError(null)

    tenantsService
      .show(slug)
      .then((res) => setTenant(res.data.data))
      .catch(() => setError('Salao nao encontrado'))
      .finally(() => setIsLoading(false))
  }, [slug])

  return { tenant, isLoading, error }
}
