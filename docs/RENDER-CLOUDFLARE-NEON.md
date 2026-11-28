# Test Deployment Guide: Render + Cloudflare Pages + Neon (PostgreSQL)

This guide walks through deploying **E-KHMER** for staging / test verification using:
- **Database**: Neon Serverless PostgreSQL (`pgsql`)
- **Backend API**: Render Docker Web Service ([`render.yaml`](../render.yaml), [`docker/render/Dockerfile`](../docker/render/Dockerfile))
- **Frontend SPA**: Cloudflare Pages (`frontend/`)

---

## Architecture Overview

```text
Browser ──► Cloudflare Pages (Vue 3 SPA)
               │
               ├──► Render Web Service (Nginx + PHP 8.3 FPM + Laravel API)
               │       ├── /up (health check)
               │       ├── /api/* (REST + Sanctum bearer auth)
               │       └── /storage/* (local public disk assets)
               │
               └──► Neon PostgreSQL (SSL required, pooled DB_URL)
```

For zero-cost test deployments, Redis is not required: `render.yaml` configures `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, and `CHECKOUT_LIMITER_STORE=database`, and enables `RUN_EMBEDDED_WORKER=true` so scheduled cleanup (`checkout:expire-reservations`) and queued jobs run inside the Render container.

---

## Step 1: Create the Neon PostgreSQL Database

1. Sign in to Neon and create a new project (e.g. `ekhmer-test`, Postgres 16).
2. In **Connection Details**, copy the **Connection string** (pooled URL is fine):
   ```text
   postgresql://neondb_owner:<password>@ep-xxxx-pooler.<region>.aws.neon.tech/neondb?sslmode=require
   ```
3. Keep this URI ready as `DB_URL` for Render.

> If you experience prepared-statement errors with Neon's transaction pooler under heavy concurrency, either use Neon's direct (non-`-pooler`) endpoint or set `DB_PGSQL_EMULATE_PREPARES=true` on Render.

---

## Step 2: Deploy the Laravel API on Render

1. Push your branch to GitHub/GitLab.
2. In Render Dashboard, choose **New + → Blueprint** and select this repository (or manually create a **Web Service → Docker** pointing to `docker/render/Dockerfile` with Docker context `.`).
3. Set the prompted environment variables in Render (see [`backend/.env.render.example`](../backend/.env.render.example)):
   - `DB_URL`: your Neon pooled `postgresql://...?sslmode=require` connection string
   - `DB_MIGRATION_URL`: Neon’s direct (non-pooler) `postgresql://...?sslmode=require` connection string
   - `APP_URL`: your Render service URL, e.g. `https://ekhmer-api.onrender.com`
   - `FRONTEND_URL`: your Cloudflare Pages URL, e.g. `https://ekhmer.pages.dev`
   - `CORS_ALLOWED_ORIGINS`: comma-separated origins, e.g. `https://ekhmer.pages.dev`
   - `CORS_ALLOWED_ORIGIN_PATTERNS`: `#^https://.*\.(pages|workers)\.dev$#` (allows Cloudflare Pages and Workers preview URLs during testing)
   - `PAYMENT_MODE`: keep `sandbox` for test deployment; switch to `production` with a real `BAKONG_API_TOKEN` before accepting live payments.
4. Wait for the build and boot health check (`GET /up` returning HTTP `200`). On first boot with `RUN_MIGRATIONS_ON_BOOT=true` and `SEED_ESSENTIALS_ON_MIGRATE=true`, `entrypoint.sh` invokes `render-migrate.sh`, which uses `DB_MIGRATION_URL` for `php artisan migrate --force` and then runs `SettingSeeder` only.

### Optional: Loading Cambodian Demo Data on a Staging/Test Database
Because `DemoMarketplaceSeeder` is restricted to `local` or `testing` environments by safety policy:
- For **production-like test (`APP_ENV=production`)**: registration creates customer accounts only. This project intentionally has no production bootstrap-admin seeder or artisan command, so provision the first `super_admin` through an approved, audited operator process; demo customers/orders stay blocked.
- For a **disposable staging preview with demo catalog/orders**: temporarily set `APP_ENV=local` and `SEED_DEMO_ON_BOOT=true` only on that isolated staging database, or run the catalog seeder manually after verifying you are targeting a non-production database. Remember to revert `APP_ENV=production` and `SEED_DEMO_ON_BOOT=false` afterwards.

