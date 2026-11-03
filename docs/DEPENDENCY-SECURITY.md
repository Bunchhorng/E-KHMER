# Dependency security

E-KHMER treats known production dependency vulnerabilities at moderate severity or above as merge-blocking. The **Dependency Security** GitHub Actions job installs the committed lockfiles and fails when either audit finds a blocking advisory.

Run the same checks with the project containers:

```powershell
docker compose exec -T frontend npm ci
docker compose exec -T frontend npm audit --omit=dev --audit-level=moderate --package-lock-only
docker compose exec -T app composer install --no-interaction --prefer-dist
docker compose exec -T app composer check-platform-reqs
docker compose exec -T app composer audit --no-dev
```

Use targeted dependency updates and review both lockfile diffs. Do not use `npm audit fix --force`, do not update dependencies in CI, and do not suppress audit failures with `|| true` or `continue-on-error`.

When an advisory has no compatible fix, record its ID, package, severity, affected range, dependency path, application exposure, temporary mitigation, owner, and follow-up issue before requesting an exception. An audit pass means no currently reported blocking advisories from the configured npm and Composer sources; it does not prove the application has no security defects.
