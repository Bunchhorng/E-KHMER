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
RUN_MIGRATIONS_ON_BOOT=false
```

`DB_MIGRATION_URL` must be the direct host from Neon’s Connect dialog: it must
not contain `pooler`. The runtime connection may contain `pooler`.

## Safe release procedure

1. Create a Neon branch from production and set both URLs to that branch.
2. Build the Render Docker image and execute `/usr/local/bin/render-migrate.sh`
   as a one-off/pre-deploy command. The script uses the direct URL and only
   runs outstanding Laravel migrations.
3. Inspect `php artisan migrate:status` and the command output. Promote only
   after it succeeds.
4. Set the production direct URI and execute the same script once. Keep
   `RUN_MIGRATIONS_ON_BOOT=false` so the web service cannot restart-loop.

Render’s pre-deploy command is the preferred automation for paid services. On
the configured free plan, run the script as a one-off release step before
deploying the web service; do not turn boot-time migrations back on.

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
