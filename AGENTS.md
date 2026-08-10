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

### Lint
`./vendor/bin/pint` (use `./vendor/bin/pint --test` to check without writing). The committed code
currently has many pre-existing Pint style findings — a non-clean `--test` result is expected and
is unrelated to your changes unless you introduced new ones.

### Tests
`php artisan test` (PHPUnit). There are ~10 pre-existing failures in the default Laravel Breeze
auth/profile tests (`tests/Feature/Auth/*`, `tests/Feature/ProfileTest.php`, `ExampleTest`)
because this app customized routing (e.g. redirects to `/garcom`, registration route removed).
Treat those as the known baseline; focus on tests relevant to your change.
