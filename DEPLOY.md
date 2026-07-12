# Deploy — Oracle Cloud Free Tier

Guia pra colocar o Agendei no ar de graça, numa VM **always-free** da Oracle Cloud
(sem expiração, sem cold start — diferente de PaaS gratuitos que dormem ou apagam
o banco depois de um tempo). Leva ~30-40 min na primeira vez.

Você precisa fazer esta parte manualmente (conta pessoal, cartão pra verificação
de identidade da Oracle — não é cobrado no tier free, um domínio próprio e acesso
ao painel de DNS dele). Depois que a VM estiver de pé, o resto (subir os
containers) é copiar e colar.

## 1. Criar a VM

1. Crie conta em [cloud.oracle.com](https://cloud.oracle.com) (pede cartão só pra verificação de identidade; o tier Always Free não cobra)
2. **Menu ☰ → Compute → Instances → Create Instance**
3. Em **Image and shape**: mantenha a imagem **Canonical Ubuntu** (é a que os comandos abaixo assumem) e troque o shape pra **Ampere (ARM), VM.Standard.A1.Flex** — o always-free dá direito a 4 OCPUs / 24 GB RAM, de longe o melhor tier gratuito de VM que existe. (O x86 free tier é bem mais fraco: 2 VMs de 1 OCPU/1GB.)
4. Em **Add SSH keys**, gere um par novo e baixe a chave privada (ou cole sua pública existente)
5. Em **Networking**, deixe criar uma VCN nova com IP público
6. Create — aguarde o estado ficar "Running" e anote o IP público

> Se a criação falhar com "Out of capacity", é uma limitação conhecida do tier
> Ampere em algumas regiões — tente outro Availability Domain ou volte a tentar
> mais tarde.

## 2. Abrir as portas 80 e 443

Por padrão só a porta 22 (SSH) está liberada.

1. **Menu ☰ → Networking → Virtual Cloud Networks** → sua VCN → **Security Lists** → a lista padrão
2. **Add Ingress Rules** duas vezes: `0.0.0.0/0` → porta `80` (TCP) e `0.0.0.0/0` → porta `443` (TCP)

## 3. Apontar o DNS

No painel do seu domínio, crie dois registros `A` apontando pro IP público da VM:

```
seudominio.com.br      A   <ip-da-vm>
api.seudominio.com.br  A   <ip-da-vm>
```

A Caddy (já configurada no `docker-compose.prod.yml`) emite os certificados
Let's Encrypt sozinha na primeira request — não precisa configurar TLS manualmente.

## 4. Instalar Docker na VM

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

## 5. Clonar e configurar

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

## 6. Subir a stack

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

## 7. Criar o super admin

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

## Manutenção

```bash
# Deploy de uma nova versão
git pull
docker compose -f docker-compose.prod.yml up -d --build

# Logs
docker compose -f docker-compose.prod.yml logs -f [serviço]

# Backup do banco
docker compose -f docker-compose.prod.yml exec postgres pg_dump -U postgres agendamentos > backup-$(date +%F).sql
```

## Alternativas mais simples (com trade-offs)

Se preferir não gerenciar uma VM:

- **Vercel** (frontend) + **Railway** (API + worker) + **Neon** (Postgres) — melhor
  experiência de deploy, mas a API deixa de ser 100% gratuita depois do trial de
  crédito da Railway (~30 dias)
- **Render** — tudo numa plataforma só, 100% gratuito, mas os serviços "dormem"
  após 15 min sem tráfego (cold start no primeiro acesso) e o Postgres free
  expira e é apagado após 30 dias
