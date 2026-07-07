# BusinessDiscovery

Laravel 11 (PHP 8.3) + Inertia.js + React 18 + TypeScript, styled with Tailwind CSS
against the dark charcoal/gold token system in `design.md`.

## Requirements

- Docker + Docker Compose (recommended), **or** PHP 8.3 + Composer + Node 20 locally
- Node 20+, npm (for frontend builds either way)

## Setup — Docker (recommended)

Brings up nginx, PHP-FPM, Postgres, Redis, and Mailhog together.

```bash
cp .env.example .env
docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

npm install
npm run build   # or: npm run dev, for HMR against the containerized backend
```

- App: http://localhost:8080 (override with `APP_PORT` in `.env`)
- Mailhog dashboard: http://localhost:8025
- Postgres/Redis are reachable on their forwarded ports for a local DB client if needed.

Services: `app` (PHP-FPM), `nginx`, `pgsql`, `redis`, `mailhog`, plus `queue`
(`artisan queue:work`) and `scheduler` (`artisan schedule:run` loop) worker containers
reusing the same image.

## Setup — without Docker

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Then edit `.env`: set `DB_CONNECTION=sqlite` (and drop the `DB_HOST`/`DB_PORT`/
`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` lines), or point the `DB_*` vars at a
Postgres/MySQL instance you run yourself. Same idea for `REDIS_*`/`MAIL_*` if you don't
want to run Redis/Mailhog locally (`SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION` can
drop back to `database`, `MAIL_MAILER` to `log`).

```bash
touch database/database.sqlite   # only if using sqlite
php artisan migrate

npm run build   # or: npm run dev (in a second terminal, alongside `php artisan serve`)
php artisan serve
```

Visit the URL printed by `php artisan serve` (or http://localhost:8080 under Docker) to
see the themed placeholder page.

## Useful scripts

| Command | What it does |
|---|---|
| `npm run dev` | Vite dev server with HMR |
| `npm run build` | Production frontend build |
| `npm run lint` | ESLint over `resources/js` |
| `npm run format` | Prettier write (Tailwind class sorting included) |
| `vendor/bin/php-cs-fixer fix` | PHP-CS-Fixer (PSR-12 + import ordering) |

## Stack notes

- Ziggy exposes named Laravel routes to the frontend via `@routes` / the `route()` helper.
- shadcn/ui-style primitives live in `resources/js/components/ui` (button, card, dialog,
  input, toast/toaster) — dark theme only, no light-mode variant.
- Design tokens (`--lb-*` CSS variables) are defined in `resources/css/app.css` and
  surfaced as Tailwind theme colors/fonts in `tailwind.config.js`.
