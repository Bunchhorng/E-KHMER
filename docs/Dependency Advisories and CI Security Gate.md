# E-KHMER Improvement #17 — Dependency Advisories & CI Security Gate

Act as a senior **Laravel + Vue.js + TypeScript Dependency Security Engineer, DevSecOps Engineer, CI/CD Engineer, Software Supply-Chain Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

Previous fixes:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
F03 — Discount Cap & Checkout Total Consistency
F04 — COD Collection & Paid-State Lifecycle
F05 — Guest Checkout Policy & Web Flow
F06 — Rating & Price Filters
F07 — Mobile Color Filters
F08 — Async Cart Mutations & Buy Now Errors
F09 — Formatted Product Descriptions
F10 — Review Aggregate Maintenance
F11 — Low-Stock Notification Lifecycle
F12 — Duplicate Coupon Validation
F13 — Checkout & Reservation Abuse Protection
F14 — Order Number Collision Handling
F15 — Report Metric Consistency
F16 — Export Validation & Bounded Memory
```

Do NOT regress those fixes.

Now work ONLY on:

# F17 — Dependency Advisories & CI Security Gate

---

# 1. CURRENT SECURITY FINDING

The previous project audit found:

```text
Frontend npm audit:
7 HIGH severity findings

Backend Composer audit:
1 MEDIUM severity finding
```

The audit specifically noted affected locked dependencies including:

```text
Vue I18n
Symfony Mime
```

Do NOT blindly upgrade packages based only on this old summary.

First inspect the current repository because dependencies may have changed since the audit.

---

# 2. PRIMARY GOAL

Create a secure, repeatable dependency-security process:

```text
Current lockfiles
      ↓
Production dependency audit
      ↓
Identify vulnerable direct/transitive dependencies
      ↓
Upgrade to compatible patched versions
      ↓
Run tests/build
      ↓
Rerun audits
      ↓
CI security gate
      ↓
Fail CI when real production vulnerabilities exceed policy
```

The final result should make dependency vulnerabilities visible and actionable before merge/deployment.

---

# 3. FIRST INSPECT THE ACTUAL REPOSITORY

Inspect:

```text
frontend/package.json
frontend/package-lock.json

backend/composer.json
backend/composer.lock

.github/workflows/
```

Especially:

```text
.github/workflows/ci.yml
```

Also inspect:

```text
Dockerfile
docker-compose.yml
docker-compose.*.yml

frontend Dockerfile
backend Dockerfile

Node version
PHP version
Composer version

CI build commands
test commands
lint commands
type-check commands
```

Do not assume the dependency state is unchanged.

---

# 4. CAPTURE CURRENT BASELINE FIRST

Before modifying dependencies, record:

```text
Node version
npm version

PHP version
Composer version

frontend package count
backend package count

current npm audit result
current composer audit result
```

Include exact commands and outputs in the final report.

---

# 5. USE LOCKFILES

Dependency remediation must preserve reproducible builds.

Do NOT delete:

```text
package-lock.json
composer.lock
```

and regenerate everything blindly.

Update intentionally.

---

# 6. FRONTEND AUDIT

Run the actual production dependency audit.

Use the repository's current npm version.

Typical command:

```bash
npm audit --omit=dev
```

or the project's equivalent.

Also inspect:

```bash
npm audit
```

to understand development-only findings.

But distinguish clearly:

```text
production dependency vulnerability
```

from:

```text
development/tooling-only vulnerability
```

---

# 7. BACKEND AUDIT

Run Composer's actual audit.

Typical:

```bash
composer audit
```

Inspect:

```text
package
affected versions
advisory ID
severity
patched version
dependency path
```

Do not rely only on package names from the old audit.

---

# 8. DO NOT USE BLIND FORCE FIXES

Do NOT immediately run:

```bash
npm audit fix --force
```

This can introduce major-version upgrades and application breakage.

Start with:

```bash
npm audit fix
```

only if its changes are understood.

Prefer targeted upgrades.

---

# 9. DIRECT VS TRANSITIVE DEPENDENCIES

For every advisory determine:

```text
Direct dependency?
Transitive dependency?
```

Example:

```text
Vue I18n
→ maybe direct

