# Development Workflow

This document covers the day-to-day Docker workflow, common commands, git conventions, and troubleshooting for this project.

## 1. Golden Rule

For the new seller, Bakong, returns and support flows, see [commerce completion](./COMMERCE-COMPLETION.md). Production setup is separate: [Oracle Cloud](./ORACLE-CLOUD.md).

Desktop/mobile browser checks run entirely in Docker:

```sh
docker compose --profile test run --rm browser-tests
docker compose exec app php artisan ops:health --require-heartbeats
```

The browser service shares the frontend network namespace and uses localhost, which permits camera testing without weakening production HTTPS requirements. CI uses fake camera input and mocked UI APIs. `docker compose up -d worker scheduler` starts queued notifications and scheduled payment/reservation maintenance; the worker uses the configured queue connection.

Backend dependencies use a shared Docker `backend_vendor` volume for app/worker/scheduler, avoiding Windows bind-mount extraction/file-lock failures and slow dependency reads. Run `docker compose exec app composer install` after changing the lock file or recreating this volume. The host's ignored `backend/vendor` folder is not used by running PHP containers; source code and database/media persistence are unchanged.

**Always run PHP, Composer, and npm commands inside the Docker containers** — never on the host machine (see `AGENTS.md`). Example:

```bash
docker compose exec app php artisan <command>        # Laravel
docker compose exec app composer <command>           # Composer
docker compose exec frontend npm <command>           # npm
```

## 2. Starting & stopping

```bash
docker compose up -d             # start containers
docker compose up -d --build     # start and rebuild
docker compose ps                # status
docker compose logs -f           # all logs
docker compose logs -f app       # Laravel logs
docker compose logs -f nginx     # Nginx logs
docker compose logs -f frontend  # Vue logs
docker compose down              # stop (data preserved)
```

## 3. Backend (Laravel) commands

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate           # apply migrations
docker compose exec app php artisan migrate --seed    # migrate + seed
docker compose exec app php artisan migrate:fresh --seed  # reset + seed (destructive)
docker compose exec app php artisan storage:link      # link public storage for images
docker compose exec app php artisan optimize:clear    # clear caches
docker compose exec app php artisan route:list        # list routes
```

> ⚠️ `migrate:fresh` drops all tables and data. `docker compose down -v` deletes the MySQL volume (all data gone).

## 4. Database & redis

```bash
docker compose exec mysql mysql -u ecommerce_user -p ecommerce_db  # MySQL shell
docker compose exec redis redis-cli                                 # Redis shell (PING → PONG)
```

Host-machine DB tools connect on port **3307** (see `docker-compose.yml`):
- Host: `localhost:3307`, DB: `ecommerce_db`, User: `ecommerce_user`

phpMyAdmin: http://localhost:8080 (root / `root_password` or `ecommerce_user` / `ecommerce_password`).

## 5. Frontend (Vue) commands

```bash
docker compose exec frontend npm install
docker compose exec frontend npm run dev
docker compose exec frontend npm run build
```

The `frontend` service already runs `npm install && npm run dev` on startup.

## 6. Environment files

Configure before first run (these files are **untracked** and must not be committed):

**`backend/.env`** — copy from `backend/.env.example`:
- `DB_HOST=mysql` (the Docker internal host — **not** `localhost`)
- `DB_PORT=3306`, `REDIS_HOST=redis`

**`frontend/.env`** —
```
VITE_API_URL=http://localhost:8000/api
```

## 7. Git & commit conventions

Use **conventional commits** and target the appropriate branch:

```
main ── develop ── feature/*
```

Examples:
```bash
git checkout -b feature/products
git add .
git commit -m "feat: add product management"
git push origin feature/products
```

Conventional prefixes: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `build`, `perf`, `style`.

## 8. Testing

Backend feature tests, model factories, and auth/inventory hardening exist under `backend/tests` / `backend/database/factories`. Run them via:

```bash
docker compose exec app php artisan test
```

## 9. Troubleshooting

| Symptom | Fix |
| --- | --- |
| `413`/wrong URLs | Confirm `backend/.env` uses `DB_HOST=mysql` (internal). |
| API returns 401 | Backend not started, token expired/cleared, or you're hitting a protected route. |
| Images not loading | Run `docker compose exec app php artisan storage:link`. |
| Migration errors | Ensure containers are up; `docker compose logs -f app`. |
| Tables out of sync | Run `docker compose exec app php artisan migrate`. |
| Port already in use | Check `docker compose ps`; adjust ports in `docker-compose.yml`. |
| Stale frontend deps | `docker compose exec frontend npm install` or recreate the volume (`docker compose down -v`, then `up -d --build`). |
