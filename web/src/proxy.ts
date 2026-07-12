import { NextRequest, NextResponse } from 'next/server'

const PUBLIC_PATHS = ['/', '/login', '/register', '/forgot-password', '/reset-password']

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl
  const token = request.cookies.get('token')?.value

  // Arquivos estáticos e rotas de API interna
  const isStatic =
    pathname.startsWith('/_next') ||
    pathname.startsWith('/api') ||
    pathname.includes('.')

  if (isStatic) {
    return NextResponse.next()
  }

  // Rotas públicas — sempre liberadas. Não redirecionamos aqui mesmo se
  // houver cookie `token`: o proxy roda no edge e não tem como validar se
  // o token ainda é válido (só checar a presença dele já causou um bug real
  // — um cookie órfão/inválido travava o acesso a /login para sempre, sem
  // forma de deslogar pela UI). Quem decide "já autenticado, redirecionar
  // para longe do login" é o próprio client-side (useAuth, que valida via
  // /auth/me antes de considerar o usuário autenticado).
  const isPublic = PUBLIC_PATHS.some(
    (p) => pathname === p || pathname.startsWith(p + '?'),
  )

  if (isPublic) {
    return NextResponse.next()
  }

  // Rota de dashboard do salão ou super-admin → requer token
  const isDashboard =
    pathname.includes('/dashboard') || pathname.startsWith('/super-admin')

  if (isDashboard && !token) {
    const url = new URL('/login', request.url)
    url.searchParams.set('redirect', pathname)
    return NextResponse.redirect(url)
  }

  return NextResponse.next()
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
}