vulnerable library X
→ dependency of package Y
```

The remediation strategy differs.

---

# 10. TRACE NPM DEPENDENCY PATHS

Use tools such as:

```bash
npm ls <package>
npm explain <package>
```

Determine why each vulnerable package exists.

Do not upgrade unrelated libraries.

---

# 11. TRACE COMPOSER DEPENDENCY PATHS

Use:

```bash
composer why <package>
```

and, when useful:

```bash
composer why-not <package> <safe-version>
```

Determine which Laravel/Symfony package constrains the vulnerable dependency.

---

# 12. CHECK CURRENT SAFE VERSION

For every vulnerability determine:

```text
installed version
affected range
first safe version
compatible safe version
```

Do not assume "latest" is always the correct upgrade.

Prefer the smallest safe compatible version first.

---

# 13. SEMVER SAFETY

Prefer:

```text
patch upgrade
```

then:

```text
minor upgrade
```

before considering:

```text
major upgrade
```

Major upgrades require explicit compatibility review.

---

# 14. VUE I18N

Inspect the actual Vue I18n version.

Determine:

```text
installed version
current advisory
patched version
Vue compatibility
API changes
```

Upgrade to a safe compatible version.

Then test:

```text
English translations
Khmer translations
locale switching
fallback locale
pluralization if used
```

Do not upgrade Vue I18n without exercising actual localization behavior.

---

# 15. SYMFONY MIME

Inspect:

```text
symfony/mime
```

and determine why it is installed.

Likely through:

```text
Laravel mail
Symfony mailer
```

but confirm using Composer.

Upgrade through compatible Laravel/Symfony dependency resolution.

Do not force an incompatible Symfony component version.

---

# 16. LARAVEL COMPATIBILITY

If a Symfony package upgrade is constrained by Laravel:

inspect Laravel's allowed dependency range.

Do NOT modify vendor files.

Use Composer dependency resolution properly.

---

# 17. TRANSITIVE PACKAGE REMEDIATION

If vulnerable package is transitive:

Possible fixes:

```text
upgrade parent dependency
update lockfile package
replace obsolete package
remove unused parent dependency
```

Choose the minimal safe option.

---

# 18. UNUSED DEPENDENCIES

Check whether vulnerable direct dependencies are actually used.

If a package is unused:

prefer:

```text
remove dependency
```

rather than maintaining unnecessary attack surface.

Verify with code search first.

---

# 19. DO NOT REMOVE NEEDED FEATURES

Do not remove:

```text
localization
email
PDF/reporting
authentication
```

simply to make audit output disappear.

Remediation must preserve required functionality.

---

# 20. PRODUCTION VS DEVELOPMENT FINDINGS

Classify each npm advisory:

```text
Production runtime
Development/build only
```

Production findings are highest priority.

Development findings still matter but may follow a different CI policy.

Document both.

---

# 21. SECURITY SEVERITY POLICY

Define CI policy explicitly.

Recommended baseline:

```text
Production dependencies:

Critical:
FAIL

High:
FAIL

Moderate:
FAIL or explicitly reviewed based on project policy

Low:
report
```

For a marketplace handling orders/payments, stricter production dependency policy is preferred.

Do not silently ignore moderate findings without documented reasoning.

---

# 22. COMPOSER POLICY

Composer audit should fail CI when an active production advisory exists.

Do not use:

```text
|| true
```

around Composer security audit.

Bad:

```bash
composer audit || true
```

This hides failures.

---

# 23. NPM POLICY

Likewise avoid:

```bash
npm audit --omit=dev || true
```

if production vulnerabilities are intended to block CI.

The CI exit code must matter.

---

# 24. CI SECURITY JOB

Add or improve a dedicated job, for example:

```text
dependency-security
```

Conceptually:

```text
Checkout
↓
Set up Node
↓
npm ci
↓
npm production audit

Set up PHP
↓
composer install
↓
composer audit
```

Use existing CI organization.

---

# 25. NPM CI MUST USE NPM CI

In CI prefer:

```bash
npm ci
```

not:

```bash
npm install
```

because:

```text
npm ci
```

uses lockfile exactly.

---

# 26. COMPOSER INSTALL

Use a deterministic Composer install.

Typical:

```bash
composer install \
  --no-interaction \
  --prefer-dist
