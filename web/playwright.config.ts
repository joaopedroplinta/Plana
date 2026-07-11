import { defineConfig, devices } from '@playwright/test'

/**
 * Config dos testes E2E.
 *
 * Decisão de design: NÃO usamos a opção `webServer` do Playwright aqui.
 * Tanto a API (Laravel) quanto o web (Next.js) precisam estar de pé para
 * os golden paths funcionarem, e a API não é algo que o Playwright possa
 * gerenciar (outro runtime, precisa de migrate/seed antes de subir). Para
 * manter o setup local e o do CI simétricos, os dois servidores são
 * subidos manualmente ANTES de rodar `npx playwright test`:
 *
 *   cd api && php artisan serve                                   # :8000
 *   cd web && npm run build && npm run start                      # :3000
 *   cd web && npx playwright test
 *
 * Ver `.github/workflows/ci.yml` (job `e2e`) para o equivalente em CI,
 * incluindo o health check que aguarda os dois responderem antes de
 * rodar os testes.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  // Os testes reutilizam o tenant seed `salao-demo` (mesmos profissionais,
  // mesma agenda) — rodar em paralelo aumentaria o risco de dois testes
  // disputarem o mesmo horário. Um worker mantém a suíte determinística.
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['html', { open: 'never' }], ['list']] : 'list',
  timeout: 30_000,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:3000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
})
