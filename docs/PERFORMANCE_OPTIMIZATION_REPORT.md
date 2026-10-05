# Performance Optimization Report

Full-application performance pass over the E-KHMER stack (Laravel 13 API, Vue 3 + TypeScript, MySQL 8, Redis, Nginx, Docker Compose), following `docs/full-application-Performance.md`.

Workflow followed: **INSPECT → MEASURE → IDENTIFY → OPTIMIZE → TEST → VERIFY**.

---

## 1. Executive summary

Three changes were made, all verified byte-identical in their API responses:

| # | Change | File | Effect |
|---|--------|------|--------|
| 1 | OPcache `revalidate_freq` `2` → `60` | `docker/php/opcache.ini` | HTTP p50 **~15× faster** (4908 ms → ~320 ms) |
| 2 | `CatalogService::facets()` N+1 removal | `backend/app/Services/CatalogService.php` | **55 → 6 queries**, service time 8.8× faster |
| 3 | `DashboardService` duplicate-aggregate removal | `backend/app/Services/DashboardService.php` | **35 → 24 queries**, revenue/order aggregates consolidated |

Verification: **269 backend tests passed (1155 assertions, 0 failures)**; frontend production build succeeded; every optimized endpoint's JSON response was diffed against its pre-change output and is **byte-identical**.

The dominant bottleneck was **not** application code or the database — it was the Windows 9p bind mount that backs `/var/www`. See §3.1.

---

## 2. Environment and methodology

- All PHP/Composer/npm commands were run inside Docker containers per `AGENTS.md`.
- Two independent measurement techniques were used so that neither could mislead on its own:
  - **In-process** — boot the framework, dispatch synthetic `Illuminate\Http\Request` objects through the HTTP kernel, and read `DB::getQueryLog()`. This isolates *application* work and is immune to network noise.
  - **End-to-end HTTP** — `curl` through `nginx → php-fpm` from inside the `app` container, reporting min/p50/p90/max over many samples.
- The two techniques disagreed by orders of magnitude, and that disagreement is what identified the real bottleneck (§3.1).

### Caveats on the numbers

- **The host is shared and noisy.** Several unrelated Docker stacks (`system_*`, `university_*`) were running throughout; `system_app` alone consumed 105–135% CPU while E-KHMER was mostly idle. Absolute timings therefore carry real variance, and only medians over many samples are quoted. One raw sample recorded a 7-hour "duration" from a host sleep/suspend and was discarded.
- **The dev database is nearly empty.** Of 14 products, **13 are soft-deleted** and only 1 is live (also 0 live featured products). This does not weaken the query-count findings — the eliminated queries were *per-row* `COUNT()` calls, so the saving grows with catalog size — but absolute latencies on a 1-product catalog are not representative of production data volume.
- `tests/phpunit.xml` pins `DB_CONNECTION=sqlite` / `:memory:`, so the test suite never touches the dev MySQL database. Confirmed: the soft-deletes are not test fallout.

---

## 3. Findings

### 3.1 Critical — the filesystem, not the application

In-process, `/api/catalog/facets` served in **~20 ms**. The same endpoint over HTTP took **~4.9 seconds**. A ~245× gap between application time and observed response time cannot be explained by queries, and it was not CPU exhaustion.

Measured cause: `/var/www` is a Docker Desktop **9p bind mount** from the Windows host, where a single `stat()` costs **~2.455 ms**. OPcache was configured with `validate_timestamps=1` and `revalidate_freq=2`, so PHP-FPM re-stat'ed all **~1100 cached scripts** every couple of seconds per worker. At ~2.5 ms per stat, that is seconds of pure syscall latency per request.

Effective config observed via `opcache_get_status()`:

```
validate_timestamps = true
revalidate_freq     = 2
cached_scripts      = 1098
hits/misses         = 413015 / 2633
jit                 = "tracing"  (jit_buffer_size = 0, i.e. effectively disabled)
```

### 3.2 High — N+1 in the catalog filter sidebar

`GET /api/catalog/facets` issued **55 queries**. The exact breakdown:

| Source | Queries |
|--------|---------|
| `brands` + `withCount` | 1 |
| `categories->facets()` (list + hierarchy) | 2 |
| `attributes` list | 1 |
| `valuesFor()` per filterable attribute — 1 + one `COUNT()` **per value** (15 colour + 7 size) | 24 |
| `valuesForSlug('color')` — recomputes colour values from scratch | 17 |
| `valuesForSlug('size')` — recomputes size values from scratch | 9 |
| price range | 1 |
| **Total** | **55** |

