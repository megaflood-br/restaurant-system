# AGENTS.md

## Cursor Cloud specific instructions

Laravel 13 restaurant-management app (PHP 8.3, SQLite by default). See `README.md` for the
product overview and the full manual setup steps; standard commands live in `composer.json`
(`scripts`) and `package.json` (`scripts`). Notes below are the non-obvious bits.

### Services
- **Web app** (`php artisan serve`, default `http://localhost:8000`) — the Laravel HTTP server.
- **Vite dev server** (`npm run dev`) — serves/hot-reloads frontend assets in development. Do
  NOT rely on `npm run build` during development; in dev mode the Blade `@vite` directive loads
  assets from the running Vite dev server. If Vite is not running, run `npm run build` once so
  compiled assets exist under `public/build`.
- `composer dev` runs server + queue + `pail` logs + Vite together via `concurrently` (handy but
  not required just to view the app).

### First-run / DB setup (only if missing)
`.env` and `database/database.sqlite` are gitignored and are NOT recreated by the update script,
so they persist via the VM snapshot. If starting from a state where they are missing (or after
new migrations land), run:

```
cp .env.example .env            # only if .env is missing
touch database/database.sqlite  # only if the file is missing
php artisan key:generate        # only if APP_KEY is empty in .env
php artisan migrate --seed      # applies new migrations; seeds demo data
```

`php artisan migrate` is safe to re-run (only pending migrations apply).

### Demo login
- Admin: `admin@restaurante.com` / `password`
- Waiter (garçom): `garcom@restaurante.com` / `password`

After login the admin lands on the dashboard; waiter users are redirected to `/garcom`.

### Timezone
App timezone defaults to `America/Sao_Paulo` via `APP_TIMEZONE` (`config/app.php`). After changing
`.env` or `config/app.php` on a server, always run:

```
php artisan config:clear
php artisan config:cache
```

If PHP-FPM / Octane / queue workers are used, restart them too — cached config is the usual reason
times still look like UTC after an `.env` edit. Verify with:
`php artisan tinker --execute="echo config('app.timezone').' '.now();"`

### Evolution / WhatsApp
Credenciais e QR Code ficam em **Configurações → Agente WhatsApp** (grupo `evolution` na
tabela `settings`). O `.env` (`EVOLUTION_*`) só é fallback inicial. Depois de salvar URL/API
Key, use **Gerar QR Code** e **Configurar webhook** na mesma tela. O servidor Laravel precisa
alcançar a URL da Evolution (rede/firewall); o celular escaneia o QR normalmente.

**Feedback pós-comanda:** em Agente WhatsApp, ative o pedido de feedback e o atraso (minutos).
Ao fechar a comanda, um job atrasado é enfileirado — precisa de `php artisan queue:listen`
(ou `composer dev`). Só envia se a comanda tiver cliente com telefone.

**Acompanhamento / descrição do prato:** em Produtos, o checkbox *Pede acompanhamento no
WhatsApp* (`requires_side`, default true) controla se fritas/legumes entram no fluxo. Desmarque
para pratos completos (ex.: feijoada). A IA responde dúvidas de ingredientes só pela
**Descrição** do produto (`get_menu` / `menuSnapshot`) — não lê a ficha técnica/estoque da receita.

**Endereço com referência:** `DeliveryFeeService` remove loja/referência/`em frente a` etc. só
para geocode; o texto original do cliente continua salvo no pedido para o entregador.

### Printing (thermal ESC/POS)
- Cloud VPS cannot reach LAN printers (`192.168.1.100:9100`). Use driver **`agent`**
  plus `scripts/print-agent.php` on a restaurant PC, or **`browser`**. See README § Impressão.
- Driver **`network`** only works when PHP can TCP-connect to the printer (same LAN/VPN).

### Lint
`./vendor/bin/pint` (use `./vendor/bin/pint --test` to check without writing). The committed code
currently has many pre-existing Pint style findings — a non-clean `--test` result is expected and
is unrelated to your changes unless you introduced new ones.

### Tests
`php artisan test` (PHPUnit). Feature auth/profile tests assume the real role model:
`User::factory()` defaults to **admin**; use `->waiter()` for garçom flows. Public
registration is disabled (`/register` → 404). Root `/` redirects to `/cardapio`.
See PR #2 (`cursor/fix-feature-tests-94b2`) for the aligned suite.
