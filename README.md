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

### Modo navegador (padrão)

Funciona com qualquer impressora instalada no Windows (incluindo térmica 80mm):

```env
PRINTING_ENABLED=true
PRINTING_DRIVER=browser
PRINTING_AUTO_ON_CREATE=true
```

Ao criar um pedido, abre automaticamente a tela de impressão. Também disponível em **Pedidos → Imprimir**.

Configure a impressora térmica como **padrão** no Windows e selecione-a no diálogo de impressão.

### Modo rede (impressora térmica IP)

Para impressoras ESC/POS na rede (porta 9100):

```env
PRINTING_DRIVER=network
PRINTING_NETWORK_HOST=192.168.0.100
PRINTING_NETWORK_PORT=9100
```

A comanda é enviada automaticamente ao criar pedido (painel ou WhatsApp).

## Stack

- Laravel 13
- Laravel Breeze (autenticação)
- Tailwind CSS
- SQLite (desenvolvimento)