```

For production security audit, do not accidentally omit packages that matter to runtime.

---

# 27. DO NOT RUN COMPOSER UPDATE IN CI

CI should verify committed lockfile.

Do NOT run:

```bash
composer update
```

as part of normal CI.

Dependency updates belong in reviewed commits.

---

# 28. DO NOT RUN NPM AUDIT FIX IN CI

CI should detect problems.

It should not automatically rewrite dependencies.

Bad:

```text
CI
→ npm audit fix
→ silently changes build
```

---

# 29. SEPARATE AUDIT FROM REMEDIATION

Correct workflow:

```text
developer updates dependencies
↓
commits lockfiles
↓
CI audits committed state
```

---

# 30. CI ORDER

Security audit can run in parallel with:

```text
backend tests
frontend tests
frontend build
```

where CI infrastructure supports it.

Do not unnecessarily serialize all jobs.

---

# 31. FAIL FAST

Dependency audit job should fail clearly if vulnerable production packages are found.

Make the failure understandable.

---

# 32. CI LOG OUTPUT

Logs should show:

```text
package
severity
advisory
affected version
```

Do not hide output.

---

# 33. NO FAKE PASS

Do not:

```text
grep output
then always exit 0
```

just to produce green CI.

Use reliable command exit status or structured audit parsing.

---

# 34. AUDIT JSON

If CI needs severity filtering, structured output may be useful.

Example:

```bash
npm audit --json
```

But do not write a fragile parser unless necessary.

Use npm's built-in:

```text
--audit-level
```

where appropriate.

---

# 35. NPM AUDIT LEVEL

If policy is:

```text
high and critical block CI
```

use an appropriate command such as:

```bash
npm audit --omit=dev --audit-level=high
```

Confirm behavior with the project's npm version.

Do not assume CLI semantics without testing.

---

# 36. DEVELOPMENT AUDIT

Optionally run a separate informational audit:

```bash
npm audit
```

for all dependencies.

But production audit should be the merge gate.

---

# 37. COMPOSER ABANDONED PACKAGES

Composer may also report:

```text
abandoned packages
```

Document them separately.

Do not confuse:

```text
abandoned package
```

with:

```text
known security advisory
```

Both may deserve follow-up.

---

# 38. PACKAGE INSTALL SCRIPTS

Review suspicious/unnecessary install scripts only if dependency audit exposes relevant concerns.

Do not expand F17 into a complete npm supply-chain redesign.

---

# 39. LOCKFILE DIFF REVIEW

After updates, review:

```text
package-lock.json
composer.lock
```

Ensure the changes correspond to intended upgrades.

Avoid massive unrelated lockfile churn.

---

# 40. PACKAGE.JSON DIFF

Only change version constraints when necessary.

If patched version already fits current range:

```text
lockfile-only update
```

may be sufficient.

---

# 41. COMPOSER.JSON DIFF

Same principle.

Do not loosen Laravel/Symfony constraints unnecessarily.

---

# 42. MAJOR FRONTEND UPGRADE

If a vulnerability requires a major dependency upgrade:

inspect:

```text
breaking changes
migration guide
TypeScript types
Vue compatibility
```

Then update affected application code.

Document the migration.

---

# 43. MAJOR BACKEND UPGRADE

If a vulnerability requires a Laravel/Symfony major change:

do not casually perform a framework migration under F17 unless unavoidable.

If unavoidable:

document it clearly and run full regression.

If no safe compatible fix exists, report it honestly rather than hiding the advisory.

---

# 44. AUDIT EXCEPTIONS

If an advisory cannot currently be fixed:

do NOT silently suppress it.

Document:

```text
advisory ID
package
severity
reason no safe update exists
actual application exposure
temporary mitigation
owner
follow-up
```

Suppression should be exceptional.

---

# 45. EXPLOITABILITY

A dependency advisory does not automatically prove E-KHMER is exploitable.

Do not claim exploitation without evidence.

But a lack of demonstrated exploitability does not mean the advisory should be ignored.

---

# 46. DO NOT DISABLE SECURITY FEATURES

Never "fix" audits by disabling:

```text
npm audit
composer audit
GitHub security checks
```

---

# 47. PACKAGE SOURCE

Use official npm/Composer registries according to existing project configuration.

Do not switch to unofficial mirrors without requirement.

---

# 48. PRIVATE PACKAGES

If none exist, no change.

If private registries exist:

do not expose tokens in:

```text
workflow YAML
logs
committed config
```

---

# 49. CI SECRETS

Dependency audit should not need production credentials.

Do not inject:

```text
payment secrets
database production password
mail credentials
```

into security-audit job.

---

# 50. MINIMUM PERMISSIONS

GitHub Actions job should use minimum required permissions.

Example:

```yaml
permissions:
  contents: read
