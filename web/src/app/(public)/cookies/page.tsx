import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Política de Cookies',
}

export default function CookiesPage() {
  return (
    <div className="mx-auto max-w-2xl px-4 py-12">
      <h1 className="text-2xl font-bold text-foreground">Política de Cookies</h1>
      <p className="mt-2 text-sm text-muted-foreground">Última atualização: julho de 2026</p>

      <div className="mt-8 space-y-6 text-sm leading-relaxed text-foreground">
        <section>
          <h2 className="font-semibold">O que são cookies</h2>
          <p className="mt-2 text-muted-foreground">
            Cookies são pequenos arquivos guardados no seu navegador que permitem que um site
            lembre de informações entre visitas, como manter você conectado à sua conta.
          </p>
        </section>

        <section>
          <h2 className="font-semibold">Quais cookies usamos</h2>
          <p className="mt-2 text-muted-foreground">
            O Plana usa apenas um cookie essencial (<code>token</code>), necessário para manter
            sua sessão ativa depois do login. Sem ele, você precisaria entrar novamente a cada
            página visitada. Não usamos cookies de publicidade, rastreamento entre sites ou
            análise de terceiros.
          </p>
        </section>

        <section>
          <h2 className="font-semibold">Como gerenciar</h2>
          <p className="mt-2 text-muted-foreground">
            Você pode bloquear ou apagar cookies a qualquer momento nas configurações do seu
            navegador. Como o cookie usado aqui é essencial para o login, bloqueá-lo impede que
            você permaneça conectado à sua conta.
          </p>
        </section>
      </div>
    </div>
  )
}
