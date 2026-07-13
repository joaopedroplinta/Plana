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
import { professionalsService } from '@/services/professionals'
import type { CreateProfessionalData } from '@/services/professionals'
import type { Professional, ApiError } from '@/types/index'

interface ProfessionalFormState {
  name: string
  bio: string
  active: boolean
}

const emptyForm: ProfessionalFormState = {
  name: '',
  bio: '',
  active: true,
}

export default function ProfessionalsPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  const [professionals, setProfessionals] = useState<Professional[]>([])
  const [isLoadingList, setIsLoadingList] = useState(true)
  const [listError, setListError] = useState<string | null>(null)
  const [refreshKey, setRefreshKey] = useState(0)

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingProfessional, setEditingProfessional] = useState<Professional | null>(null)
  const [form, setForm] = useState<ProfessionalFormState>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const [deleteTarget, setDeleteTarget] = useState<Professional | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    if (!slug) return
    let cancelled = false

    professionalsService
      .list(slug)
      .then((response) => {
        if (!cancelled) {
          setProfessionals(response.data.data)
          setListError(null)
          setIsLoadingList(false)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setListError('Erro ao carregar profissionais. Tente novamente.')
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
    setEditingProfessional(null)
    setForm(emptyForm)
    setFormError(null)
    setIsFormOpen(true)
  }

  function openEdit(professional: Professional) {
    setEditingProfessional(professional)
    setForm({
      name: professional.name,
      bio: professional.bio ?? '',
      active: professional.active,
    })
    setFormError(null)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setEditingProfessional(null)
    setForm(emptyForm)
    setFormError(null)
  }

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setFormError(null)

    const payload: CreateProfessionalData = {
      name: form.name,
      bio: form.bio,
      active: form.active,
    }

    setIsSubmitting(true)
    try {
      if (editingProfessional) {
        await professionalsService.update(slug, editingProfessional.id, payload)
      } else {
        await professionalsService.create(slug, payload)
      }
      closeForm()
      refresh()
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        setFormError(apiError?.message ?? 'Erro ao salvar profissional.')
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
      await professionalsService.remove(slug, deleteTarget.id)
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
          <h1 className="text-2xl font-bold text-foreground">Profissionais</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Gerencie os profissionais do seu negócio
          </p>
        </div>
        <Button onClick={openCreate} className="gap-2">
          <Plus className="h-4 w-4" />
          Novo profissional
        </Button>
      </div>

      {/* List */}
      <div className="rounded-xl border bg-card">
        {isLoadingList ? (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-muted-foreground">Carregando profissionais...</p>
          </div>
        ) : listError ? (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-red-500 dark:text-red-400">{listError}</p>
          </div>
        ) : professionals.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <p className="text-sm font-medium text-muted-foreground">
              Nenhum profissional cadastrado
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              Clique em &quot;Novo profissional&quot; para começar.
            </p>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Bio</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Ações</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {professionals.map((professional) => (
                <TableRow key={professional.id}>
                  <TableCell>
                    <p className="font-medium text-foreground">{professional.name}</p>
                  </TableCell>
                  <TableCell>
                    <p className="max-w-xs text-sm text-muted-foreground line-clamp-2">
                      {professional.bio ?? '—'}
                    </p>
                  </TableCell>
                  <TableCell>
                    {professional.active ? (
                      <Badge
                        variant="default"
                        className="bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-500/15"
                      >
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
                        onClick={() => openEdit(professional)}
                      >
                        <Pencil className="h-4 w-4" />
                        <span className="sr-only">Editar</span>
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0 text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40"
                        onClick={() => setDeleteTarget(professional)}
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
              {editingProfessional ? 'Editar profissional' : 'Novo profissional'}
            </DialogTitle>
            <DialogDescription>
              {editingProfessional
                ? 'Atualize as informações do profissional.'
                : 'Preencha os dados para cadastrar um novo profissional.'}
            </DialogDescription>
          </DialogHeader>
          <form id="professional-form" onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="prof-name">Nome</Label>
              <Input
                id="prof-name"
                required
                placeholder="Ex: Ana Lima"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                disabled={isSubmitting}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="prof-bio">Bio</Label>
              <Textarea
                id="prof-bio"
                placeholder="Breve descrição sobre o profissional (opcional)"
                value={form.bio}
                onChange={(e) => setForm((f) => ({ ...f, bio: e.target.value }))}
                disabled={isSubmitting}
                rows={3}
              />
            </div>
            <div className="flex items-center gap-3">
              <button
                type="button"
                role="switch"
                aria-checked={form.active}
                onClick={() => setForm((f) => ({ ...f, active: !f.active }))}
                disabled={isSubmitting}
                className={[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50',
                  form.active ? 'bg-indigo-600' : 'bg-muted',
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
            <Button type="submit" form="professional-form" disabled={isSubmitting}>
              {isSubmitting ? 'Salvando...' : 'Salvar'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog
        open={!!deleteTarget}
        onOpenChange={(open) => { if (!open) setDeleteTarget(null) }}
      >
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Excluir profissional</DialogTitle>
            <DialogDescription>
              Tem certeza que deseja excluir o profissional{' '}
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
