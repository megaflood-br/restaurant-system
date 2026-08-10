# Restaurant System

Sistema de gestão de restaurante em Laravel com cardápio, pedidos e controle de estoque.

## Módulos

- **Dashboard** — visão geral com métricas, pedidos em andamento e alertas de estoque baixo
- **Categorias** — organização do cardápio
- **Cardápio** — produtos com preço e disponibilidade
- **Pedidos** — salão, delivery e retirada com acompanhamento de status
- **CRM / Clientes** — cadastro, perfil, histórico de pedidos e interações
- **WhatsApp (Evolution API)** — envio, recebimento via webhook e notificações de pedido
- **Estoque** — ingredientes com movimentações de entrada/saída

## Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- SQLite (padrão) ou MySQL

## Instalação

```bash
cd Projects/restaurant-system
composer install
cp .env.example .env   # se necessário
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Acesse: http://localhost:8000

## Login de demonstração

| Campo | Valor |
|-------|-------|
| E-mail | admin@restaurante.com |
| Senha | password |

## Usar MySQL

No `.env`, configure:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=restaurant_system
DB_USERNAME=root
DB_PASSWORD=
```

Depois execute `php artisan migrate --seed`.

## Integração WhatsApp (Evolution API)

Configure no `.env`:

```env
EVOLUTION_API_ENABLED=true
EVOLUTION_API_URL=http://localhost:8080
EVOLUTION_API_KEY=sua-api-key
EVOLUTION_API_INSTANCE=nome-da-instancia
EVOLUTION_WEBHOOK_SECRET=segredo-opcional
EVOLUTION_NOTIFY_ORDER_STATUS=true
```

Na Evolution API, configure o webhook da instância:

- **URL:** `{APP_URL}/api/webhooks/evolution`
- **Evento:** `MESSAGES_UPSERT`
- **Header (opcional):** `x-webhook-secret: {EVOLUTION_WEBHOOK_SECRET}`

### Bot automático (receber mensagens)

Com o webhook ativo e `EVOLUTION_AUTO_REPLY=true`, o cliente pode:

1. Enviar **CARDAPIO** → recebe a lista de produtos
2. Enviar **1 2** → adiciona 2x do item 1
3. Enviar **FINALIZAR** → vê o resumo
4. Enviar **CONFIRMAR** → pedido criado no sistema
5. Enviar **STATUS** → consulta último pedido

O pedido aparece em **Pedidos** no painel admin.

## Impressão de comandas

Configure em **Configurações → Impressão** (não depende de variáveis `PRINTING_*` no `.env`).

### Modo navegador (padrão)

Funciona com qualquer impressora instalada no Windows (incluindo térmica 80mm).
Ao criar um pedido (com autoimpressão ligada), abre a tela de impressão do navegador.
Também disponível em **Pedidos → Imprimir**.

Configure a impressora térmica como **padrão** no Windows e selecione-a no diálogo de impressão.

### Modo rede (impressora térmica IP / ESC/POS)

Para impressoras na rede local (porta raw `9100`, protocolo EPSON ESC/POS):

1. Modo: **Rede IP**
2. IP: o do relatório da impressora (ex.: `192.168.1.100`)
3. Porta: `9100`
4. Largura: `48` para bobina 80mm (ou `32` para 58mm)
5. Salve e clique em **Testar impressora de rede**

**Importante:** o servidor PHP precisa alcançar o IP da impressora. Se o painel roda na
nuvem/VPS e a impressora só existe no Wi‑Fi do restaurante, a conexão falha — use o modo
**Navegador** ou hospede o sistema na mesma rede da impressora.

A comanda de cozinha é enviada automaticamente ao criar pedido (painel ou WhatsApp) quando
o modo rede está ativo.
## Stack

- Laravel 13
- Laravel Breeze (autenticação)
- Tailwind CSS
- SQLite (desenvolvimento)
