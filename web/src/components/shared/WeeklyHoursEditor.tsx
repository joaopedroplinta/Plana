'use client'

import { Input } from '@/components/ui/input'

/** Uma linha do editor: dia da semana com toggle liga/desliga e faixa de horário. */
export interface DayHours {
  day_of_week: number
  enabled: boolean
  start: string
  end: string
}

/** Segunda primeiro, domingo por último — ordem de negócio. */
const DAYS: { value: number; label: string }[] = [
  { value: 1, label: 'Segunda' },
  { value: 2, label: 'Terça' },
  { value: 3, label: 'Quarta' },
  { value: 4, label: 'Quinta' },
  { value: 5, label: 'Sexta' },
  { value: 6, label: 'Sábado' },
  { value: 0, label: 'Domingo' },
]

/** Semana em branco (todos os dias desligados, 09:00–18:00 como padrão). */
export function emptyWeek(): DayHours[] {
  return DAYS.map((d) => ({ day_of_week: d.value, enabled: false, start: '09:00', end: '18:00' }))
}

interface WeeklyHoursEditorProps {
  value: DayHours[]
  onChange: (next: DayHours[]) => void
  disabled?: boolean
  /** Texto ao lado do dia quando desligado (ex.: "Fechado" / "Folga"). */
  offLabel?: string
}

export function WeeklyHoursEditor({
  value,
  onChange,
  disabled = false,
  offLabel = 'Fechado',
}: WeeklyHoursEditorProps) {
  function update(day: number, patch: Partial<DayHours>) {
    onChange(value.map((d) => (d.day_of_week === day ? { ...d, ...patch } : d)))
  }

  return (
    <div className="space-y-2">
      {DAYS.map((d) => {
        const row = value.find((v) => v.day_of_week === d.value)
        if (!row) return null

        return (
          <div key={d.value} className="flex items-center gap-3">
            <button
              type="button"
              role="switch"
              aria-checked={row.enabled}
              aria-label={d.label}
              onClick={() => update(d.value, { enabled: !row.enabled })}
              disabled={disabled}
              className={[
                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-50',
                row.enabled ? 'bg-primary' : 'bg-muted',
              ].join(' ')}
            >
              <span
                className={[
                  'inline-block h-4 w-4 rounded-full bg-white shadow transition-transform',
                  row.enabled ? 'translate-x-6' : 'translate-x-1',
                ].join(' ')}
              />
            </button>
            <span className="w-20 shrink-0 text-sm text-foreground">{d.label}</span>
            {row.enabled ? (
              <div className="flex items-center gap-2">
                <Input
                  type="time"
                  value={row.start}
                  onChange={(e) => update(d.value, { start: e.target.value })}
                  disabled={disabled}
                  className="w-32"
                />
                <span className="text-sm text-muted-foreground">até</span>
                <Input
                  type="time"
                  value={row.end}
                  onChange={(e) => update(d.value, { end: e.target.value })}
                  disabled={disabled}
                  className="w-32"
                />
              </div>
            ) : (
              <span className="text-sm text-muted-foreground">{offLabel}</span>
            )}
          </div>
        )
      })}
    </div>
  )
}
