import { NextRequest, NextResponse } from 'next/server'

const PROTECTED_PATTERNS = [/^\/admin(\/|$)/, /^\/(super-admin)(\/|$)/]

export function proxy(request: NextRequest): NextResponse {
  const { pathname } = request.nextUrl
  const isProtected = PROTECTED_PATTERNS.some((pattern) => pattern.test(pathname))

  if (!isProtected) {
    return NextResponse.next()
  }

  const token = request.cookies.get('token')?.value

  if (!token) {
    const loginUrl = request.nextUrl.clone()
    loginUrl.pathname = '/login'
    loginUrl.searchParams.set('redirect', pathname)
    return NextResponse.redirect(loginUrl)
  }

  return NextResponse.next()
}

export const config = {
  matcher: ['/admin/:path*', '/(super-admin)/:path*'],
}
