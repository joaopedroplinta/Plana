'use client'

import { useEffect, useRef, useState } from 'react'
import { Upload, UserRound } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'
import { profileService } from '@/services/profile'
import { getSafeErrorMessage } from '@/lib/api-error'
import { assetUrl } from '@/lib/assets'

interface ProfileFieldsState {
  name: string
  email: string
  phone: string
  birth_date: string
  notes: string
}

const EMPTY_FIELDS: ProfileFieldsState = {
  name: '',
  email: '',
  phone: '',
  birth_date: '',
  notes: '',
}

interface PasswordFieldsState {
  current_password: string
  password: string
  password_confirmation: string
}

const EMPTY_PASSWORD: PasswordFieldsState = {
  current_password: '',
  password: '',
  password_confirmation: '',
}

/**
 * Formulário de perfil do usuário autenticado (dados pessoais + troca de
 * senha). Compartilhado entre `/[slug]/minha-conta/perfil` (cliente) e
 * `/[slug]/dashboard/perfil` (salon_owner/salon_staff) — o perfil é do
 * usuário, não do tenant, então não há nada específico de salão aqui.
 *
 * Carrega os dados diretamente de `GET /auth/profile` (em vez de receber o
 * `user` do `useAuth` via prop) para não precisar espelhar prop -> state
 * num useEffect síncrono.
 */
