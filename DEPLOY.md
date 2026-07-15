# Deploy

Duas formas de colocar o Plana no ar de graça. Escolha uma:

| | Opção A — Oracle Cloud (VM) | Opção B — Render + Neon (PaaS) |
|---|---|---|
| Cadastro | Mais burocrático (identidade, cartão, escolha de região) | Rápido (login com GitHub) |
| Custo | Grátis pra sempre | Grátis pra sempre |
| Cold start | Não tem | Serviços dormem após 15min sem tráfego (~1min pra acordar) |
| Fila/scheduler | Worker e cron rodando de verdade | Sem worker persistente — fila roda inline, scheduler via cron externo (ver Opção B) |
| Domínio | Precisa de domínio próprio (HTTPS via Caddy) | Não precisa — ganha subdomínio `.onrender.com` com HTTPS automático (dá pra trocar por domínio próprio depois) |
| Esforço | ~30-40min, mais manual | ~15min, mais automatizado (Blueprint) |

Se você já tem paciência pra Oracle e quer a experiência completa (fila e
scheduler rodando de verdade, sem cold start), vá de **Opção A**. Se quer
algo no ar rápido e não se importa com os trade-offs do free tier, vá de
**Opção B**.

## Opção A — Oracle Cloud Free Tier

VM **always-free** (sem expiração, sem cold start). Leva ~30-40 min na
primeira vez.

Você precisa fazer esta parte manualmente (conta pessoal, cartão pra verificação
de identidade da Oracle — não é cobrado no tier free, um domínio próprio e acesso
ao painel de DNS dele). Depois que a VM estiver de pé, o resto (subir os
containers) é copiar e colar.

### 1. Criar a VM

