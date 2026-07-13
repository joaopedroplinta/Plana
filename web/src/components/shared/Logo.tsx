import { cn } from '@/lib/utils'

interface LogoProps {
  className?: string
  markSize?: number
  showWordmark?: boolean
}

export function Logo({ className, markSize = 22, showWordmark = true }: LogoProps) {
  return (
    <span className={cn('inline-flex items-center gap-2', className)}>
      <svg
        width={markSize}
        height={markSize}
        viewBox="0 0 100 100"
        aria-hidden="true"
        className="shrink-0"
      >
        <rect x="6" y="6" width="88" height="88" rx="22" fill="#006768" />
        <rect x="24" y="38" width="52" height="12" rx="6" fill="#ffffff" />
        <rect x="24" y="58" width="32" height="12" rx="6" fill="#9ad335" />
      </svg>
      {showWordmark && (
        <span className="font-bold tracking-tight text-current">Plana</span>
      )}
    </span>
  )
}