export function ProfileForm() {
  const [fields, setFields] = useState<ProfileFieldsState>(EMPTY_FIELDS)
  const [avatarUrl, setAvatarUrl] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isUploadingAvatar, setIsUploadingAvatar] = useState(false)

  const [password, setPassword] = useState<PasswordFieldsState>(EMPTY_PASSWORD)
  const [isSavingPassword, setIsSavingPassword] = useState(false)

  const avatarInput = useRef<HTMLInputElement>(null)

  useEffect(() => {
    profileService
      .show()
      .then((res) => {
        const u = res.data.data
        setFields({
          name: u.name ?? '',
          email: u.email ?? '',
          phone: u.phone ?? '',
          birth_date: u.birth_date ?? '',
          notes: u.notes ?? '',
        })
        setAvatarUrl(u.avatar_url)
      })
      .catch(() => toast.error('Erro ao carregar seu perfil.'))
      .finally(() => setIsLoading(false))
  }, [])

  function setField(field: keyof ProfileFieldsState, value: string) {
    setFields((f) => ({ ...f, [field]: value }))
  }

  async function handleAvatarChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    setIsUploadingAvatar(true)
    try {
      const res = await profileService.uploadAvatar(file)
      setAvatarUrl(res.data.data.avatar_url)
      toast.success('Foto de perfil atualizada!')
    } catch (err) {
      toast.error(getSafeErrorMessage(err, 'Erro ao enviar a foto. Use uma imagem de até 2MB.'))
    } finally {
      setIsUploadingAvatar(false)
      if (avatarInput.current) avatarInput.current.value = ''
    }
  }

  function setPasswordField(field: keyof PasswordFieldsState, value: string) {
    setPassword((p) => ({ ...p, [field]: value }))
  }

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setIsSaving(true)
    try {
      const res = await profileService.update({
        name: fields.name.trim(),
        email: fields.email.trim(),
        phone: fields.phone.trim() || null,
        birth_date: fields.birth_date || null,
        notes: fields.notes.trim() || null,
      })
      const u = res.data.data
      setFields({
        name: u.name ?? '',
        email: u.email ?? '',
        phone: u.phone ?? '',
        birth_date: u.birth_date ?? '',
        notes: u.notes ?? '',
      })
      setAvatarUrl(u.avatar_url)
      toast.success('Perfil atualizado com sucesso!')
    } catch (err) {
      toast.error(getSafeErrorMessage(err, 'Erro ao salvar o perfil. Tente novamente.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handlePasswordSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()

    if (password.password !== password.password_confirmation) {
      toast.error('A confirmação de senha não confere com a nova senha.')
      return
    }

    setIsSavingPassword(true)
    try {
      await profileService.updatePassword(password)
      setPassword(EMPTY_PASSWORD)
      toast.success('Senha atualizada com sucesso!')
    } catch (err) {
      toast.error(getSafeErrorMessage(err, 'Erro ao trocar a senha. Verifique a senha atual.'))
    } finally {
      setIsSavingPassword(false)
    }
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center rounded-xl border bg-card py-24">
        <p className="text-sm text-muted-foreground animate-pulse">Carregando...</p>
      </div>
    )
  }

  const avatarPreview = assetUrl(avatarUrl)
  const initial = fields.name.trim().charAt(0).toUpperCase()

  return (
    <div className="space-y-6">
      <Card className="max-w-2xl p-6">
        <div className="mb-6 flex items-center gap-4">
          <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted">
            {avatarPreview ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={avatarPreview} alt="Foto de perfil" className="h-full w-full object-cover" />
            ) : initial ? (
              <span className="text-2xl font-semibold text-muted-foreground">{initial}</span>
            ) : (
              <UserRound className="h-8 w-8 text-muted-foreground" />
            )}
          </div>
          <div>
            <p className="mb-1.5 text-sm font-medium text-foreground">Foto de perfil</p>
            <input
              ref={avatarInput}
              type="file"
              accept="image/*"
              onChange={handleAvatarChange}
              className="hidden"
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => avatarInput.current?.click()}
              disabled={isUploadingAvatar}
            >
              <Upload className="mr-2 h-4 w-4" />
              {isUploadingAvatar ? 'Enviando...' : avatarPreview ? 'Trocar foto' : 'Enviar foto'}
            </Button>
          </div>
        </div>
        <Separator className="mb-5" />
        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="space-y-1.5">
            <Label htmlFor="profile-name">Nome</Label>
            <Input
              id="profile-name"
              required
              value={fields.name}
              onChange={(e) => setField('name', e.target.value)}
              disabled={isSaving}
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="profile-email">E-mail</Label>
            <Input
              id="profile-email"
              type="email"
              required
              value={fields.email}
              onChange={(e) => setField('email', e.target.value)}
              disabled={isSaving}
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="profile-phone">Telefone</Label>
            <Input
              id="profile-phone"
              placeholder="(42) 99999-0000"
              value={fields.phone}
              onChange={(e) => setField('phone', e.target.value)}
              disabled={isSaving}
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="profile-birth-date">Data de nascimento</Label>
            <Input
              id="profile-birth-date"
              type="date"
              value={fields.birth_date}
              onChange={(e) => setField('birth_date', e.target.value)}
              disabled={isSaving}
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="profile-notes">Observações</Label>
            <Textarea
              id="profile-notes"
              placeholder="Alergias, preferências ou qualquer informação que o salão deva saber"
              value={fields.notes}
              onChange={(e) => setField('notes', e.target.value)}
              disabled={isSaving}
              rows={3}
            />
          </div>

          <Button type="submit" disabled={isSaving}>
            {isSaving ? 'Salvando...' : 'Salvar alterações'}
          </Button>
        </form>
      </Card>

      <Card className="max-w-2xl p-6">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-foreground">Trocar senha</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Informe sua senha atual para definir uma nova.
          </p>
        </div>
        <Separator className="mb-5" />
        <form onSubmit={handlePasswordSubmit} className="space-y-5">
          <div className="space-y-1.5">
            <Label htmlFor="current-password">Senha atual</Label>
            <Input
              id="current-password"
              type="password"
              required
              value={password.current_password}
              onChange={(e) => setPasswordField('current_password', e.target.value)}
              disabled={isSavingPassword}
            />
          </div>

          <div className="grid gap-5 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="new-password">Nova senha</Label>
              <Input
                id="new-password"
                type="password"
                required
                minLength={8}
                value={password.password}
                onChange={(e) => setPasswordField('password', e.target.value)}
                disabled={isSavingPassword}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="new-password-confirmation">Confirmar nova senha</Label>
              <Input
                id="new-password-confirmation"
                type="password"
                required
                minLength={8}
                value={password.password_confirmation}
                onChange={(e) => setPasswordField('password_confirmation', e.target.value)}
                disabled={isSavingPassword}
              />
            </div>
          </div>

          <Button type="submit" disabled={isSavingPassword}>
            {isSavingPassword ? 'Salvando...' : 'Trocar senha'}
          </Button>
        </form>
      </Card>
    </div>
  )
}