```

if nothing else is needed.

Do not grant write access unnecessarily.

---

# 51. PIN ACTION VERSIONS

Inspect current GitHub Actions.

Use maintained stable action versions.

Do not make broad action-supply-chain changes unless current actions are obviously obsolete/insecure.

---

# 52. NODE VERSION CONSISTENCY

Ensure CI Node version matches project-supported runtime.

Avoid:

```text
local Node 22
CI Node 18
production Node 20
```

without reason.

---

# 53. PHP VERSION CONSISTENCY

Same for PHP.

CI should match supported backend runtime.

---

# 54. COMPOSER PLATFORM REQUIREMENTS

Run:

```bash
composer check-platform-reqs
```

where appropriate after install.

This can catch mismatched:

```text
PHP version
extensions
```

Do not use it as a substitute for security audit.

---

# 55. FRONTEND CLEAN INSTALL

After dependency changes:

```bash
npm ci
```

must succeed from a clean state.

Do not rely on stale local `node_modules`.

---

# 56. BACKEND CLEAN INSTALL

After dependency changes:

Composer install from lockfile must succeed cleanly.

Do not rely on old `vendor`.

---

# 57. FRONTEND TYPE CHECK

Run:

```text
TypeScript type check
```

after upgrades.

Do not suppress new type errors with:

```text
any
@ts-ignore
```

unless genuinely justified.

---

# 58. FRONTEND PRODUCTION BUILD

Run the actual production build.

Must succeed.

---

# 59. FRONTEND TESTS

Run existing frontend tests.

Pay special attention to dependencies touching:

```text
Vue
router
i18n
state management
validation
HTTP client
charts
```

---

# 60. BACKEND TESTS

Run backend suite.

Pay special attention to packages touching:

```text
mail
MIME
queues
notifications
HTTP responses
filesystem
```

---

# 61. MAIL REGRESSION

Because Symfony Mime was previously identified:

test at least:

```text
order confirmation mail rendering
password/reset mail if present
low-stock email if present
support/contact mail if present
```

No real SMTP is required for unit/feature tests.

---

# 62. ATTACHMENT REGRESSION

If emails attach:

```text
receipts
PDFs
exports
```

test MIME generation.

---

# 63. I18N REGRESSION

Because Vue I18n was previously identified:

verify:

```text
English
Khmer
locale switching
fallback strings
interpolation
```

No blank/missing UI caused by upgrade.

---

# 64. ROUTER REGRESSION

If frontend dependencies are upgraded indirectly:

run browser tests around:

```text
login
product listing
cart
checkout
admin
seller
```

---

# 65. SECURITY AUDIT AFTER UPDATE

After all upgrades, rerun:

```text
npm production audit
composer audit
```

Do not stop after package install succeeds.

---

# 66. EXPECTED TARGET

Preferred:

```text
npm production audit:
0 blocking findings

composer audit:
0 active security advisories
```

If not achievable:

report exact remaining advisory and reason.

---

# 67. DO NOT HIDE DEV FINDINGS

If production audit is clean but dev audit still reports issues:

report:

```text
Production:
PASS