Two independent defects: a per-value `COUNT()` inside `valuesFor()` (`backend/app/Services/CatalogService.php:241` pre-change), and `colors`/`sizes` being computed a second time from scratch even though the loop above had already fetched them.

### 3.3 Medium — duplicated aggregates on the admin dashboard

`GET /api/admin/dashboard/overview` issued **35 queries**. Not an N+1 — a set of independent aggregates containing two clear redundancies:

- The month revenue `SUM()` was executed **twice**: once inside the `percentChange()` call for `revenue_delta`, then again for `month_revenue` (`backend/app/Services/DashboardService.php:52-57` pre-change).
- `orderStatusDistribution()` re-ran the exact `GROUP BY status` aggregate that `metrics()` had already executed for the same request.

---

## 4. Changes applied

### 4.1 `docker/php/opcache.ini` — `revalidate_freq` 2 → 60

Three configurations were A/B tested through real HTTP traffic:

| Configuration | `/api/catalog/facets` p50 | Hot reload |
|---------------|---------------------------|-----------|
| `revalidate_freq=2` (before) | 4908 ms | yes |
| `revalidate_freq=60` | **318 ms** | yes (≤60 s) |
| `validate_timestamps=0` | 393 ms | **no** |

`revalidate_freq=60` matches full validation-disabling while preserving code reload, so it was chosen over `validate_timestamps=0`.

**Developer impact:** a PHP edit may take up to 60 s to be picked up. To bypass the wait:

```sh
docker compose exec app kill -USR2 1
```

**Production note:** this win is specific to the Docker Desktop 9p mount. On a native Linux filesystem a `stat()` costs roughly 1–2 µs rather than 2.5 ms, so `revalidate_freq=2` is not pathological there. For production, prefer mounting an ini that sets `opcache.validate_timestamps=0` *after* this file (it must sort later in `conf.d` to win) — a comment in the file records this.

### 4.2 `CatalogService::facets()` — N+1 removed

Replaced the per-value `COUNT()` with a single eager-loaded `withCount`, and reused the already-fetched values for `colors`/`sizes` instead of recomputing them.

One behavioural subtlety was preserved deliberately: `color` and `size` are now loaded even when `is_filterable = false`, because the legacy swatch rail has always reported them regardless of that flag; they are filtered back out of the `attributes` array afterwards.

`valuesFor()`/`valuesForSlug()` (both issuing queries) were replaced by a single pure `shapeValues()` shaper that performs no I/O. Neither method had callers outside this class.

### 4.3 `DashboardService` — consolidated aggregates

- Five separate revenue `SUM()` round-trips → one conditional-aggregation query.
- Order and customer month-over-month counts → one query per table.
- `GROUP BY status` memoised per instance and shared by `metrics()` and `orderStatusDistribution()`.

---

## 5. Before / after evidence

### 5.1 End-to-end HTTP latency (nginx → php-fpm)

n=40 before, n=25 after, same host, same payloads:

| Endpoint | Before p50 | After p50 | Before min | After min |
|----------|-----------|-----------|-----------|-----------|
| `/api/catalog/facets` | 4908 ms | 409 ms | 3970 ms | 302 ms |
| `/api/catalog/products?perPage=24` | 5522 ms | 319 ms | 4026 ms | 282 ms |
| `/api/catalog/products/{slug}` | 5203 ms | 318 ms | 4214 ms | 296 ms |

Across three independent post-fix runs, p50 for these endpoints landed between **310 ms and 409 ms**, consistently ~15× better than the 4.9–5.5 s baseline. Residual ~300 ms is the irreducible cost of loading PHP files over the 9p mount on a contended host.

### 5.2 In-process query counts

| Endpoint | Before | After |
|----------|--------|-------|
| `/api/catalog/facets` | 55 | **6** |
| `/api/admin/dashboard/overview` | 35 | **24** |

After the fixes, every audited endpoint is at **≤11 queries** except the admin dashboard (24 independent aggregates).

### 5.3 In-process service time — `facets()`, A/B via `git stash`

n=9 in a single warm process, old and new code measured back-to-back:

| | Queries | min | median | max |
|---|---------|-----|--------|-----|
| Before | 55 | 160.9 ms | **185.8 ms** | 223.8 ms |
| After | 6 | 17.0 ms | **21.1 ms** | (cold first call 1514 ms) |

**8.8× faster**, and the improvement scales with the number of attribute values in the catalog.

---

## 6. Verification

| Check | Result |
|-------|--------|
| Full backend suite | **269 passed, 1155 assertions, 0 failures** |
| `php -l` on both modified services | No syntax errors |
| `/api/catalog/facets` JSON response | **Byte-identical** to pre-change (`diff` clean) |
| `/api/admin/dashboard/overview` JSON response | **Byte-identical** to pre-change (per-key comparison) |
| Frontend production build | Succeeded in 12.85 s |
| Temporary artefacts | `backend/public/_perfprobe.php` deleted; `git status` shows only the 3 intended files |

No API contract, business rule, authorization behaviour, or UI change was introduced. No new dependency was added.

---

## 7. Investigated and deliberately left unchanged

| Observation | Decision |
|-------------|----------|
| `GET /api/catalog/featured` returns `{"data":[]}` | **Not a bug.** All 4 `is_featured` products are soft-deleted. A data/seed issue, not a performance one. |
| `GET /api/catalog/products/aurora-wireless-headphones` → 404 | **Not a bug.** The product is soft-deleted (`deleted_at` set); the scope correctly excludes it. |
| `/api/admin/orders/{id}/receipt` ≈ 859 KB | This is the **PDF binary** from domPDF, not a JSON payload. Expected. |
| 404 responses carry ~8–11 KB bodies | `APP_DEBUG=true` in dev renders a full stack trace. Not a production issue and not worth degrading local debugging. |
| `AdminDashboardView` chunk ≈ 212 KB | `chart.js` via `vue-chartjs`, statically imported by 5 chart components. Admin-only and already route-lazy-loaded, so customer pages are unaffected. Lazy-loading Chart.js is a real opportunity but touches 5 chart components — recorded as a recommendation rather than risk UI regressions here. |
| Frontend routing / icons | Already optimal: all 50 view components are route-lazy-loaded, and `lucide-vue-next` is consumed via named imports that tree-shake correctly. Main entry chunk 272 KB / 79 KB gzip is reasonable for Vue + router + pinia + axios + vue-i18n. |
| `opcache.jit` is `tracing` but `jit_buffer_size=0` | JIT was left disabled. With a filesystem-bound rather than CPU-bound profile, JIT's benefit is marginal, and enabling it adds behavioural risk for little expected gain. |
| No new DB indexes added | Hot queries are already served by existing indexes on a tiny dataset; all remaining dashboard aggregates are full-table `COUNT`/`SUM`s that index tuning cannot improve. |

---

## 8. Recommendations (not applied)

1. **Cache `/var/www` off the bind mount in CI and production.** For real deployments, bake the application into the Docker image instead of bind-mounting Windows/macOS host paths. This removes the 9p penalty at its root rather than tuning around it. The existing `frontend_node_modules` named volume in `docker-compose.yml` is the pattern to follow — but note `docker/php/Dockerfile` does **not** bake `vendor/` into the image, so a named volume at `/var/www/vendor` would start empty and break autoloading unless a seed step is added first. This is why it was not done here.
2. **Load `chart.js` on demand** in the five admin chart components to cut the `AdminDashboardView` chunk from ~212 KB.
3. **Add a `catalog:facets` query-count regression test** asserting a fixed upper bound, so the N+1 cannot silently return. The same applies to `DashboardService::overview()`.
4. **Restore dev demo data.** 13 of 14 products are soft-deleted, so the storefront homepage renders an empty featured rail and the catalog looks broken during manual QA. Re-running the seeders would also make future performance measurements representative.
5. **Enable `validate_timestamps=0` for production** via a compose override that mounts an ini sorting after `zz-opcache.ini`.

---

## 9. Files changed

```
backend/app/Services/CatalogService.php   |  72 ++++++++++++++++++++++++++---------
backend/app/Services/DashboardService.php | 115 +++++++++++++++++++++++++++++++++---------
docker/php/opcache.ini                    |  26 +++++++++---
3 files changed, 134 insertions(+), 79 deletions(-)
```

No migrations, no dependency changes, no configuration outside the OPcache ini.