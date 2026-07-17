'use client'

import { useState, useEffect, useRef } from 'react'
import { useParams } from 'next/navigation'
import { Plus, Pencil, Trash2, ImagePlus, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from '@/components/ui/dialog'
import { servicesService } from '@/services/services'
import type { CreateServiceData } from '@/services/services'
import type { Service } from '@/types/index'
import { formatPrice, formatDuration } from '@/lib/format'
import { getSafeErrorMessage } from '@/lib/api-error'
import { assetUrl } from '@/lib/assets'

/** 'inherit' = herda o padrão do salão; demais = override explícito do serviço. */
type DepositMode = 'inherit' | 'none' | 'fixed' | 'percentage'

/** Rótulos exibidos no Select (Base UI usa isso para mostrar o texto selecionado). */
const DEPOSIT_MODE_LABELS: Record<DepositMode, string> = {
  inherit: 'Padrão do salão',
  none: 'Sem sinal (valor cheio)',
  fixed: 'Valor fixo (R$)',
  percentage: 'Percentual (%)',
}

interface ServiceFormState {
  name: string
  description: string
  price: string
  duration_minutes: string
  active: boolean
  deposit_mode: DepositMode
  deposit_value: string
}

const emptyForm: ServiceFormState = {
  name: '',
  description: '',
  price: '',
  duration_minutes: '',
  active: true,
  deposit_mode: 'inherit',
  deposit_value: '',
}

function priceInputToCents(value: string): number {
  const cleaned = value.replace(',', '.')
  return Math.round(parseFloat(cleaned) * 100)
}

/** Deriva o modo/valor do formulário a partir do serviço salvo. */
function depositFormFromService(service: Service): Pick<ServiceFormState, 'deposit_mode' | 'deposit_value'> {
  const mode: DepositMode = service.deposit_type ?? 'inherit'
  if (mode === 'fixed' && service.deposit_value != null) {
    return { deposit_mode: mode, deposit_value: (service.deposit_value / 100).toFixed(2).replace('.', ',') }
  }
  if (mode === 'percentage' && service.deposit_value != null) {
    return { deposit_mode: mode, deposit_value: String(service.deposit_value) }
  }
  return { deposit_mode: mode, deposit_value: '' }
}

export default function ServicesPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  const [services, setServices] = useState<Service[]>([])
  const [isLoadingList, setIsLoadingList] = useState(true)
  const [listError, setListError] = useState<string | null>(null)
  const [refreshKey, setRefreshKey] = useState(0)

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingService, setEditingService] = useState<Service | null>(null)
  const [form, setForm] = useState<ServiceFormState>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const [deleteTarget, setDeleteTarget] = useState<Service | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  // Imagem do serviço. `imageFile` = novo arquivo escolhido (upload após salvar);
  // `imagePreview` = o que mostrar (URL do serviço existente ou preview local).
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const imageInput = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!slug) return
    let cancelled = false

    servicesService
      .list(slug)
      .then((response) => {
        if (!cancelled) {
          setServices(response.data.data)
          setListError(null)
          setIsLoadingList(false)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setListError('Erro ao carregar serviços. Tente novamente.')
          setIsLoadingList(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [slug, refreshKey])

  function refresh() {
    setRefreshKey((k) => k + 1)
  }

  function openCreate() {
    setEditingService(null)
    setForm(emptyForm)
    setImageFile(null)
    setImagePreview(null)
    setFormError(null)
    setIsFormOpen(true)
  }

  function openEdit(service: Service) {
    setEditingService(service)
    setForm({
      name: service.name,
      description: service.description ?? '',
      price: (service.price / 100).toFixed(2).replace('.', ','),
      duration_minutes: String(service.duration_minutes),
      active: service.active,
      ...depositFormFromService(service),
    })
    setImageFile(null)
    setImagePreview(assetUrl(service.image_url))
    setFormError(null)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setEditingService(null)
    setForm(emptyForm)
    setImageFile(null)
    setImagePreview(null)
    setFormError(null)
  }

  function handlePickImage(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    setImageFile(file)
    setImagePreview(URL.createObjectURL(file))
  }

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setFormError(null)

    const priceValue = priceInputToCents(form.price)
    const durationValue = parseInt(form.duration_minutes, 10)

    if (isNaN(priceValue) || priceValue <= 0) {
      setFormError('Informe um preço válido.')
      return
    }
    if (isNaN(durationValue) || durationValue <= 0) {
      setFormError('Informe uma duração válida em minutos.')
      return
    }

    const payload: CreateServiceData = {
      name: form.name,
      description: form.description,
      price: priceValue,
      duration_minutes: durationValue,
      active: form.active,
    }

    // Sinal do serviço. 'inherit' => herda o salão (envia null p/ limpar override).
    if (form.deposit_mode === 'inherit') {
      payload.deposit_type = null
      payload.deposit_value = null
    } else if (form.deposit_mode === 'none') {
      payload.deposit_type = 'none'
      payload.deposit_value = null
    } else {
      const isPercent = form.deposit_mode === 'percentage'
      const depositValue = isPercent
        ? parseInt(form.deposit_value, 10)
        : priceInputToCents(form.deposit_value)

      if (isNaN(depositValue) || depositValue <= 0) {
        setFormError('Informe um valor de sinal válido.')
        return
      }
      if (isPercent && depositValue > 100) {
        setFormError('O percentual do sinal não pode passar de 100%.')
        return
      }

      payload.deposit_type = form.deposit_mode
      payload.deposit_value = depositValue
    }

    setIsSubmitting(true)
    try {
      // A imagem é enviada após salvar, pois o endpoint precisa do id do serviço.
      const saved = editingService
        ? await servicesService.update(slug, editingService.id, payload)
        : await servicesService.create(slug, payload)

      if (imageFile) {
        await servicesService.uploadImage(slug, saved.data.data.id, imageFile)
      }

      closeForm()
      refresh()
    } catch (err) {
      setFormError(getSafeErrorMessage(err, 'Erro ao salvar serviço.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await servicesService.remove(slug, deleteTarget.id)
      setDeleteTarget(null)
      refresh()
    } catch {
      // Error handled silently — list will not change
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Serviços</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Gerencie os serviços oferecidos pelo seu negócio
          </p>
        </div>
        <Button onClick={openCreate} className="gap-2">
          <Plus className="h-4 w-4" />
          Novo serviço
        </Button>
      </div>

      {/* List */}
      <div className="rounded-xl border bg-card">
        {isLoadingList ? (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-muted-foreground">Carregando serviços...</p>
          </div>
        ) : listError ? (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-red-500 dark:text-red-400">{listError}</p>
          </div>
        ) : services.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <p className="text-sm font-medium text-muted-foreground">Nenhum serviço cadastrado</p>
            <p className="mt-1 text-xs text-muted-foreground">
              Clique em &quot;Novo serviço&quot; para começar.
            </p>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Preço</TableHead>
                <TableHead>Duração</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Ações</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {services.map((service) => (
                <TableRow key={service.id}>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted">
                        {assetUrl(service.image_url) ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={assetUrl(service.image_url)!} alt={service.name} className="h-full w-full object-cover" />
                        ) : (
                          <ImagePlus className="h-4 w-4 text-muted-foreground" />
                        )}
                      </div>
                      <div className="min-w-0">
                        <p className="font-medium text-foreground">{service.name}</p>
                        {service.description && (
                          <p className="mt-0.5 text-xs text-muted-foreground line-clamp-1">
                            {service.description}
                          </p>
                        )}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="font-medium">
                    {formatPrice(service.price)}
                  </TableCell>
                  <TableCell>{formatDuration(service.duration_minutes)}</TableCell>
                  <TableCell>
                    {service.active ? (
                      <Badge variant="default" className="bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-500/15">
                        Ativo
                      </Badge>
                    ) : (
                      <Badge variant="secondary">Inativo</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex items-center justify-end gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0"
                        onClick={() => openEdit(service)}
                      >
                        <Pencil className="h-4 w-4" />
                        <span className="sr-only">Editar</span>
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0 text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40"
                        onClick={() => setDeleteTarget(service)}
                      >
                        <Trash2 className="h-4 w-4" />
                        <span className="sr-only">Excluir</span>
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>

      {/* Create / Edit Dialog */}
      <Dialog open={isFormOpen} onOpenChange={(open) => { if (!open) closeForm() }}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {editingService ? 'Editar serviço' : 'Novo serviço'}
            </DialogTitle>
            <DialogDescription>
              {editingService
                ? 'Atualize as informações do serviço.'
                : 'Preencha os dados para criar um novo serviço.'}
            </DialogDescription>
          </DialogHeader>
          <form id="service-form" onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="service-name">Nome</Label>
              <Input
                id="service-name"
                required
                placeholder="Ex: Corte feminino"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                disabled={isSubmitting}
              />
            </div>
            <div className="space-y-1.5">
              <Label>Foto do serviço</Label>
              <div className="flex items-center gap-4">
                <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted">
                  {imagePreview ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={imagePreview} alt="Prévia do serviço" className="h-full w-full object-cover" />
                  ) : (
                    <ImagePlus className="h-5 w-5 text-muted-foreground" />
                  )}
                </div>
                <input
                  ref={imageInput}
                  type="file"
                  accept="image/*"
                  onChange={handlePickImage}
                  className="hidden"
                />
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => imageInput.current?.click()}
                  disabled={isSubmitting}
                >
                  <Upload className="mr-2 h-4 w-4" />
                  {imagePreview ? 'Trocar foto' : 'Adicionar foto'}
                </Button>
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="service-desc">Descrição</Label>
              <Textarea
                id="service-desc"
                placeholder="Descrição do serviço (opcional)"
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                disabled={isSubmitting}
                rows={3}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="service-price">Preço (R$)</Label>
                <Input
                  id="service-price"
                  required
                  placeholder="0,00"
                  value={form.price}
                  onChange={(e) => setForm((f) => ({ ...f, price: e.target.value }))}
                  disabled={isSubmitting}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="service-duration">Duração (min)</Label>
                <Input
                  id="service-duration"
                  required
                  type="number"
                  min={1}
                  placeholder="60"
                  value={form.duration_minutes}
                  onChange={(e) =>
                    setForm((f) => ({ ...f, duration_minutes: e.target.value }))
                  }
                  disabled={isSubmitting}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div
                className={
                  form.deposit_mode === 'fixed' || form.deposit_mode === 'percentage'
                    ? 'space-y-1.5'
                    : 'col-span-2 space-y-1.5'
                }
              >
                <Label htmlFor="service-deposit-mode">Sinal na reserva</Label>
                <Select
                  items={DEPOSIT_MODE_LABELS}
                  value={form.deposit_mode}
                  onValueChange={(v) =>
                    setForm((f) => ({ ...f, deposit_mode: v as DepositMode, deposit_value: '' }))
                  }
                  disabled={isSubmitting}
                >
                  <SelectTrigger id="service-deposit-mode" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="inherit">{DEPOSIT_MODE_LABELS.inherit}</SelectItem>
                    <SelectItem value="none">{DEPOSIT_MODE_LABELS.none}</SelectItem>
                    <SelectItem value="fixed">{DEPOSIT_MODE_LABELS.fixed}</SelectItem>
                    <SelectItem value="percentage">{DEPOSIT_MODE_LABELS.percentage}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              {(form.deposit_mode === 'fixed' || form.deposit_mode === 'percentage') && (
                <div className="space-y-1.5">
                  <Label htmlFor="service-deposit-value">
                    {form.deposit_mode === 'fixed' ? 'Sinal (R$)' : 'Sinal (%)'}
                  </Label>
                  <Input
                    id="service-deposit-value"
                    required
                    placeholder={form.deposit_mode === 'fixed' ? '0,00' : '20'}
                    value={form.deposit_value}
                    onChange={(e) => setForm((f) => ({ ...f, deposit_value: e.target.value }))}
                    disabled={isSubmitting}
                  />
                </div>
              )}
            </div>
            <p className="text-xs text-muted-foreground">
              O cliente paga o sinal para confirmar; o restante é pago presencialmente.
            </p>
            <div className="space-y-1.5">
            <Label>Status</Label>
            <div className="flex items-center gap-3">
              <button
                type="button"
                role="switch"
                aria-checked={form.active}
                onClick={() => setForm((f) => ({ ...f, active: !f.active }))}
                disabled={isSubmitting}
                className={[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-50',
                  form.active ? 'bg-primary' : 'bg-muted',
                ].join(' ')}
              >
                <span
                  className={[
                    'inline-block h-4 w-4 rounded-full bg-white shadow transition-transform',
                    form.active ? 'translate-x-6' : 'translate-x-1',
                  ].join(' ')}
                />
              </button>
              <Label className="cursor-pointer select-none">
                {form.active ? 'Ativo' : 'Inativo'}
              </Label>
            </div>
            </div>
            {formError && (
              <p className="rounded-lg bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-600 dark:text-red-400">
                {formError}
              </p>
            )}
          </form>
          <DialogFooter>
            <Button variant="outline" onClick={closeForm} disabled={isSubmitting}>
              Cancelar
            </Button>
            <Button type="submit" form="service-form" disabled={isSubmitting}>
              {isSubmitting ? 'Salvando...' : 'Salvar'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={!!deleteTarget} onOpenChange={(open) => { if (!open) setDeleteTarget(null) }}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Excluir serviço</DialogTitle>
            <DialogDescription>
              Tem certeza que deseja excluir o serviço{' '}
              <span className="font-semibold">{deleteTarget?.name}</span>? Esta ação não
              pode ser desfeita.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={isDeleting}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={handleDelete}
              disabled={isDeleting}
            >
              {isDeleting ? 'Excluindo...' : 'Excluir'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
