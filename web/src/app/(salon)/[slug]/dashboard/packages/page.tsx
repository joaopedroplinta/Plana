'use client'

import { useState, useEffect } from 'react'
import { useParams } from 'next/navigation'
import { isAxiosError } from 'axios'
import { Plus, Pencil, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
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
import { packagesService } from '@/services/packages'
import type { CreatePackageData } from '@/services/packages'
import type { ServicePackage, ApiError } from '@/types/index'
import { formatPrice } from '@/lib/format'

interface PackageFormState {
  name: string
  description: string
  price: string
  sessions: string
  valid_days: string
}

const emptyForm: PackageFormState = {
  name: '',
  description: '',
  price: '',
  sessions: '',
  valid_days: '',
}

function priceInputToCents(value: string): number {
  const cleaned = value.replace(',', '.')
  return Math.round(parseFloat(cleaned) * 100)
}

export default function PackagesPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  const [packages, setPackages] = useState<ServicePackage[]>([])
  const [isLoadingList, setIsLoadingList] = useState(true)
  const [listError, setListError] = useState<string | null>(null)
  const [refreshKey, setRefreshKey] = useState(0)

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingPackage, setEditingPackage] = useState<ServicePackage | null>(null)
  const [form, setForm] = useState<PackageFormState>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const [deleteTarget, setDeleteTarget] = useState<ServicePackage | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    if (!slug) return
    let cancelled = false

    packagesService
      .list(slug)
      .then((response) => {
        if (!cancelled) {
          setPackages(response.data.data)
          setListError(null)
          setIsLoadingList(false)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setListError('Erro ao carregar pacotes. Tente novamente.')
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
    setEditingPackage(null)
    setForm(emptyForm)
    setFormError(null)
    setIsFormOpen(true)
  }

  function openEdit(pkg: ServicePackage) {
    setEditingPackage(pkg)
    setForm({
      name: pkg.name,
      description: pkg.description ?? '',
      price: (pkg.price / 100).toFixed(2).replace('.', ','),
      sessions: String(pkg.sessions),
      valid_days: String(pkg.valid_days),
    })
    setFormError(null)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setEditingPackage(null)
    setForm(emptyForm)
    setFormError(null)
  }

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setFormError(null)

    const priceValue = priceInputToCents(form.price)
    const sessionsValue = parseInt(form.sessions, 10)
    const validDaysValue = parseInt(form.valid_days, 10)

    if (isNaN(priceValue) || priceValue <= 0) {
      setFormError('Informe um preço válido.')
      return
    }
    if (isNaN(sessionsValue) || sessionsValue <= 0) {
      setFormError('Informe uma quantidade de sessões válida.')
      return
    }
    if (isNaN(validDaysValue) || validDaysValue <= 0) {
      setFormError('Informe uma validade em dias válida.')
      return
    }

    const payload: CreatePackageData = {
      name: form.name,
      description: form.description,
      price: priceValue,
      sessions: sessionsValue,
      valid_days: validDaysValue,
      service_ids: [],
    }

    setIsSubmitting(true)
    try {
      if (editingPackage) {
        await packagesService.update(slug, editingPackage.id, payload)
      } else {
        await packagesService.create(slug, payload)
      }
      closeForm()
      refresh()
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        toast.error(apiError?.message ?? 'Erro ao salvar pacote.')
      } else {
        toast.error('Erro inesperado. Tente novamente.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await packagesService.remove(slug, deleteTarget.id)
      setDeleteTarget(null)
      refresh()
    } catch {
      toast.error('Erro ao excluir pacote.')
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Pacotes</h1>
          <p className="mt-1 text-sm text-gray-500">
            Gerencie os pacotes de serviços do seu salão
          </p>
        </div>
        <Button onClick={openCreate} className="gap-2">
          <Plus className="h-4 w-4" />
          Novo pacote
        </Button>
      </div>

      {/* List */}
      <div className="rounded-xl border bg-white">
        {isLoadingList ? (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-gray-400">Carregando pacotes...</p>
          </div>
        ) : listError ? (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-red-500">{listError}</p>
          </div>
        ) : packages.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <p className="text-sm font-medium text-gray-500">Nenhum pacote cadastrado</p>
            <p className="mt-1 text-xs text-gray-400">
              Clique em &quot;Novo pacote&quot; para começar.
            </p>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Preço</TableHead>
                <TableHead>Sessões</TableHead>
                <TableHead>Validade</TableHead>
                <TableHead className="text-right">Ações</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {packages.map((pkg) => (
                <TableRow key={pkg.id}>
                  <TableCell>
                    <div>
                      <p className="font-medium text-gray-900">{pkg.name}</p>
                      {pkg.description && (
                        <p className="mt-0.5 text-xs text-gray-400 line-clamp-1">
                          {pkg.description}
                        </p>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className="font-medium">{formatPrice(pkg.price)}</TableCell>
                  <TableCell>{pkg.sessions} sessões</TableCell>
                  <TableCell>{pkg.valid_days} dias</TableCell>
                  <TableCell className="text-right">
                    <div className="flex items-center justify-end gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0"
                        onClick={() => openEdit(pkg)}
                      >
                        <Pencil className="h-4 w-4" />
                        <span className="sr-only">Editar</span>
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0 text-red-500 hover:text-red-600 hover:bg-red-50"
                        onClick={() => setDeleteTarget(pkg)}
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
              {editingPackage ? 'Editar pacote' : 'Novo pacote'}
            </DialogTitle>
            <DialogDescription>
              {editingPackage
                ? 'Atualize as informações do pacote.'
                : 'Preencha os dados para criar um novo pacote.'}
            </DialogDescription>
          </DialogHeader>
          <form id="package-form" onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="pkg-name">Nome</Label>
              <Input
                id="pkg-name"
                required
                placeholder="Ex: Pacote VIP mensal"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                disabled={isSubmitting}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="pkg-desc">Descrição</Label>
              <Textarea
                id="pkg-desc"
                placeholder="Descrição do pacote (opcional)"
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                disabled={isSubmitting}
                rows={3}
              />
            </div>
            <div className="grid grid-cols-3 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="pkg-price">Preço (R$)</Label>
                <Input
                  id="pkg-price"
                  required
                  placeholder="0,00"
                  value={form.price}
                  onChange={(e) => setForm((f) => ({ ...f, price: e.target.value }))}
                  disabled={isSubmitting}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="pkg-sessions">Sessões</Label>
                <Input
                  id="pkg-sessions"
                  required
                  type="number"
                  min={1}
                  placeholder="10"
                  value={form.sessions}
                  onChange={(e) => setForm((f) => ({ ...f, sessions: e.target.value }))}
                  disabled={isSubmitting}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="pkg-valid">Validade (dias)</Label>
                <Input
                  id="pkg-valid"
                  required
                  type="number"
                  min={1}
                  placeholder="30"
                  value={form.valid_days}
                  onChange={(e) => setForm((f) => ({ ...f, valid_days: e.target.value }))}
                  disabled={isSubmitting}
                />
              </div>
            </div>
            {formError && (
              <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
                {formError}
              </p>
            )}
          </form>
          <DialogFooter>
            <Button variant="outline" onClick={closeForm} disabled={isSubmitting}>
              Cancelar
            </Button>
            <Button type="submit" form="package-form" disabled={isSubmitting}>
              {isSubmitting ? 'Salvando...' : 'Salvar'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={!!deleteTarget} onOpenChange={(open) => { if (!open) setDeleteTarget(null) }}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Excluir pacote</DialogTitle>
            <DialogDescription>
              Tem certeza que deseja excluir o pacote{' '}
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
