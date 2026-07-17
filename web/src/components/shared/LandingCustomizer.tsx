'use client'

import { useEffect, useRef, useState } from 'react'
import { ImagePlus, Trash2, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { tenantsService } from '@/services/tenants'
import { galleryService } from '@/services/gallery'
import type { GalleryImage } from '@/types/index'
import { assetUrl } from '@/lib/assets'
import { getSafeErrorMessage } from '@/lib/api-error'

/** teal-600 — cor padrão do tema, usada quando o salão ainda não escolheu uma. */
const DEFAULT_COLOR = '#0d9488'

export function LandingCustomizer({ slug }: { slug: string }) {
  const [color, setColor] = useState(DEFAULT_COLOR)
  const [logoUrl, setLogoUrl] = useState<string | null>(null)
  const [gallery, setGallery] = useState<GalleryImage[]>([])

  const [isSavingColor, setIsSavingColor] = useState(false)
  const [isUploadingLogo, setIsUploadingLogo] = useState(false)
  const [isUploadingImage, setIsUploadingImage] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const logoInput = useRef<HTMLInputElement>(null)
  const imageInput = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!slug) return
    tenantsService
      .show(slug)
      .then((res) => {
        const t = res.data.data
        if (t.brand_color) setColor(t.brand_color)
        setLogoUrl(t.logo_url)
      })
      .catch(() => {
        // Silencioso: mantém os padrões.
      })
    galleryService
      .list(slug)
      .then((res) => setGallery(res.data.data))
      .catch(() => {
        // Silencioso: galeria fica vazia.
      })
  }, [slug])

  async function handleSaveColor() {
    setError('')
    setSuccess('')
    setIsSavingColor(true)
    try {
      await tenantsService.updateSettings(slug, { brand_color: color })
      setSuccess('Cor da marca salva!')
    } catch (err) {
      setError(getSafeErrorMessage(err, 'Erro ao salvar a cor. Tente novamente.'))
    } finally {
      setIsSavingColor(false)
    }
  }

  async function handleLogo(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    setError('')
    setSuccess('')
    setIsUploadingLogo(true)
    try {
      const res = await tenantsService.uploadLogo(slug, file)
      setLogoUrl(res.data.data.logo_url)
      setSuccess('Logo enviada!')
    } catch (err) {
      setError(getSafeErrorMessage(err, 'Erro ao enviar a logo. Use uma imagem de até 2MB.'))
    } finally {
      setIsUploadingLogo(false)
      if (logoInput.current) logoInput.current.value = ''
    }
  }

  async function handleAddImage(e: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files ?? [])
    if (files.length === 0) return
    setError('')
    setSuccess('')
    setIsUploadingImage(true)
    let failures = 0
    try {
      for (const file of files) {
        try {
          const res = await galleryService.add(slug, file)
          setGallery((prev) => [...prev, res.data.data])
        } catch {
          failures += 1
        }
      }
      if (failures > 0) {
        setError(
          failures === files.length
            ? 'Erro ao enviar as imagens. Use imagens de até 4MB.'
            : `${failures} de ${files.length} imagens não foram enviadas. Use imagens de até 4MB.`,
        )
      }
    } finally {
      setIsUploadingImage(false)
      if (imageInput.current) imageInput.current.value = ''
    }
  }

  async function handleRemove(id: string) {
    setError('')
    setSuccess('')
    try {
      await galleryService.remove(slug, id)
      setGallery((prev) => prev.filter((image) => image.id !== id))
    } catch (err) {
      setError(getSafeErrorMessage(err, 'Erro ao remover a imagem.'))
    }
  }

  const logoPreview = assetUrl(logoUrl)

  return (
    <div className="space-y-3">
      <div>
        <h2 className="text-lg font-semibold text-foreground">Personalização da página</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Deixe a página pública com a cara do seu salão: cor da marca, logo e fotos dos
          seus atendimentos.
        </p>
      </div>

      <Card className="max-w-2xl space-y-8 p-6">
        {/* Cor da marca */}
        <div className="space-y-2">
          <Label htmlFor="brand-color">Cor da marca</Label>
          <div className="flex items-center gap-3">
            <Input
              id="brand-color"
              type="color"
              value={color}
              onChange={(e) => setColor(e.target.value)}
              disabled={isSavingColor}
              className="h-10 w-16 cursor-pointer p-1"
            />
            <Input
              value={color}
              onChange={(e) => setColor(e.target.value)}
              disabled={isSavingColor}
              className="w-32 font-mono"
              aria-label="Código hexadecimal da cor"
            />
            <Button variant="outline" onClick={handleSaveColor} disabled={isSavingColor}>
              {isSavingColor ? 'Salvando...' : 'Salvar cor'}
            </Button>
          </div>
        </div>

        {/* Logo */}
        <div className="space-y-2">
          <Label>Logo</Label>
          <div className="flex items-center gap-4">
            <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-lg border bg-muted">
              {logoPreview ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={logoPreview} alt="Logo do salão" className="h-full w-full object-contain" />
              ) : (
                <ImagePlus className="h-6 w-6 text-muted-foreground" />
              )}
            </div>
            <input
              ref={logoInput}
              type="file"
              accept="image/*"
              onChange={handleLogo}
              className="hidden"
            />
            <Button
              variant="outline"
              onClick={() => logoInput.current?.click()}
              disabled={isUploadingLogo}
            >
              <Upload className="mr-2 h-4 w-4" />
              {isUploadingLogo ? 'Enviando...' : logoPreview ? 'Trocar logo' : 'Enviar logo'}
            </Button>
          </div>
        </div>

        {/* Galeria */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <Label>Galeria de atendimentos</Label>
            <input
              ref={imageInput}
              type="file"
              accept="image/*"
              multiple
              onChange={handleAddImage}
              className="hidden"
            />
            <Button
              variant="outline"
              size="sm"
              onClick={() => imageInput.current?.click()}
              disabled={isUploadingImage}
            >
              <ImagePlus className="mr-2 h-4 w-4" />
              {isUploadingImage ? 'Enviando...' : 'Adicionar foto'}
            </Button>
          </div>

          {gallery.length === 0 ? (
            <p className="rounded-lg border border-dashed py-8 text-center text-sm text-muted-foreground">
              Nenhuma foto ainda. Mostre seus trabalhos aos clientes.
            </p>
          ) : (
            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
              {gallery.map((image) => {
                const url = assetUrl(image.image_url)
                if (!url) return null
                return (
                  <div key={image.id} className="group relative overflow-hidden rounded-lg border">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={url} alt={image.caption ?? 'Trabalho do salão'} className="aspect-square w-full object-cover" />
                    <button
                      type="button"
                      onClick={() => handleRemove(image.id)}
                      aria-label="Remover foto"
                      className="absolute right-1.5 top-1.5 rounded-md bg-black/60 p-1.5 text-white opacity-0 transition-opacity hover:bg-red-600 group-hover:opacity-100"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        {error && (
          <p className="rounded-lg bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-600 dark:text-red-400">{error}</p>
        )}
        {success && (
          <p className="rounded-lg bg-green-50 dark:bg-green-950/40 px-3 py-2 text-sm text-green-700 dark:text-green-400">{success}</p>
        )}
      </Card>
    </div>
  )
}