Development:
X remaining
```

Do not imply the entire dependency tree is clean.

---

# 68. CI GATE TEST

Intentionally verify that the audit job can fail.

Do not commit a vulnerable package just for testing.

Instead inspect/run the command locally and verify non-zero behavior using a safe test/mocked workflow approach if practical.

Goal:

```text
audit failure
→ CI job fails
```

---

# 69. CI SUCCESS TEST

With patched lockfiles:

```text
dependency-security
→ PASS
```

---

# 70. NO `continue-on-error`

Security audit job must not use:

```yaml
continue-on-error: true
```

if it is intended as a gate.

---

# 71. NO `if: always()` TO MASK FAILURE

Using `if: always()` for reporting is fine.

But do not turn failed audit into successful overall job.

---

# 72. BRANCH PROTECTION

Do not change repository branch-protection settings unless access/tooling supports it and the task explicitly requires it.

Instead ensure the CI job exists and has a stable name that can be made required.

Document recommended required check name.

---

# 73. CI JOB NAME

Use a clear name such as:

```text
Dependency Security
```

or repository convention.

Avoid ambiguous:

```text
Check 2
```

---

# 74. CACHE

GitHub Actions may cache:

```text
npm
Composer downloads
```

but the cache must not replace lockfile integrity.

Use standard action caching if already present.

---

# 75. NPM CACHE

Do not cache `node_modules` unless current workflow intentionally does.

Prefer npm package cache + `npm ci`.

---

# 76. COMPOSER CACHE

Composer package cache is fine.

Still install from committed lockfile.

---

# 77. LOCKFILE INTEGRITY

CI should fail if:

```text
package.json
```

and:

```text
package-lock.json
```

are inconsistent.

`npm ci` naturally helps enforce this.

---

# 78. COMPOSER LOCK CONSISTENCY

Composer should warn/fail appropriately if lockfile does not satisfy composer.json.

Do not regenerate in CI.

---

# 79. DEPENDABOT / RENOVATE

If the repo already uses automated dependency update tooling, inspect it.

Do not introduce a large new bot setup unless useful and low-risk.

F17's required outcome is:

```text
clean dependency state
+
CI audit gate
```

---

# 80. OPTIONAL DEPENDABOT

If no automation exists, you may recommend—not necessarily implement—Dependabot for:

```text
npm
Composer
GitHub Actions
```

Keep it separate from core acceptance criteria.

---

# 81. LICENSE CHECKING

Do not expand F17 into comprehensive license compliance unless current tooling already performs it.

---

# 82. SBOM

Do not build a full SBOM pipeline unless current project already has one.

Could be future hardening.

---

# 83. CONTAINER IMAGE AUDIT

Do not expand into Docker image vulnerability scanning unless CI already has it.

F17 specifically concerns npm/Composer findings and their CI gate.

---

# 84. OS PACKAGE VULNERABILITIES

Similarly, document separately if discovered.

Do not delay F17 core fix.

---

# 85. SECURITY UPDATE DOCUMENTATION

Create/update project security/dependency documentation.

Include:

```text
How to audit frontend dependencies

How to audit backend dependencies

How to update safely

What severity blocks CI

What to do when no fix exists
```

---

# 86. LOCAL DEVELOPER COMMANDS

Document commands similar to:

```bash
cd frontend
npm ci
npm audit --omit=dev --audit-level=high

cd ../backend
composer install
composer audit
```

Adapt to Docker workflow.

---

# 87. DOCKER-BASED COMMANDS

E-KHMER uses Docker.

Prefer project-standard container commands.

For example conceptually:

```text
docker compose exec frontend npm audit ...

docker compose exec app composer audit
```

Use actual service names.

Do not invent them without checking compose files.

---

# 88. CI SHOULD NOT REQUIRE RUNNING DOCKER COMPOSE

GitHub Actions may use native setup actions rather than full Docker Compose if current workflow already does.

Follow existing architecture.

---

# 89. DEPENDENCY UPDATE COMMIT SCOPE

Keep F17 changes focused:

```text
package manifests
lockfiles
compatibility code if required
CI workflow
security docs
tests
```

Do not refactor unrelated application modules.

---

# 90. FRONTEND AUDIT TEST

Record:

```text
Before:
high = X
critical = X

After:
high = X
critical = X
```

Use actual results.

---

# 91. COMPOSER AUDIT TEST

Record:

```text
Before:
advisories = X

After:
advisories = X
```

---

# 92. VUE I18N TEST

If updated:

verify a translated page.

Example:

```text
English:
Orders

