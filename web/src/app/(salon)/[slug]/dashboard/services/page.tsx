'use client'

import { useState, useEffect } from 'react'
import { useParams } from 'next/navigation'
import { isAxiosError } from 'axios'
import { Plus, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
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
import type { Service, ApiError } from '@/types/index'
import { formatPrice, formatDuration } from '@/lib/format'

interface ServiceFormState {
  name: string
  description: string
  price: string
  duration_minutes: string
}

const emptyForm: ServiceFormState = {
  name: '',
  description: '',
  price: '',
  duration_minutes: '',
}

function priceInputToCents(value: string): number {
  const cleaned = value.replace(',', '.')
  return Math.round(parseFloat(cleaned) * 100)
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
    })
    setFormError(null)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setEditingService(null)
    setForm(emptyForm)
    setFormError(null)
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
    }

    setIsSubmitting(true)
    try {
      if (editingService) {
        await servicesService.update(slug, editingService.id, payload)
      } else {
        await servicesService.create(slug, payload)
      }
      closeForm()
      refresh()
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        setFormError(apiError?.message ?? 'Erro ao salvar serviço.')
      } else {
        setFormError('Erro inesperado. Tente novamente.')
      }
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
            Gerencie os serviços oferecidos pelo seu salão
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
                    <div>
                      <p className="font-medium text-foreground">{service.name}</p>
                      {service.description && (
                        <p className="mt-0.5 text-xs text-muted-foreground line-clamp-1">
                          {service.description}
                        </p>
                      )}
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
