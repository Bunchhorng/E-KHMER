# Oracle Cloud deployment and recovery

This repository supplies a single-host Docker deployment for OCI Compute. Its default Compose project is `ekhmer-production`, keeping its containers and volumes separate from development. No cloud resources have been created and no production secrets have been installed by this change.

## 1. Infrastructure and private configuration

Provision a supported Linux OCI Compute instance with Docker Engine/Compose and enough memory/disk for PHP, MySQL, Redis, image builds and backups. Use a persistent disk and a reserved public IP. Point your domain's DNS at that address.

Allow inbound 80/443, and restrict SSH to administrator addresses in both OCI security rules and the host firewall. The production Compose file exposes only Caddy's web ports. Do not expose MySQL, Redis, PHP-FPM or phpMyAdmin. The development stack is not a production deployment.

Copy `.env.production.example` to the untracked `.env.production` on the server and restrict its permissions (`chmod 600 .env.production`). Fill in:

- `APP_DOMAIN`, `APP_URL`, `FRONTEND_URL` using the same real HTTPS domain.
- Strong independent `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD`. Keep `DB_PASSWORD` in sync with `MYSQL_PASSWORD` if used by other tooling. Compose supplies the live DB credentials to PHP.
- A stable Laravel `APP_KEY`; preserve a secure off-server copy. Do not regenerate it on every deployment.
- `BAKONG_API_TOKEN`, obtained through NBC's integrator registration/verification process. Keep `PAYMENT_MODE=production`.
- SMTP host, port, username, password and verified sender address. For a STARTTLS endpoint use `MAIL_SCHEME=smtp`; use the provider's documented configuration. Configure SPF/DKIM/DMARC with your mail provider.

Use a secret manager or protected server file; do not commit or paste these values into chat. Compose forwards only application settings to PHP; the MySQL root password is not forwarded to app/worker/scheduler. An Oracle instance alone does not provide application email credentials.

## 2. Build and initialize

Run from the checked-out repository on the server. All PHP/npm work remains in Docker.

```sh
docker compose --env-file .env.production -f compose.production.yml config --quiet
docker compose --env-file .env.production -f compose.production.yml build app web
docker compose --env-file .env.production -f compose.production.yml up -d mysql redis
```

On the first deployment only, if no application key exists, generate one with the following command and securely copy its output into `.env.production` as `APP_KEY`. The value is secret. Do not log it in CI.

```sh
docker compose --env-file .env.production -f compose.production.yml run --rm app php artisan key:generate --show
```

Apply schema changes, then start the application services:

```sh
docker compose --env-file .env.production -f compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yml up -d app worker scheduler web edge backup
docker compose --env-file .env.production -f compose.production.yml exec app php artisan ops:health --require-heartbeats
docker compose --env-file .env.production -f compose.production.yml ps
```

Allow a minute for scheduler/worker heartbeats. Caddy obtains HTTPS certificates when DNS and inbound HTTP/HTTPS work. Public uploads are served directly from the media volume; a development `public/storage` symlink is not copied into the production image.

Do not run demo seeders on production. Bootstrap a real administrator using your organization's controlled account-provisioning procedure and remove/change all demo credentials if importing development data.

## 3. Acceptance and routine operation

- Open `/`, `/up`, `/shops` and `/seller` over HTTPS. Verify login/reset/verification mail and queue delivery. Camera access requires HTTPS (localhost is suitable only for development).
- Configure each seller's banking and delivery settings. Test purchases/refunds with authorized real test amounts before launch. USD is the current checkout currency.
- Monitor `ops:health --require-heartbeats`, `queue:failed`, worker/scheduler logs, Caddy certificate errors, disk space and Bakong token/API failures.
- Check `docker compose ... logs backup` and backup container health every day. Failed backup jobs exit and restart; the health check flags a stale success marker. Connect these signals to an external alert destination; none is preconfigured.
- For updates, take a verified backup first, retain the previous image/release, build from a reviewed commit, apply forward-compatible migrations and recreate app/worker/scheduler/web. Do not roll back database migrations blindly after accepting new orders.
- Never use `migrate:fresh` or `docker compose down -v` on production.

## 4. Backup and isolated restore verification

The backup service writes a transaction-consistent MySQL dump and public/private media archives to the `backups` volume daily; it validates archive integrity before updating `latest`. A local volume is **not** an off-server backup. Configure encrypted copies to a private OCI Object Storage bucket (or your backup service), restricted IAM, retention/lifecycle rules and alerts. No OCI credentials or offsite job are configured here. The application key and private environment configuration need a separate encrypted backup.

For a point-in-time, application-consistent backup across database and files, use a short maintenance window: pause workers/scheduler and writes while capturing both, then restore service. Regular daily backups do not implement MySQL binlog point-in-time recovery.

Verify the latest archive in a separate, temporary MySQL server:

```sh
docker compose --env-file .env.production -f compose.production.yml --profile maintenance run --rm restore-check
docker compose --env-file .env.production -f compose.production.yml --profile maintenance stop restore-db
docker compose --env-file .env.production -f compose.production.yml --profile maintenance rm -f restore-db
```

Only the explicitly named disposable restore service is removed; live volumes are untouched. The restore script refuses the live `mysql` hostname and creates only `ecommerce_restore_check`. It refuses to overwrite an existing database. Temporary storage is discarded when this restore container is stopped. Archives are never extracted over live files by this check.

To run the same database/media archive test locally against development data:

```sh
docker compose --profile maintenance run --rm backup-check
docker compose --profile maintenance stop restore-db
docker compose --profile maintenance rm -f restore-db
```

For real disaster recovery, restore into a new isolated stack, restore both media volumes with the correct ownership, supply the original `APP_KEY` and secrets, verify order/payment/media consistency, and only then switch traffic. Do not treat a successful row count as full business acceptance.

## References

OCI supports Docker-based HTTPS deployments on Compute; this application uses its own Laravel/Vue stack, not the WordPress example in Oracle's [Docker deployment guide](https://docs.oracle.com/en/solutions/oci-free-wordpress-docker/index.html).