Khmer:
ការបញ្ជាទិញ
```

Use actual project translations.

---

# 93. SYMFONY MIME TEST

If updated:

create/render an application mail.

Verify:

```text
subject
HTML body
text body if applicable
attachments
headers
```

work.

---

# 94. FRONTEND BUILD TEST

Run:

```text
npm run build
```

or project equivalent.

Must pass.

---

# 95. TYPE CHECK TEST

Run:

```text
npm run type-check
```

or actual command.

Must pass.

---

# 96. BACKEND FULL TEST

Run full PHPUnit/Pest suite according to repository.

Do not only run a single package-specific test.

---

# 97. FRONTEND BROWSER TESTS

Run relevant existing browser suite, especially if dependency changes affect runtime packages.

If entire suite is expensive, at minimum run critical commerce flows plus existing standard acceptance suite.

Document what actually ran.

---

# 98. F01 REGRESSION

Shop coupon isolation unaffected.

---

# 99. F02 REGRESSION

Cart recovery unaffected.

---

# 100. F03 REGRESSION

Financial totals unaffected.

---

# 101. F04 REGRESSION

COD lifecycle unaffected.

---

# 102. F05 REGRESSION

Guest/account checkout unaffected.

---

# 103. F06 REGRESSION

Catalog filtering unaffected.

---

# 104. F07 REGRESSION

Mobile color filtering unaffected.

---

# 105. F08 REGRESSION

Async cart actions unaffected.

---

# 106. F09 REGRESSION

Sanitized rich descriptions unaffected.

---

# 107. F10 REGRESSION

Review aggregate maintenance unaffected.

---

# 108. F11 REGRESSION

Low-stock lifecycle unaffected.

---

# 109. F12 REGRESSION

Coupon validation unaffected.

---

# 110. F13 REGRESSION

Checkout abuse protection unaffected.

---

# 111. F14 REGRESSION

Order-number collision handling unaffected.

---

# 112. F15 REGRESSION

Reporting metrics unaffected.

---

# 113. F16 REGRESSION

Export validation/streaming unaffected.

---

# 114. CI FULL PIPELINE

Final CI should include or preserve:

```text
Backend tests
Frontend type check
Frontend tests
Frontend production build
Dependency security audit
```

Do not replace functional tests with audit checks.

---

# 115. SECURITY JOB SHOULD BE INDEPENDENTLY VISIBLE

It should be easy to see:

```text
Dependency Security:
PASS / FAIL
```

from CI.

---

# 116. NO SECURITY WARNING SUPPRESSION

Search CI/config for:

```text
audit=false
ignore-platform-reqs
no-audit
|| true
continue-on-error
```

Determine whether any suppress security checks.

Do not remove legitimate flags without understanding their purpose.

---

# 117. `--IGNORE-PLATFORM-REQS`

If Composer uses:

```text
--ignore-platform-reqs
```

in CI, inspect why.

This can hide runtime incompatibilities.

Remove only if project environment correctly provides required extensions.

---

# 118. NPM `--LEGACY-PEER-DEPS`

If used:

inspect why.

Do not remove blindly if dependency tree currently requires it.

But document technical debt.

---

# 119. DEPRECATED PACKAGES

Record major deprecated packages encountered during dependency review.

Do not expand scope unless they create current security risk.

---

# 120. PACKAGE ENGINE REQUIREMENTS

After updates verify:

```text
Node engine
PHP version
Laravel compatibility
Vue compatibility
```

against Docker and CI.

---

# 121. CHANGELOG REVIEW

For updated direct dependencies, inspect release notes/changelog when available in repository/package metadata.

Look for:

```text
breaking changes
security fixes
removed APIs
```

Do not blindly trust semver for every ecosystem package.

---

# 122. SECURITY PATCH TESTING

If package vulnerability affects a feature E-KHMER uses:

add a targeted regression test where practical.

Example:

```text
i18n rendering
mail MIME generation
```

---

# 123. DO NOT CLAIM FULL SECURITY

Passing dependency audits means:

```text
no currently reported blocking advisories under configured audit sources
```

It does NOT mean:

```text
application has no security vulnerabilities
```

Use precise wording.

---

# 124. DOCUMENT REMAINING RISK

Possible remaining risks:

```text
development-only advisories
unscanned Docker base images
future vulnerabilities
packages with no available patch
```

Report them honestly.

---

# 125. DO NOT START F18

Only solve F17.

Do NOT fix:

```text
commerce.spec.ts barcode assertion
```

That is:

```text
F18 — Broken Barcode Browser Assertion
```

---

# 126. DEFINITION OF DONE

Do not mark F17 complete until:

```text
[ ] Current npm audit baseline captured

