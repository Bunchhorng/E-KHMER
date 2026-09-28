# E-KHMER — Full-Stack E-Commerce System

A full-stack e-commerce management system:

- **Backend** — Laravel REST API (PHP 8.3, Sanctum, MySQL 8, Redis)
- **Frontend** — Vue 3 + TypeScript (Vite, Pinia, Vue Router, Axios)
- **Infra** — Docker Compose, Nginx, Dockerized dev environment

Customer storefront (browse, cart, checkout, orders, wishlist, reviews) + full admin dashboard (catalog, inventory, orders, coupons, reports).

This README is the **team setup guide**. It covers:

- [Option A — Docker (recommended)](#option-a--docker-recommended) on Windows / Linux / macOS
- [Option B — Native, no Docker](#option-b--native-without-docker) on Windows / Linux / macOS
- Environment cheat-sheet, demo accounts, tests, and troubleshooting

Full architecture / API / frontend docs live in [`docs/`](./docs). Team workflow & task ownership: [`docs/TEAM-WORK-BREAKDOWN.md`](./docs/TEAM-WORK-BREAKDOWN.md) and [`docs/project-management/`](./docs/project-management/).

---

## 0. What you get when it runs

| Service | Docker setup | Native setup |
| --- | --- | --- |
| Frontend (Vue dev server + HMR) | http://localhost:**5174** | http://localhost:**5173** |
| Laravel API | http://localhost:**8000** `/api` | http://localhost:**8000** `/api` |
| phpMyAdmin | http://localhost:**8080** | _(optional, install separately)_ |
| MySQL | localhost port **3307** | localhost port **3306** |
| Redis | localhost **6379** | optional |

> The frontend dev server **proxies `/api` to the backend** automatically (Vite proxy), so you never configure CORS locally.

### Demo accounts (seeded)

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@ekhmer.dev` | `password` |
| Customer | `olivia.bennett@example.com` (or any seeded customer) | `password` |

Seeder customers: `marcus.lee@example.com`, `priya.shah@example.com`, `jake.miller@example.com`, `elena.rodriguez@example.com`, `dan.okafor@example.com`.

### Environment cheat-sheet (`backend/.env`)

| Key | Docker | Native |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | `mysql` |
| `DB_HOST` | `mysql` (container name) | `127.0.0.1` |
| `DB_PORT` | `3306` | `3306` |
| `DB_DATABASE` | `ecommerce_db` | `ecommerce_db` |
| `DB_USERNAME` | `ecommerce_user` | `ecommerce_user` |
| `DB_PASSWORD` | `ecommerce_password` | `ecommerce_password` |
| `PAYMENT_MODE` | `sandbox` (dev) | `sandbox` (dev) |

`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` default to `database`, so **Redis is optional** for native setup. Mail defaults to the `log` driver — password-reset / verification emails are written to `backend/storage/logs/laravel.log`.

---

## Option A — Docker (recommended)

One command starts everything. All PHP / Composer / npm work happens **inside containers** — never install PHP, Composer, Node, or MySQL on your machine.

### A1. Install Docker

| OS | How |
| --- | --- |
| **Windows 10/11** | Install **Docker Desktop** (uses WSL2 backend). In Docker Desktop → Settings → *Resources → WSL Integration* → enable. Run PowerShell as admin: `wsl --install` if WSL is missing, then restart. |
| **macOS** | Install **Docker Desktop** for Mac (Apple Silicon or Intel build). |
| **Linux (Debian/Ubuntu)** | `sudo apt update && sudo apt install -y docker.io docker-compose-v2` and `sudo usermod -aG docker $USER` (log out/in after). Or Docker's official convenience script. |

Verify: `docker --version` and `docker compose version`.

### A2. Get the code

```bash
git clone <your-repository-url> E-Ecommerce
cd E-Ecommerce
```

### A3. Configure environment files

```bash
# Backend env (copy once; .env is untracked — never commit it)
cp backend/.env.example backend/.env
```

For Docker the defaults below are already correct in `backend/.env` — verify/extend:

```dotenv
APP_NAME="E-KHMER"
APP_ENV=local
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5174   # Docker frontend port
APP_KEY=            # filled by key:generate below
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ecommerce_db
DB_USERNAME=ecommerce_user
DB_PASSWORD=ecommerce_password
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
REDIS_HOST=redis
REDIS_PORT=6379
PAYMENT_MODE=sandbox
```

_(Windows PowerShell: use `Copy-Item backend/.env.example backend/.env`.)_

### A4. Build and boot the stack

```bash
# Build images, start all containers (app, scheduler, nginx, mysql, redis, phpmyadmin, frontend)
docker compose up -d --build

# Watch until the frontend container finishes: npm install && npm run dev
docker compose ps
```

The `frontend` service runs `npm install && npm run dev` automatically on first boot.

### A5. Backend setup

```bash
# Install PHP dependencies
docker compose exec app composer install

# Generate application key
docker compose exec app php artisan key:generate

# Create the schema + seed demo data
docker compose exec app php artisan migrate --seed

# Link storage so product/brand images load
docker compose exec app php artisan storage:link
```

### A6. Verify

- Frontend: http://localhost:5174 → login as `admin@ekhmer.dev` / `password`
- API: http://localhost:8000/api (e.g. `GET /api/categories` returns JSON)
- phpMyAdmin: http://localhost:8080 (user `ecommerce_user` / `ecommerce_password`, or `root` / `root_password`)

### A7. Scheduler (required for the 15-min inventory reservation expiry)

Not needed for basic browsing, but **required** for checkout correctness:

```bash
docker compose up -d scheduler   # runs `php artisan schedule:work` in the background
docker compose logs -f scheduler
```

### A8. Common Docker commands

```bash
docker compose up -d              # start (data preserved)
docker compose down               # stop (data preserved)
docker compose down -v            # stop AND delete DB volume (all data lost!)
docker compose logs -f app        # Laravel logs
docker compose logs -f frontend   # Vite logs
docker compose exec app php artisan route:list
docker compose exec app php artisan test
docker compose exec frontend npm run build
```

> ⚠️ Never run `php`, `composer`, or `npm` on the host when using Docker — always `docker compose exec`.

---

## Option B — Native (without Docker)

Pick this only if Docker can't run on your machine. You install PHP, Composer, MySQL, and Node directly.

**Prerequisites (all OS):** PHP **≥ 8.3**, Composer **2.x**, MySQL **8.x**, Node **22 LTS** (Vite 8 needs Node ≥ 20.19 / ≥ 22.12), and the PHP extensions below.

**Required PHP extensions:** `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `zip`, `gd`, `intl`, `bcmath`, `exif`, `fileinfo`, `opcache`. Verify after install with `php -m`.

### B1. Install prerequisites per OS

#### Windows

| Tool | Install |
| --- | --- |
| PHP 8.3 + Composer + MySQL (easiest) | **Laragon** (https://laragon.org) bundles PHP 8.x, Composer, and MySQL. Menu → *PHP → 8.3* and *Tools → Service/Port*. |
| Alternative — standalone PHP | `winget` or the `php.new` installer, then uncomment these in `C:\php\php.ini`: `extension=pdo_mysql`, `extension=mbstring`, `extension=gd`, `extension=zip`, `extension=intl`, `extension=bcmath`, `extension=exif`, `extension=fileinfo`, `extension=openssl`. |
| Composer | https://getcomposer.org/download (installer adds it to PATH). |
| Node.js | https://nodejs.org (LTS 22). Or `nvm-windows`. |

**MySQL:** Laragon users can use its bundled MySQL. Standalone users install MySQL Installer, then run the DB/user creation block in **B2** (e.g. in MySQL Workbench or `mysql -u root -p`).

#### Linux (Debian / Ubuntu)

```bash
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-curl unzip

# Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"

# MySQL 8
sudo apt install -y mysql-server
sudo systemctl enable --now mysql

# Node 22 LTS
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

#### macOS

```bash
# Homebrew
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

brew install php@8.3 composer mysql node@22

# Start MySQL and set a root password when prompted
brew services start mysql
mysql_secure_installation
```

Verify everything: `php -v`, `composer -V`, `node -v`, `mysql --version`.

### B2. Create the database & user

Run once (MySQL prompt or any client):

```sql
CREATE DATABASE IF NOT EXISTS ecommerce_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ecommerce_user'@'localhost' IDENTIFIED BY 'ecommerce_password';
GRANT ALL PRIVILEGES ON ecommerce_db.* TO 'ecommerce_user'@'localhost';
FLUSH PRIVILEGES;
```

### B3. Get the code & configure the backend

```bash
git clone <your-repository-url> E-Ecommerce
cd E-Ecommerce

cd backend
cp .env.example .env          # Windows PowerShell: Copy-Item .env.example .env
```

Edit `backend/.env` (native values):

```dotenv
APP_NAME="E-KHMER"
APP_ENV=local
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173   # native frontend port
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce_db
DB_USERNAME=ecommerce_user
DB_PASSWORD=ecommerce_password
PAYMENT_MODE=sandbox
```

> Leave `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` as `database` (defaults) — Redis is **optional** natively (see B5).

### B4. Install, configure, migrate, seed, serve

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve            # → http://localhost:8000
```

Keep `php artisan serve` running in its own terminal.

### B5. (Optional) Native Redis

The app runs fine without it. To enable:

```bash
# install redis server for your platform (e.g. apt install redis-server / brew install redis)
# PHP: pecl install redis && echo "extension=redis" >> php.ini   (or use predisfallback)
```
then set in `.env`: `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis`, `REDIS_HOST=127.0.0.1`.

### B6. Frontend

```bash
cd frontend
npm install
npm run dev                   # → http://localhost:5173
```

No `.env` needed for dev: the Vite proxy sends `/api` to `http://localhost:8000` automatically (see `frontend/vite.config.ts`). For a **production build**, create `frontend/.env` with `VITE_API_URL=http://localhost:8000/api`:

```bash
npm run build                 # type-checks (vue-tsc) + builds → dist/
```

### B7. Scheduler (inventory reservation expiry)

Reservation expiry (`checkout:expire-reservations`) is a scheduled task. Run it manually in its own terminal, or add a cron entry:

```bash
# foreground (like the Docker scheduler container)
php artisan schedule:work

# OR system cron (every minute)
# * * * * * cd /absolute/path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## Running tests & checks

| Task | Docker | Native |
| --- | --- | --- |
| Backend test suite | `docker compose exec app php artisan test` | `cd backend && php artisan test` |
| Frontend build (type-check + build) | `docker compose exec frontend npm run build` | `cd frontend && npm run build` |
| Laravel Pint (style) | `docker compose exec app ./vendor/bin/pint --test` | `cd backend && ./vendor/bin/pint --test` |

---

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Images / product photos missing | Run `php artisan storage:link` (missing symlink to `storage/app/public`). |
| API returns 401 constantly | Backend not running, token expired, or protected route. Restart `php artisan serve` / check container. |
| API 413 / wrong DB host | Native: use `DB_HOST=127.0.0.1`. Docker: must be `DB_HOST=mysql` (container name, **not** localhost). |
| Frontend shows network errors on `/api` | Confirm backend is up on `:8000` (Vite proxy target). |
| MySQL: `Access denied for user` | Re-run the B2 grant block; confirm `DB_USERNAME`/`DB_PASSWORD` match `.env`. |
| MySQL 8 auth failure from PHP | PHP ≥ 8.0's bundled `mysqlnd` supports `caching_sha2_password`; update PHP if it's older than 8.3. |
| `APP_KEY` missing error | `php artisan key:generate` after copying `.env`. |
| Composer memory error (native) | `COMPOSER_MEMORY_LIMIT=-1 composer install`. |
| Windows: PowerShell blocks scripts | `Set-ExecutionPolicy -Scope Process Bypass`, or use the official `composer-setup.exe` installer. |
| Docker "port already in use" | `docker compose ps`; change the host port (e.g. `8000:80` → `8001:80`) in `docker-compose.yml`. |
| Reservations never expire in dev | The `scheduler` service (Docker) or `php artisan schedule:work` (native) isn't running → see A7/B7. |
| phpMyAdmin can't log in | Use `ecommerce_user` / `ecommerce_password` (PMA_HOST is already `mysql`). |

## Security notes

- `backend/.env`, `frontend/.env` are **untracked** — never commit them.
- Never commit `APP_KEY`, DB passwords, or payment secrets.
- `APP_DEBUG=true` is fine locally; set `APP_DEBUG=false` + `PAYMENT_MODE=production` in test/prod.
- The API uses Laravel Sanctum bearer tokens; admin endpoints require the `admin` role.

## Related documentation

| Doc | What it covers |
| --- | --- |
| [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md) | Layered architecture, DB schema, business-logic engine |
| [`docs/API-REFERENCE.md`](./docs/API-REFERENCE.md) | Live API reference: every route, request, response |
| [`docs/FRONTEND.md`](./docs/FRONTEND.md) | Vue project layout, routing, stores, API client, i18n |
| [`docs/DEVELOPMENT.md`](./docs/DEVELOPMENT.md) | Docker workflow, git conventions |
| [`docs/TEAM-WORK-BREAKDOWN.md`](./docs/TEAM-WORK-BREAKDOWN.md) | Task ownership across the 5-member team, git workflow |
| [`docs/project-management/`](./docs/project-management/) | Charter, plan, task sheet, timeline, meeting/minutes, risk/decision logs |

## License

Developed for educational and academic purposes.


test bot