> **Render Ephemeral Storage Warning:** Render's free Web Service filesystem resets on every redeploy or spin-down. Product/shop images uploaded via `/api/admin/uploads/*` on the local `public` disk will not survive container rebuilds unless you attach a **Render Persistent Disk** mounted at `/var/www/storage/app` or configure S3/Cloudflare R2 (`FILESYSTEM_DISK=s3`).

---

## Step 3: Deploy the Vue 3 Storefront on Cloudflare (Workers Static Assets or Pages)

### Option A: Cloudflare Workers (Recommended — `wrangler.jsonc`)

Both [`frontend/wrangler.jsonc`](../frontend/wrangler.jsonc) and [`wrangler.jsonc`](../wrangler.jsonc) configure Cloudflare Workers Static Assets to build and serve **`dist/`** (never the raw `frontend/` source folder) with `"not_found_handling": "single-page-application"`.

> **Why `"Failed to load module script... MIME type of video/mp2t"` happens:**
> If Cloudflare deploys raw `frontend/` instead of `frontend/dist`, the browser receives source `index.html` containing `<script type="module" src="/src/main.ts">`. Cloudflare serves `.ts` files as `video/mp2t`, causing a blank white screen. Always build `npm run build` and deploy `./dist` (do **not** pass `--assets=.` in your deploy command).

1. In Cloudflare Dashboard, open **Workers & Pages → Create → Workers → Connect to Git**.
2. Configure **Build settings**:
   - **Root directory**: `frontend` (or leave `/` to use the root [`wrangler.jsonc`](../wrangler.jsonc))
   - **Build command**: `npm run build` (when Root directory is `frontend`)
   - **Deploy command**: `npx wrangler deploy` (leave without `--assets=.` so `wrangler.jsonc`'s `"assets.directory": "./dist"` is used)
3. Add **Build environment variables** under **Settings → Build → Variables and secrets** (Vite reads `VITE_*` at build time, not runtime):
   - `VITE_API_URL=https://ekhmer-api.onrender.com/api`
4. Deploy and ensure the `workers.dev` URL or custom domain is allowed by Render's CORS (`CORS_ALLOWED_ORIGIN_PATTERNS` on Render should include `#^https://.*\.(pages|workers)\.dev$#` or your exact origin in `CORS_ALLOWED_ORIGINS`).

### Option B: Cloudflare Pages

1. In Cloudflare Dashboard, open **Workers & Pages → Create → Pages → Connect to Git**.
2. Configure build settings:
   - **Root directory**: `frontend`
   - **Build command**: `npm run build`
   - **Build output directory**: `dist`
   - **Node version** (optional env): `NODE_VERSION=22`
3. Add Cloudflare Pages environment variable (for both **Production** and **Preview**):
   - `VITE_API_URL=https://ekhmer-api.onrender.com/api`
4. Deploy. [`frontend/public/_redirects`](../frontend/public/_redirects) handles SPA client-side routes (`/products/...`, `/checkout`, `/admin/...`) cleanly without 404s.

### Optional Cloudflare Custom Domain Setup
If you attach a custom domain managed by Cloudflare DNS:
- Point `shop.yourdomain.com` to Cloudflare Pages and `api.yourdomain.com` (CNAME) to `ekhmer-api.onrender.com`.
- Update Render env vars:
  - `APP_URL=https://api.yourdomain.com`
  - `FRONTEND_URL=https://shop.yourdomain.com`
  - `CORS_ALLOWED_ORIGINS=https://shop.yourdomain.com`
- Update Cloudflare Pages env var and redeploy:
  - `VITE_API_URL=https://api.yourdomain.com/api`
- Ensure Cloudflare SSL/TLS encryption mode is **Full (strict)**.

---

## Step 4: Post-Deploy Verification Checklist

1. **API health**: `curl -i https://ekhmer-api.onrender.com/up` → `200 OK`.
2. **CORS + Catalog**: Open `https://ekhmer.pages.dev` and confirm `GET /api/categories`, `GET /api/catalog/products?sort=price-asc`, and `GET /api/shops` load without CORS or console errors.
3. **SPA deep link**: Refresh `/checkout` or `/auth/login` directly in the browser and verify Cloudflare Pages serves the app with HTTP `200`.
4. **Checkout & Auth**: Register a test user, verify login token persistence, add to cart, and verify database-backed rate limiting + checkout quote calculations work against Neon PostgreSQL.