[ ] Current Composer audit baseline captured

[ ] Every production advisory is identified

[ ] Direct/transitive path is understood

[ ] Safe patched versions are identified

[ ] Compatible dependency upgrades are applied

[ ] Lockfiles are intentionally updated

[ ] No blind npm audit --force fix used

[ ] Vue I18n regression tested if updated

[ ] Symfony Mime/mail regression tested if updated

[ ] Clean npm ci succeeds

[ ] Clean Composer install succeeds

[ ] Frontend type check passes

[ ] Frontend production build passes

[ ] Frontend tests pass

[ ] Backend full test suite passes

[ ] Relevant browser tests pass

[ ] Production npm audit satisfies security policy

[ ] Composer audit reports no blocking advisories

[ ] Development-only findings are documented separately

[ ] CI dependency-security job exists

[ ] CI audit failures return non-zero

[ ] Security job does not use continue-on-error

[ ] Security commands are not followed by || true

[ ] CI does not mutate dependency versions

[ ] CI uses committed lockfiles

[ ] Severity policy is documented

[ ] Remaining exceptions are explicitly documented

[ ] F01–F16 regressions pass
```

---

# 127. FINAL REPORT

At completion report exactly:

```text
F17 — DEPENDENCY ADVISORIES & CI SECURITY GATE

Status:
PASS / NEEDS WORK

Initial Frontend Audit:

Critical:
High:
Moderate:
Low:

Initial Composer Audit:

Critical:
High:
Moderate:
Low:

Production Advisories Found:
List

Development-Only Advisories Found:
List

Dependencies Updated:
List old → new

Direct Dependencies Updated:
List

Transitive Dependencies Updated:
List

Dependencies Removed:
None / List

Vue I18n:
Old Version:
New Version:
Advisory Resolved:
YES / NO / N/A

Symfony Mime:
Old Version:
New Version:
Advisory Resolved:
YES / NO / N/A

Major-Version Upgrades:
None / List

Breaking Changes Required:
None / List

package.json Changed:
YES / NO

package-lock.json Changed:
YES / NO

composer.json Changed:
YES / NO

composer.lock Changed:
YES / NO

Final Production npm Audit:

Critical:
High:
Moderate:
Low:

Final Composer Audit:

Advisories:
0 / List

Production Audit Policy:

Critical:
FAIL / WARN

High:
FAIL / WARN

Moderate:
FAIL / WARN

Low:
FAIL / WARN

CI Security Job:

Job Name:

npm Audit Command:

Composer Audit Command:

Uses npm ci:
YES / NO

Uses Composer Lockfile:
YES / NO

continue-on-error:
YES / NO

Audit || true:
YES / NO

CI Mutates Dependencies:
YES / NO

Clean Frontend Install:
PASS / FAIL

Clean Backend Install:
PASS / FAIL

Vue I18n Regression:
PASS / FAIL / N/A

Mail/MIME Regression:
PASS / FAIL / N/A

Backend Tests:
Passed / Failed

Frontend Tests:
Passed / Failed

Browser Tests:
Passed / Failed / N/A

Type Check:
PASS / FAIL

Production Build:
PASS / FAIL

F01 Regression:
PASS / FAIL

F02 Regression:
PASS / FAIL

F03 Regression:
PASS / FAIL

F04 Regression:
PASS / FAIL

F05 Regression:
PASS / FAIL

F06 Regression:
PASS / FAIL

F07 Regression:
PASS / FAIL

F08 Regression:
PASS / FAIL

F09 Regression:
PASS / FAIL

F10 Regression:
PASS / FAIL

F11 Regression:
PASS / FAIL

F12 Regression:
PASS / FAIL

F13 Regression:
PASS / FAIL

F14 Regression:
PASS / FAIL

F15 Regression:
PASS / FAIL

F16 Regression:
PASS / FAIL

Remaining Security Advisories:
None / List

Remaining Risks:

Recommended Required CI Check:

Related Follow-Up:
F18 — Broken Barcode Browser Assertion
```

Also provide this before/after example:

```text
BEFORE

Frontend production dependency audit:

7 HIGH findings

Backend Composer audit:

1 MEDIUM finding

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

Stop after F17.

Do NOT automatically begin F18.

I will review the result first.