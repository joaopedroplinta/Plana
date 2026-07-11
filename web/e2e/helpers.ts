import { expect, type Page } from '@playwright/test'

/**
 * Tenant seed usado pelos golden paths (ver api/database/seeders/DatabaseSeeder.php).
 * Tem owner, serviços, profissionais e agenda (seg–sáb, 09:00–18:00) já prontos.
 */
export const SALON_SLUG = 'salao-demo'
export const SALON_OWNER_EMAIL = 'owner@salao-demo.com.br'
export const SALON_OWNER_PASSWORD = 'password'

export interface ClientCredentials {
  name: string
  email: string
  password: string
}

let clientCounter = 0

/** Gera credenciais únicas de cliente para evitar colisão de e-mail entre specs/execuções. */
export function uniqueClient(prefix: string): ClientCredentials {
  clientCounter += 1
  const stamp = `${Date.now()}-${clientCounter}`
  return {
    name: `Cliente E2E ${prefix} ${stamp}`,
    email: `${prefix}-${stamp}@e2e-test.dev`,
    password: 'SenhaForte123',
  }
}

/**
 * Registra um cliente novo via `/register`. Se `redirect` for informado, replica o
 * fluxo real de "agendar exige conta" (o formulário já nasce no modo "cliente" e,
 * ao concluir, o usuário volta direto pra página de origem, já autenticado).
 */
export async function registerClient(
  page: Page,
  credentials: ClientCredentials,
  options: { redirect?: string } = {},
): Promise<void> {
  const url = options.redirect
    ? `/register?redirect=${encodeURIComponent(options.redirect)}`
    : '/register'
  await page.goto(url)

  // Sem `redirect` o tipo de conta padrão é "owner" — precisa selecionar "cliente".
  if (!options.redirect) {
    await page.getByRole('button', { name: 'Sou cliente' }).click()
  }

  await page.getByLabel('Seu nome').fill(credentials.name)
  await page.getByLabel('E-mail').fill(credentials.email)
  await page.getByLabel('Senha', { exact: true }).fill(credentials.password)
  await page.getByLabel('Confirmar senha').fill(credentials.password)
  await page.getByRole('button', { name: 'Criar conta grátis' }).click()

  if (options.redirect) {
    await page.waitForURL((url) => url.pathname === options.redirect)
  } else {
    await page.waitForURL((url) => url.pathname === '/')
  }
}

export async function login(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('E-mail').fill(email)
  await page.getByLabel('Senha', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Entrar' }).click()
}

/**
 * Data (YYYY-MM-DD) usada para agendar. O seed dá expediente seg–sáb (sem domingo),
 * então usamos múltiplos de 7 dias a partir de hoje (mantém o mesmo dia da semana,
 * garantindo espaçamento estável entre specs) e corrige pro dia seguinte se cair
 * num domingo (aplica a mesma correção pra todo `weeksAhead`, então a distância
 * de 7 dias entre eles nunca colide). Cada spec usa um `weeksAhead` diferente para
 * não disputar o mesmo horário do mesmo profissional/serviço "padrão" (o primeiro
 * da lista, sempre escolhido pelos helpers de booking).
 */
export function bookingDate(weeksAhead: number): string {
  const date = new Date()
  date.setHours(0, 0, 0, 0)
  date.setDate(date.getDate() + weeksAhead * 7)
  if (date.getDay() === 0) {
    date.setDate(date.getDate() + 1)
  }
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export interface BookedAppointment {
  serviceName: string
  professionalName: string
  date: string
}

/**
 * Preenche o wizard de agendamento (`/[slug]/booking`) do início ao fim, sempre
 * escolhendo o primeiro serviço/profissional/horário disponíveis, e finaliza com
 * "pagar no local" (evita depender de integração com MercadoPago nesta rodada).
 * Assume que o usuário já está autenticado.
 */
export async function bookFirstAvailableSlot(
  page: Page,
  slug: string,
  weeksAhead: number,
): Promise<BookedAppointment> {
  await page.goto(`/${slug}/booking`)

  const serviceCard = page.getByTestId('service-option').first()
  await expect(serviceCard).toBeVisible()
  const serviceName = (await serviceCard.locator('p').first().innerText()).trim()
  await serviceCard.click()

  const professionalCard = page.getByTestId('professional-option').first()
  await expect(professionalCard).toBeVisible()
  const professionalName = (await professionalCard.locator('p').first().innerText()).trim()
  await professionalCard.click()

  const date = bookingDate(weeksAhead)
  await page.getByLabel('Data', { exact: true }).fill(date)
  await page.getByRole('button', { name: 'Ver horários disponíveis' }).click()

  const slotButton = page.getByRole('button', { name: /^\d{2}:\d{2}\s.\s\d{2}:\d{2}$/ }).first()
  await expect(slotButton).toBeVisible()
  await slotButton.click()

  await page.getByRole('button', { name: 'Confirmar Agendamento' }).click()

  await page
    .getByRole('button', { name: 'Pagar no local — finalizar sem pagamento online' })
    .click()

  await expect(page.getByRole('heading', { name: 'Agendamento realizado!' })).toBeVisible()

  return { serviceName, professionalName, date }
}
