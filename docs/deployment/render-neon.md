# Render + Neon PostgreSQL deployment

The API uses a Neon pooled connection for requests and a direct Neon
connection for schema migrations. Do not use `migrate:fresh`, `db:wipe`, or
manual schema deletion against an existing database.

## Render environment

Set these secrets in Render (not in this repository):

```text
DB_CONNECTION=pgsql
DB_URL=postgresql://…@…-pooler.…neon.tech/neondb?sslmode=require
DB_MIGRATION_URL=postgresql://…@….…neon.tech/neondb?sslmode=require
DB_SSLMODE=require
RUN_MIGRATIONS_ON_BOOT=true
SEED_ESSENTIALS_ON_MIGRATE=true
```

`DB_MIGRATION_URL` must be the direct host from Neon’s Connect dialog: it must
not contain `pooler`. The runtime connection may contain `pooler`.

## Render Free deployment

Render Free does not support pre-deploy commands. With
`RUN_MIGRATIONS_ON_BOOT=true`, the container runs
`/usr/local/bin/render-migrate.sh` before PHP-FPM and Nginx start. The script
uses the direct Neon URI, applies only outstanding migrations, then seeds
missing essential settings when `SEED_ESSENTIALS_ON_MIGRATE=true`.

Use this only for a single Free web-service instance connected to the database.
A failed migration fails the new deployment and leaves the last successful
deployment running.

## Paid-service release procedure

1. Create a Neon branch from production and set both URLs to that branch.
2. Build the Render Docker image and execute `/usr/local/bin/render-migrate.sh`
   as a one-off/pre-deploy command. The script uses the direct URL and only
   runs outstanding Laravel migrations.
3. Inspect `php artisan migrate:status` and the command output. Promote only
   after it succeeds.
4. Set the production direct URI and execute the same script once. It seeds
   missing essential settings only after migrations succeed. Keep
   `RUN_MIGRATIONS_ON_BOOT=false` and `SEED_ESSENTIALS_ON_BOOT=false` so the
   web service cannot restart-loop.

Render’s pre-deploy command is the preferred automation for paid services. On
the configured free plan, run the script as a one-off release step before
deploying the web service; do not turn boot-time migrations back on.

The entrypoint intentionally ignores legacy `RUN_MIGRATIONS_ON_BOOT=true` and
`SEED_ESSENTIALS_ON_BOOT=true` values. This protects an existing Render service
where dashboard variables override `render.yaml`; update or remove those
variables after deploying this version.

## Read-only investigation of a failed database

Run these through a Laravel process configured with the direct URI, before any
retry. They read metadata only and do not change tables or data:

```sh
export DB_URL="$DB_MIGRATION_URL"
php artisan config:clear --no-interaction
php artisan migrate:status
php artisan tinker --execute="dump(DB::select(\"select table_name from information_schema.tables where table_schema = 'public' order by table_name\"));"
php artisan tinker --execute="dump(DB::select(\"select * from migrations order by batch, migration\"));"
```

Capture the first PostgreSQL error before `SQLSTATE[25P02]`. `25P02` only says
that a prior statement in the transaction failed. If an existing table is
missing from `migrations`, stop and reconcile that history from a backup or the
last successful deployment; do not delete the table or mark a migration as run
without confirming its full schema, constraints, and data.

`SettingSeeder` is safe to run after a successful migration: it uses
`firstOrCreate` and does not overwrite administrator settings.
