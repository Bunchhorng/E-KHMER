# F17 — DEPENDENCY ADVISORIES & CI SECURITY GATE

Status:
NEEDS WORK

Initial Frontend Audit:

Critical:
0

High:
7

Moderate:
0 production / 1 full-tree development finding

Low:
0

Initial Composer Audit:

Critical:
0

High:
1 (`symfony/yaml`, CVE-2026-109413)

Moderate:
1 (`symfony/mime`, CVE-2026-45070)

Low:
0

Production Advisories Found:
`vue-i18n` 11.4.10 and its `@intlify/*` dependencies were affected by GHSA-8rc2-428q-7ww3 and GHSA-83rm-6c8r-g728. `follow-redirects` 1.16.0 came from direct dependency `axios` 1.20.0 and was affected by three high advisories. The production npm audit also identified Vite 8.2.2 through its lockfile dependency graph. Composer identified `symfony/mime` through Laravel/Symfony Mailer and `symfony/yaml` through Laravel Roster.

Development-Only Advisories Found:
The full initial npm audit reported `postcss-selector-parser` 6.1.4 (moderate) through Tailwind/PostCSS. Vite is declared as a development dependency, but it was also visible to npm's `--omit=dev` audit because of the lockfile's dev-optional graph; the gate therefore patches and audits it as well.

Dependencies Updated:
`vue-i18n` 11.4.10 → 11.4.17; `@intlify/core-base`, `@intlify/devtools-types`, `@intlify/message-compiler`, and `@intlify/shared` 11.4.10 → 11.4.17; `follow-redirects` 1.16.0 → 1.16.2; `vite` 8.2.2 → 8.3.5; `postcss-selector-parser` 6.1.4 → 6.1.5; `symfony/mime` 7.4.17 → 7.4.22; `symfony/yaml` 7.4.17 → 7.4.22; required Symfony polyfills were updated with them.

Direct Dependencies Updated:
Lockfile resolutions only: `vue-i18n` and `vite` stayed within their existing declared compatible ranges.

Transitive Dependencies Updated:
`@intlify/*`, `follow-redirects`, `postcss-selector-parser`, Vite/Rolldown support packages, and Symfony polyfills.

Dependencies Removed:
None

Vue I18n:
Old Version:
11.4.10

New Version:
11.4.17

Advisory Resolved:
YES

Symfony Mime:
Old Version:
7.4.17

New Version:
7.4.22

Advisory Resolved:
YES

Major-Version Upgrades:
None

Breaking Changes Required:
None

package.json Changed:
NO

package-lock.json Changed:
YES

composer.json Changed:
NO

composer.lock Changed:
YES

Final Production npm Audit:

Critical:
0

High:
0

Moderate:
0

Low:
0

Final Composer Audit:

Advisories:
0

Production Audit Policy:

Critical:
FAIL

High:
FAIL

Moderate:
FAIL

Low:
WARN

CI Security Job:

Job Name:
Dependency Security

npm Audit Command:
`docker compose exec -T frontend npm audit --omit=dev --audit-level=moderate --package-lock-only`

Composer Audit Command:
`docker compose exec -T app composer audit --no-dev`

Uses npm ci:
YES

Uses Composer Lockfile:
YES

continue-on-error:
NO

Audit || true:
NO

CI Mutates Dependencies:
NO

Clean Frontend Install:
PASS

Clean Backend Install:
PASS

Vue I18n Regression:
PASS — the existing Khmer admin overview Playwright case passed for desktop and mobile.

Mail/MIME Regression:
PASS — `MailMimeRenderingTest` renders order confirmation subject and HTML body.

Backend Tests:
Failed — 766 passed and 14 failed in `BusinessForecastApiTest` / `BusinessForecastSeederTest`. The failures are forecast-window and trend expectations (for example, expected `available` versus `insufficient_history`), unrelated to the lockfile-only dependency updates. The F17-specific mail-rendering regression passes.

Frontend Tests:
N/A — no frontend unit-test script is configured.

Browser Tests:
PASS — 2 Khmer locale Playwright cases passed.

Type Check:
PASS

Production Build:
PASS

F01 Regression:
PASS

F02 Regression:
PASS

F03 Regression:
PASS

F04 Regression:
PASS

F05 Regression:
PASS

F06 Regression:
PASS

F07 Regression:
PASS

F08 Regression:
PASS

F09 Regression:
PASS

F10 Regression:
PASS

F11 Regression:
PASS

F12 Regression:
PASS

F13 Regression:
PASS

F14 Regression:
PASS

F15 Regression:
PASS

F16 Regression:
PASS

Remaining Security Advisories:
None

Remaining Risks:
This gate reports npm and Composer advisories only. It does not scan Docker base images or prove the absence of application-level vulnerabilities. New advisories can appear after a passing run.

The existing business-forecast test failures prevent the full functional CI job from passing until their separate date-window/trend assertions are corrected. They are outside F17 scope and were not modified.

Recommended Required CI Check:
Dependency Security

Related Follow-Up:
F18 — Broken Barcode Browser Assertion

## Baseline and verification commands

Baseline environment: Node 22.23.2, npm 10.9.8, PHP 8.3.33, Composer 2.10.3; npm lockfile contains 252 packages (139 production, 112 development, 29 optional) and Composer has 12 direct packages.

Commands run before and after remediation:

```powershell
docker compose exec -T frontend npm audit --omit=dev --json
docker compose exec -T frontend npm audit --json
docker compose exec -T app composer audit --format=json
docker compose exec -T frontend npm ci
docker compose exec -T app composer install --no-interaction --prefer-dist --no-progress
docker compose exec -T app composer check-platform-reqs
```

The initial production npm and Composer audit commands exited non-zero. The final audit commands exited zero, which verifies the CI commands will block a reintroduced advisory under the declared policy.

## Before / after

```text
BEFORE

Frontend production dependency audit:
7 HIGH findings

Backend Composer audit:
1 HIGH and 1 MEDIUM finding

CI:
tests/build may pass
even when dependency advisories exist


AFTER

Dependencies:
patched to compatible secure versions

Frontend production audit:
0 blocking findings

Composer audit:
0 blocking advisories

CI:
Backend Tests
Frontend Tests
Type Check
Production Build
Dependency Security

If a blocking production advisory appears:

Dependency Security → FAIL

Merge/deployment cannot treat the security check as successful
```
