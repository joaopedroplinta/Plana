import { isAxiosError } from 'axios'
import type { ApiError } from '@/types/index'

/**
 * Mensagem segura pra mostrar ao usuário. Só confia no `message` vindo da
 * API quando o status está em `trustedStatuses` — statuses cuja mensagem é
 * sempre um literal fixo escrito à mão no controller/Form Request (ex:
 * validação 422, "Credenciais inválidas" no 401), nunca o texto de uma
 * exceção. Qualquer outro status (500, erro de rede/CORS) usa o fallback:
 * fora de produção (APP_DEBUG=true) o backend pode devolver o texto cru de
 * uma exceção interna, e isso nunca deve chegar na tela do usuário final.
 */
export function getSafeErrorMessage(
  err: unknown,
  fallback: string,
  trustedStatuses: number[] = [422],
): string {
  if (!isAxiosError(err)) return 'Erro inesperado. Tente novamente.'
  if (trustedStatuses.includes(err.response?.status ?? 0)) {
    const apiError = err.response?.data as ApiError | undefined
    return apiError?.message ?? fallback
  }
  return fallback
}
