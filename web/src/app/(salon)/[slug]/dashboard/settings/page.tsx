'use client'

import { useEffect, useState } from 'react'
import { useParams } from 'next/navigation'
import { isAxiosError } from 'axios'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { tenantsService } from '@/services/tenants'
import type { ApiError } from '@/types/index'

interface FormState {
  name: string
  description: string
  phone: string
  whatsapp: string
  address: string
  instagram: string
}

const EMPTY_FORM: FormState = {
  name: '',
  description: '',
  phone: '',
  whatsapp: '',
  address: '',
  instagram: '',
}

export default function SalonSettingsPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  const [form, setForm] = useState<FormState>(EMPTY_FORM)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  useEffect(() => {
    if (!slug) return
    tenantsService
      .show(slug)
      .then((res) => {
        const t = res.data.data
        setForm({
          name: t.name ?? '',
          description: t.description ?? '',
          phone: t.phone ?? '',
          whatsapp: t.whatsapp ?? '',
          address: t.address ?? '',
          instagram: t.instagram ?? '',
        })
      })
      .catch(() => setError('Erro ao carregar os dados do negócio.'))
      .finally(() => setIsLoading(false))
  }, [slug])

  function setField(field: keyof FormState, value: string) {
    setForm((f) => ({ ...f, [field]: value }))
  }

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setError('')
    setSuccess('')
    setIsSaving(true)
    try {
      await tenantsService.updateSettings(slug, {
        name: form.name.trim(),
        description: form.description.trim() || null,
        phone: form.phone.trim() || null,
        whatsapp: form.whatsapp.trim() || null,
        address: form.address.trim() || null,
        instagram: form.instagram.trim().replace(/^@/, '') || null,
      })
      setSuccess('Perfil do negócio atualizado! As mudanças já aparecem na sua página pública.')
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        setError(apiError?.message ?? 'Erro ao salvar. Tente novamente.')
      } else {
        setError('Erro inesperado. Tente novamente.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Meu negócio</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Essas informações aparecem na página pública do seu negócio
        </p>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center rounded-xl border bg-card py-24">
          <p className="text-sm text-muted-foreground animate-pulse">Carregando...</p>
        </div>
      ) : (
        <Card className="max-w-2xl p-6">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-1.5">
              <Label htmlFor="salon-name">Nome do negócio</Label>
              <Input
                id="salon-name"
                required
                value={form.name}
                onChange={(e) => setField('name', e.target.value)}
                disabled={isSaving}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="salon-description">Descrição</Label>
              <Textarea
                id="salon-description"
                rows={3}
                placeholder="Conte aos clientes o que torna seu negócio especial..."
                value={form.description}
                onChange={(e) => setField('description', e.target.value)}
                disabled={isSaving}
              />
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="salon-phone">Telefone</Label>
                <Input
                  id="salon-phone"
                  placeholder="(42) 3333-0000"
                  value={form.phone}
                  onChange={(e) => setField('phone', e.target.value)}
                  disabled={isSaving}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="salon-whatsapp">WhatsApp</Label>
                <Input
                  id="salon-whatsapp"
                  placeholder="5542999990000"
                  value={form.whatsapp}
                  onChange={(e) => setField('whatsapp', e.target.value)}
                  disabled={isSaving}
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="salon-address">Endereço</Label>
              <Input
                id="salon-address"
                placeholder="Rua das Flores, 123 — Centro"
                value={form.address}
                onChange={(e) => setField('address', e.target.value)}
                disabled={isSaving}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="salon-instagram">Instagram</Label>
              <Input
                id="salon-instagram"
                placeholder="meunegocio (sem @)"
                value={form.instagram}
                onChange={(e) => setField('instagram', e.target.value)}
                disabled={isSaving}
              />
            </div>

            {error && (
              <p className="rounded-lg bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-600 dark:text-red-400">{error}</p>
            )}
            {success && (
              <p className="rounded-lg bg-green-50 dark:bg-green-950/40 px-3 py-2 text-sm text-green-700 dark:text-green-400">
                {success}
              </p>
            )}

            <Button type="submit" disabled={isSaving}>
              {isSaving ? 'Salvando...' : 'Salvar alterações'}
            </Button>
          </form>
        </Card>
      )}
    </div>
  )
}
