'use client'

import { useCallback, useEffect, useState } from 'react'
import { useParams, useRouter } from 'next/navigation'
import { PackageX } from 'lucide-react'
import { MinhaContaTabs } from '@/components/shared/MinhaContaTabs'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { useAuth } from '@/hooks/useAuth'
import { packagePurchasesService } from '@/services/packagePurchases'
import type { PackagePurchase } from '@/types/index'
import { formatDate, formatPrice } from '@/lib/format'

const STATUS_BADGE: Record<PackagePurchase['status'], { label: string; className: string }> = {
  pending: { label: 'Aguardando pagamento', className: 'bg-amber-100 text-amber-800' },
  active: { label: 'Ativo', className: 'bg-green-100 text-green-800' },
  expired: { label: 'Expirado', className: 'bg-gray-100 text-gray-500' },
  cancelled: { label: 'Cancelado', className: 'bg-red-100 text-red-700' },
}

export default function MeusPacotesPage() {
  const params = useParams()
  const router = useRouter()
  const slug = typeof params.slug === 'string' ? params.slug : ''
  const { isLoading: authLoading, isAuthenticated } = useAuth()

  const [purchases, setPurchases] = useState<PackagePurchase[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!authLoading && !isAuthenticated) {
      router.replace(`/login?redirect=/${slug}/minha-conta/pacotes`)
    }
  }, [authLoading, isAuthenticated, router, slug])

  const loadPurchases = useCallback(() => {
    if (!slug) return
    packagePurchasesService
      .list(slug)
      .then((res) => setPurchases(res.data.data))
      .catch(() => setError('Erro ao carregar seus pacotes.'))
      .finally(() => setIsLoading(false))
  }, [slug])

  useEffect(() => {
    if (isAuthenticated) loadPurchases()
  }, [isAuthenticated, loadPurchases])

  if (authLoading || (!isAuthenticated && !error)) {
    return (
      <div className="flex min-h-[60vh] items-center justify-center">
        <p className="text-sm text-gray-400 animate-pulse">Carregando...</p>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <MinhaContaTabs slug={slug} />

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Meus pacotes</h1>
        <p className="mt-1 text-sm text-gray-500">Pacotes de sessões comprados neste salão</p>
      </div>

      {error && (
        <div className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{error}</div>
      )}

      {isLoading ? (
        <p className="py-16 text-center text-sm text-gray-400 animate-pulse">
          Carregando pacotes...
        </p>
      ) : purchases.length === 0 ? (
        <div className="flex flex-col items-center rounded-xl border bg-white py-16 text-center">
          <PackageX className="h-10 w-10 text-gray-300" />
          <p className="mt-4 text-sm text-gray-500">Você ainda não comprou nenhum pacote.</p>
          <a
            href={`/${slug}`}
            className="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 transition-colors"
          >
            Ver pacotes disponíveis
          </a>
        </div>
      ) : (
        <div className="space-y-3">
          {purchases.map((purchase) => {
            const badge = STATUS_BADGE[purchase.status]
            return (
              <Card key={purchase.id} className="p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0">
                    <p className="font-semibold text-gray-900">{purchase.service_package.name}</p>
                    <p className="mt-0.5 text-sm text-gray-500">
                      {purchase.sessions_remaining} de {purchase.sessions_total} sessões restantes
                    </p>
                    {purchase.expires_at && (
                      <p className="mt-1 text-xs text-gray-400">
                        Válido até {formatDate(purchase.expires_at)}
                      </p>
                    )}
                  </div>
                  <div className="flex shrink-0 flex-col items-end gap-2">
                    <Badge variant="secondary" className={`text-xs ${badge.className}`}>
                      {badge.label}
                    </Badge>
                    <p className="text-sm font-bold text-indigo-600">
                      {formatPrice(purchase.price_paid)}
                    </p>
                  </div>
                </div>
              </Card>
            )
          })}
        </div>
      )}
    </div>
  )
}
