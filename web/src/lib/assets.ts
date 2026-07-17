/**
 * Converte um caminho de arquivo devolvido pela API (ex.: `/storage/logos/x.png`)
 * na URL absoluta servida pelo host da API. A API expõe os arquivos em
 * `<origin>/storage/...`, enquanto `NEXT_PUBLIC_API_URL` aponta para `<origin>/api/v1`.
 * Caminhos já absolutos (http/https/data) são devolvidos sem alteração.
 */
export function assetUrl(path: string | null | undefined): string | null {
  if (!path) return null
  if (/^(https?:|data:)/.test(path)) return path

  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? 'http://127.0.0.1:8000/api/v1'
  const origin = apiUrl.replace(/\/api\/v1\/?$/, '')

  return `${origin}${path.startsWith('/') ? '' : '/'}${path}`
}
