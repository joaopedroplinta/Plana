'use client'

import { useEffect, useState } from 'react'
import { useParams } from 'next/navigation'
import QRCode from 'qrcode'
import { toast } from 'sonner'
import { Check, Copy, Download, ExternalLink, QrCode } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface BookingLinkCardProps {
  slug?: string
}

export function BookingLinkCard({ slug: slugProp }: BookingLinkCardProps) {
  const params = useParams()
  const paramSlug = typeof params.slug === 'string' ? params.slug : ''
  const slug = slugProp ?? paramSlug

  const [url, setUrl] = useState('')
  const [qrDataUrl, setQrDataUrl] = useState('')
  const [copied, setCopied] = useState(false)

  useEffect(() => {
    if (!slug || typeof window === 'undefined') return
    const bookingUrl = `${window.location.origin}/${slug}/booking`
    QRCode.toDataURL(bookingUrl, {
      width: 320,
      margin: 2,
      errorCorrectionLevel: 'M',
    })
      .then((dataUrl) => {
        setUrl(bookingUrl)
        setQrDataUrl(dataUrl)
      })
      .catch(() => {
        setUrl(bookingUrl)
        setQrDataUrl('')
      })
  }, [slug])

  async function handleCopy() {
    if (!url) return
    if (!navigator.clipboard?.writeText) {
      toast.error('Copiar não é suportado neste navegador. Selecione o link manualmente.')
      return
    }
    try {
      await navigator.clipboard.writeText(url)
      setCopied(true)
      toast.success('Link copiado!')
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      toast.error('Não foi possível copiar o link.')
    }
  }

  function handleOpen() {
    if (!url) return
    window.open(url, '_blank', 'noopener,noreferrer')
  }

  function handleDownloadQr() {
    if (!qrDataUrl) return
    const link = document.createElement('a')
    link.href = qrDataUrl
    link.download = `agendamento-${slug}.png`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <Card className="max-w-2xl p-6">
      <div className="space-y-5">
        <div className="space-y-1.5">
          <Label htmlFor="booking-link">Seu link de agendamento</Label>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              id="booking-link"
              readOnly
              value={url}
              placeholder="Gerando link..."
              onFocus={(e) => e.currentTarget.select()}
              className="font-mono text-sm text-muted-foreground"
            />
            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={handleCopy}
                disabled={!url}
                className="flex-1 sm:flex-none"
              >
                {copied ? (
                  <>
                    <Check className="size-4" />
                    Copiado
                  </>
                ) : (
                  <>
                    <Copy className="size-4" />
                    Copiar
                  </>
                )}
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={handleOpen}
                disabled={!url}
                className="flex-1 sm:flex-none"
              >
                <ExternalLink className="size-4" />
                Abrir
              </Button>
            </div>
          </div>
        </div>

        <div className="rounded-xl border bg-muted/30 p-5">
          <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
            <div className="flex size-40 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-white p-2">
              {qrDataUrl ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={qrDataUrl} alt="QR code do link de agendamento" className="size-full" />
              ) : (
                <QrCode className="size-10 text-muted-foreground/50" />
              )}
            </div>
            <div className="flex flex-col items-center gap-3 text-center sm:items-start sm:text-left">
              <div>
                <p className="text-sm font-medium text-foreground">QR code do agendamento</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  Imprima ou compartilhe para que seus clientes agendem apontando a câmera.
                </p>
              </div>
              <Button
                type="button"
                variant="outline"
                onClick={handleDownloadQr}
                disabled={!qrDataUrl}
              >
                <Download className="size-4" />
                Baixar QR code
              </Button>
            </div>
          </div>
        </div>
      </div>
    </Card>
  )
}
