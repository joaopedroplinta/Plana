'use client'

import { useCallback, useEffect, useState } from 'react'
import { useParams } from 'next/navigation'
import { isAxiosError } from 'axios'
import { UserPlus, Users, X } from 'lucide-react'
import { toast } from 'sonner'
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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useTenant } from '@/hooks/useTenant'
import { teamService } from '@/services/team'
import type { ApiError, TeamMember } from '@/types/index'

export default function TeamPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''
  const { tenant } = useTenant(slug)

  const [members, setMembers] = useState<TeamMember[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [isInviting, setIsInviting] = useState(false)
  const [removingId, setRemovingId] = useState<number | null>(null)
  const [memberToRemove, setMemberToRemove] = useState<TeamMember | null>(null)

  const isOwner = tenant?.current_tenant_role === 'owner'

  const loadMembers = useCallback(() => {
    if (!slug) return
    teamService
      .list(slug)
      .then((res) => setMembers(res.data.data))
      .catch(() => setError('Erro ao carregar a equipe.'))
      .finally(() => setIsLoading(false))
  }, [slug])

  useEffect(() => {
    loadMembers()
  }, [loadMembers])

  async function handleInvite(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setIsInviting(true)
    try {
      await teamService.invite(slug, { name: name.trim(), email: email.trim() })
      toast.success(
        `Convite enviado para ${email.trim()}. A pessoa receberá um e-mail para definir a senha.`,
      )
      setName('')
      setEmail('')
      loadMembers()
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        toast.error(
          apiError?.errors?.email?.[0] ?? apiError?.message ?? 'Erro ao enviar convite.',
        )
      } else {
        toast.error('Erro inesperado. Tente novamente.')
      }
    } finally {
      setIsInviting(false)
    }
  }

  async function handleRemove() {
    if (!memberToRemove) return
    const member = memberToRemove
    setRemovingId(member.id)
    setMemberToRemove(null)
    try {
      await teamService.remove(slug, member.id)
      loadMembers()
    } catch {
      toast.error('Erro ao remover membro da equipe.')
    } finally {
      setRemovingId(null)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Equipe</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Convide funcionários para acessar a agenda do salão
        </p>
      </div>

      {isOwner && (
        <Card className="p-5">
          <h2 className="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground">
            <UserPlus className="h-4 w-4 text-indigo-500" />
            Convidar funcionário
          </h2>
          <form onSubmit={handleInvite} className="flex flex-wrap items-end gap-3">
            <div className="min-w-48 flex-1 space-y-1.5">
              <Label htmlFor="invite-name">Nome</Label>
              <Input
                id="invite-name"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Ex: Ana Silva"
                disabled={isInviting}
              />
            </div>
            <div className="min-w-56 flex-1 space-y-1.5">
              <Label htmlFor="invite-email">E-mail</Label>
              <Input
                id="invite-email"
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="ana@email.com"
                disabled={isInviting}
              />
            </div>
            <Button type="submit" disabled={isInviting}>
              {isInviting ? 'Enviando...' : 'Convidar'}
            </Button>
          </form>
        </Card>
      )}

      {error && (
        <div className="rounded-lg bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-600 dark:text-red-400">{error}</div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center rounded-xl border bg-card py-24">
          <p className="text-sm text-muted-foreground animate-pulse">Carregando equipe...</p>
        </div>
      ) : members.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-xl border bg-card py-24 text-center">
          <div className="rounded-full bg-indigo-50 dark:bg-indigo-500/15 p-4">
            <Users className="h-10 w-10 text-indigo-400" />
          </div>
          <p className="mt-4 text-sm text-muted-foreground">Nenhum membro na equipe ainda.</p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl border bg-card">
          <ul className="divide-y">
            {members.map((member) => (
              <li key={member.id} className="flex items-center gap-4 px-4 py-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/15 text-sm font-bold text-indigo-600 dark:text-indigo-300">
                  {member.name.charAt(0).toUpperCase()}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium text-foreground">{member.name}</p>
                  <p className="truncate text-sm text-muted-foreground">{member.email}</p>
                </div>
                <Badge
                  variant="secondary"
                  className={
                    member.role === 'owner'
                      ? 'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300'
                      : 'bg-muted text-muted-foreground'
                  }
                >
                  {member.role === 'owner' ? 'Dono' : 'Funcionário'}
                </Badge>
                {isOwner && member.role !== 'owner' && (
                  <Button
                    size="sm"
                    variant="ghost"
                    className="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 hover:text-red-700 dark:hover:text-red-400"
                    disabled={removingId === member.id}
                    onClick={() => setMemberToRemove(member)}
                    title="Remover da equipe"
                  >
                    <X className="h-4 w-4" />
                  </Button>
                )}
              </li>
            ))}
          </ul>
        </div>
      )}

      <AlertDialog
        open={memberToRemove !== null}
        onOpenChange={(open) => !open && setMemberToRemove(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover da equipe</AlertDialogTitle>
            <AlertDialogDescription>
              Remover {memberToRemove?.name} da equipe?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-red-600 text-white hover:bg-red-700"
              onClick={handleRemove}
            >
              Remover
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