1. Crie conta em [cloud.oracle.com](https://cloud.oracle.com) (pede cartão só pra verificação de identidade; o tier Always Free não cobra)
2. **Menu ☰ → Compute → Instances → Create Instance**
3. Em **Image and shape**: mantenha a imagem **Canonical Ubuntu** (é a que os comandos abaixo assumem) e troque o shape pra **Ampere (ARM), VM.Standard.A1.Flex** — o always-free dá direito a 4 OCPUs / 24 GB RAM, de longe o melhor tier gratuito de VM que existe. (O x86 free tier é bem mais fraco: 2 VMs de 1 OCPU/1GB.)
4. Em **Add SSH keys**, gere um par novo e baixe a chave privada (ou cole sua pública existente)
5. Em **Networking**, deixe criar uma VCN nova com IP público
6. Create — aguarde o estado ficar "Running" e anote o IP público

> Se a criação falhar com "Out of capacity", é uma limitação conhecida do tier
> Ampere em algumas regiões — tente outro Availability Domain ou volte a tentar
> mais tarde.

### 2. Abrir as portas 80 e 443

Por padrão só a porta 22 (SSH) está liberada.

1. **Menu ☰ → Networking → Virtual Cloud Networks** → sua VCN → **Security Lists** → a lista padrão
2. **Add Ingress Rules** duas vezes: `0.0.0.0/0` → porta `80` (TCP) e `0.0.0.0/0` → porta `443` (TCP)

### 3. Apontar o DNS

No painel do seu domínio, crie dois registros `A` apontando pro IP público da VM:

```
seudominio.com.br      A   <ip-da-vm>
api.seudominio.com.br  A   <ip-da-vm>
```

A Caddy (já configurada no `docker-compose.prod.yml`) emite os certificados
Let's Encrypt sozinha na primeira request — não precisa configurar TLS manualmente.

### 4. Instalar Docker na VM

```bash
ssh -i sua-chave.pem ubuntu@<ip-da-vm>

curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker

# Firewall interno da própria VM (as imagens Ubuntu da Oracle vêm com iptables restritivo por padrão)
sudo apt-get install -y iptables-persistent
sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

### 5. Clonar e configurar

```bash
git clone https://github.com/joaopedroplinta/sistema-agendamentos.git
cd sistema-agendamentos

cp .env.prod.example .env
nano .env   # preencha domínios, senha do Postgres, MercadoPago e SMTP reais
```

Gere o `APP_KEY` antes de subir a stack:

```bash
docker compose -f docker-compose.prod.yml run --rm api php artisan key:generate --show
# cole o valor gerado (base64:...) na linha APP_KEY= do .env
```

### 6. Subir a stack

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Isso builda a API e o frontend localmente (o frontend *precisa* ser buildado
com o `NEXT_PUBLIC_API_URL` real, por isso não usamos a imagem pré-pronta do
GHCR pra ele — ver nota em `.github/workflows/release.yml`), sobe Postgres,
Redis, worker de fila, agendador (lembretes + downgrade de assinatura) e a
Caddy servindo `https://seudominio.com.br` e `https://api.seudominio.com.br`
com HTTPS automático.

Acompanhe o primeiro boot (migrations rodando, certificado sendo emitido):

```bash
docker compose -f docker-compose.prod.yml logs -f api caddy
```

### 7. Criar o super admin

**Não rode `php artisan db:seed`** em produção — o `DatabaseSeeder` do projeto
cria dados de demonstração (tenant, owner e super admin) com a senha fixa
`password`, pensados só pra ambiente local.

Registre o primeiro salão pelo fluxo normal (`https://seudominio.com.br/register`)
e promova o usuário que vai administrar a plataforma a `super_admin` direto no
banco:

```bash
docker compose -f docker-compose.prod.yml exec api php artisan tinker
>>> $user = \App\Models\User::where('email', 'voce@seudominio.com.br')->firstOrFail();
>>> $user->assignRole('super_admin');
```

### Manutenção

```bash
# Deploy de uma nova versão
git pull
docker compose -f docker-compose.prod.yml up -d --build

# Logs
docker compose -f docker-compose.prod.yml logs -f [serviço]

# Backup do banco
docker compose -f docker-compose.prod.yml exec postgres pg_dump -U postgres agendamentos > backup-$(date +%F).sql
```

## Opção B — Render + Neon

Sem VM pra gerenciar. ~15 min. Usa o Blueprint `render.yaml` do repositório
pra criar os dois serviços (`plana-api`, `plana-web`) de uma vez.

**Trade-off importante:** o plano free do Render não tem worker nem cron
persistente. Pra contornar isso sem custo:
- **Fila** (`QUEUE_CONNECTION=sync`) processa e-mails/webhooks na hora, dentro
  da própria requisição — sem worker separado
- **Scheduler** (lembrete de agendamento ~24h antes, downgrade de assinatura
  expirada) é disparado por um cron externo gratuito (`cron-job.org`) batendo
  numa rota protegida da API a cada poucos minutos

Os serviços web também "dormem" após 15 min sem tráfego — a primeira
requisição depois disso demora uns segundos a mais (cold start).

### 1. Criar o banco no Neon

1. Crie conta em [neon.tech](https://neon.tech) (login com GitHub, sem cartão)
2. **Create a project** → escolha uma região próxima
3. No dashboard do projeto, copie a **Connection string** (formato
   `postgres://usuario:senha@host/banco?sslmode=require`) — é o `DB_URL` do
   passo 3

### 2. Deploy do Blueprint no Render

1. Crie conta em [render.com](https://render.com) (login com GitHub)
2. **New → Blueprint**, selecione o fork/repositório do Plana — o Render lê o
   `render.yaml` da raiz e propõe criar `plana-api` e `plana-web`
3. Clique em **Apply** — o primeiro deploy vai falhar ou ficar incompleto,
   porque faltam variáveis obrigatórias (próximo passo). Isso é esperado.

### 3. Preencher as variáveis de ambiente

No dashboard de cada serviço → **Environment**, preencha o que ficou marcado
`sync: false` no `render.yaml`:

**`plana-api`**
| Variável | Valor |
|---|---|
| `APP_KEY` | gere localmente: `cd api && php artisan key:generate --show` |
| `APP_URL` | URL pública do `plana-api` (ex: `https://plana-api.onrender.com`) |
| `FRONTEND_URL` | URL pública do `plana-web` |
| `DB_URL` | connection string do Neon (passo 1) |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | credenciais SMTP reais — sem isso, nenhum e-mail sai |
| `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_PUBLIC_KEY`, `MERCADOPAGO_WEBHOOK_SECRET` | credenciais de produção do MercadoPago |
| `CORS_ALLOWED_ORIGINS` | URL pública do `plana-web` |

`SCHEDULER_TOKEN` já vem gerado automaticamente pelo Render (`generateValue: true`)
— copie o valor gerado, ele é usado no passo 5.

**`plana-web`**
| Variável | Valor |
|---|---|
| `NEXT_PUBLIC_API_URL` | URL pública do `plana-api` + `/api/v1` |
| `NEXT_PUBLIC_MERCADOPAGO_PUBLIC_KEY` | mesma public key do MercadoPago |

> `NEXT_PUBLIC_*` é inlinada em build-time — depois de preencher, clique em
> **Manual Deploy → Clear build cache & deploy** no `plana-web` pra rebuildar
> com o valor certo.

Depois de preencher tudo, dispare **Manual Deploy** nos dois serviços.

> **Atenção — checklist pós-deploy (já causou 2 incidentes em produção):**
> `DB_URL` e `CORS_ALLOWED_ORIGINS` são `sync: false` — se ficarem em branco,
> o `plana-api` sobe normalmente mas quebra silenciosamente (cai pro host
> `127.0.0.1:5432` do Postgres local, que não existe no container, ou
> bloqueia toda chamada do frontend por CORS). Depois do Manual Deploy:
> 1. Abra os **Logs** do `plana-api` e confirme a linha do
>    `php artisan migrate --force` rodando sem erro (só roda se
>    `RUN_MIGRATIONS=1` estiver setado — sem Shell disponível no plano free,
>    esse é o único jeito de rodar migration: garantir a env var e forçar um
>    redeploy em **Manual Deploy → Deploy latest commit**).
> 2. Teste `/register` e `/login` de verdade no `plana-web` publicado, não só
>    o healthcheck (`/up`) — ele não toca o banco nem depende de CORS, então
>    fica verde mesmo com essas duas variáveis erradas.

### 4. Criar o super admin

Mesma lógica da Opção A — **não rode `php artisan db:seed`** em produção.
Registre o primeiro negócio pelo fluxo normal
(`https://<url-do-plana-web>/register`) e promova o usuário a `super_admin`
pelo **Shell** do serviço `plana-api` no dashboard do Render:

```bash
php artisan tinker
>>> $user = \App\Models\User::where('email', 'voce@example.com')->firstOrFail();
>>> $user->assignRole('super_admin');
```

### 5. Configurar o cron externo do scheduler

1. Crie conta grátis em [cron-job.org](https://cron-job.org)
2. **Create cronjob**:
   - URL: `https://<url-do-plana-api>/api/v1/system/scheduler`
   - Method: `POST`
   - Schedule: a cada 5 minutos
   - Em **Advanced → Custom headers**, adicione `X-Scheduler-Token: <valor do SCHEDULER_TOKEN>`
3. Salve e rode um teste manual — deve responder `200` com `{"output": "..."}`

### Alternativa híbrida

Nada impede misturar: **Vercel** (frontend, sempre grátis, zero cold start)
+ **Render** só pra API + **Neon** (Postgres). Mesma configuração de
variáveis acima, só troca onde o `plana-web` roda.
