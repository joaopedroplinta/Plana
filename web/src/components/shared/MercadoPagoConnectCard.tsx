'use client'

import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { CheckCircle2, CreditCard, Link2, Loader2, Unplug } from 'lucide-react'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { mercadopagoService } from '@/services/mercadopago'
import { getSafeErrorMessage } from '@/lib/api-error'
import { formatDateTime } from '@/lib/format'
import type { MercadoPagoStatus } from '@/types/index'

interface MercadoPagoConnectCardProps {
  slug: string
}

export function MercadoPagoConnectCard({ slug }: MercadoPagoConnectCardProps) {
  const [status, setStatus] = useState<MercadoPagoStatus | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isConnecting, setIsConnecting] = useState(false)
  const [isDisconnecting, setIsDisconnecting] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)

  useEffect(() => {
    if (!slug) return
    let active = true
    mercadopagoService
      .getStatus(slug)
      .then((res) => {
        if (active) setStatus(res.data.data)
      })
      .catch((err) => {
        if (active) toast.error(getSafeErrorMessage(err, 'Erro ao carregar status do MercadoPago.'))
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })
    return () => {
      active = false
    }
  }, [slug])

  async function handleConnect() {
    setIsConnecting(true)
    try {
      const res = await mercadopagoService.getConnectUrl(slug)
      window.location.href = res.data.data.authorization_url
    } catch (err) {
      toast.error(getSafeErrorMessage(err, 'Não foi possível iniciar a conexão com o MercadoPago.'))
      setIsConnecting(false)
    }
  }

  async function handleDisconnect() {
    setIsDisconnecting(true)
    try {
      await mercadopagoService.disconnect(slug)
      setStatus({ connected: false, connected_at: null, mp_user_id: null })
      setConfirmOpen(false)
      toast.success('Conta MercadoPago desconectada.')
    } catch (err) {
      toast.error(getSafeErrorMessage(err, 'Não foi possível desconectar a conta.'))
    } finally {
      setIsDisconnecting(false)
    }
  }

  return (
    <Card className="max-w-2xl p-6">
      <div className="space-y-5">
        <div className="flex items-start gap-3">
          <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-muted/40">
            <CreditCard className="size-5 text-muted-foreground" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h3 className="text-base font-semibold text-foreground">Conta MercadoPago</h3>
              {!isLoading && status?.connected && (
                <Badge className="bg-green-600 text-white hover:bg-green-600">
                  <CheckCircle2 className="size-3" />
                  Conectado
                </Badge>
              )}
              {!isLoading && !status?.connected && (
                <Badge variant="outline">Não conectado</Badge>
              )}
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              Conecte sua conta MercadoPago para receber os pagamentos dos agendamentos diretamente
              na sua conta.
            </p>
          </div>
        </div>

        {isLoading ? (
          <div className="flex items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
            <Loader2 className="size-4 animate-spin" />
            Carregando status...
          </div>
        ) : status?.connected ? (
          <div className="space-y-4">
            <div className="rounded-lg border bg-muted/30 px-4 py-3 text-sm">
              {status.connected_at && (
                <p className="text-muted-foreground">
                  Conectado em{' '}
                  <span className="font-medium text-foreground">
                    {formatDateTime(status.connected_at)}
                  </span>
                </p>
              )}
              {status.mp_user_id && (
                <p className="mt-1 text-muted-foreground">
                  ID da conta:{' '}
                  <span className="font-mono text-xs text-foreground">{status.mp_user_id}</span>
                </p>
              )}
            </div>
            <Button
              type="button"
              variant="outline"
              onClick={() => setConfirmOpen(true)}
              disabled={isDisconnecting}
            >
              <Unplug className="size-4" />
              Desconectar
            </Button>
          </div>
        ) : (
          <Button type="button" onClick={handleConnect} disabled={isConnecting}>
            {isConnecting ? (
              <>
                <Loader2 className="size-4 animate-spin" />
                Redirecionando...
              </>
            ) : (
              <>
                <Link2 className="size-4" />
                Conectar MercadoPago
              </>
            )}
          </Button>
        )}
      </div>

      <AlertDialog
        open={confirmOpen}
        onOpenChange={(open) => !isDisconnecting && setConfirmOpen(open)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Desconectar MercadoPago</AlertDialogTitle>
            <AlertDialogDescription>
              Você deixará de receber os pagamentos dos agendamentos na sua conta MercadoPago até
              reconectar. Deseja continuar?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDisconnecting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-red-600 text-white hover:bg-red-700"
              onClick={(e) => {
                e.preventDefault()
                handleDisconnect()
              }}
              disabled={isDisconnecting}
            >
              {isDisconnecting ? (
                <>
                  <Loader2 className="size-4 animate-spin" />
                  Desconectando...
                </>
              ) : (
                'Desconectar'
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  )
}
