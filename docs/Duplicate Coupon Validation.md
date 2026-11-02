# E-KHMER Improvement #12 — Duplicate Coupon Validation

Act as a senior **Laravel + Vue.js + MySQL Marketplace Engineer, Validation Engineer, Database Integrity Engineer, Coupon Domain Engineer, Security Engineer, and QA Engineer**.

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
```

Do NOT regress those fixes.

Now work ONLY on:

# F12 — Duplicate Coupon Validation

---

# 1. CURRENT DEFECT

The current audit found that creating or updating a coupon with an existing coupon code can pass application-level validation.

Example:

```text
Existing coupon:

Code:
SAVE20
```

Admin creates another coupon:

```text
Code:
SAVE20
```

Current broken flow:

```text
Request validation
→ PASS

Controller / Service
→ attempts INSERT

MySQL unique constraint
→ FAIL

Database exception
```

Instead, the application should reject the duplicate before the database insert attempt.

Expected:

```text
HTTP 422

Field:
code

Message:
The coupon code has already been taken.
```

or the project's equivalent customer-friendly validation message.

---

# 2. PRIMARY GOAL

Implement a complete duplicate-coupon validation lifecycle:

```text
Coupon Form
      ↓
Laravel Request Validation
      ↓
Normalized Coupon Code
      ↓
Uniqueness Validation
      ↓
Coupon Service
      ↓
Database Unique Constraint
      ↓
Safe Success / Safe Conflict Handling
```

Use both:

```text
Application validation
+
Database uniqueness
```

Application validation improves UX.

Database uniqueness remains the final integrity safeguard.

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing anything.

Focus especially on:

```text
backend/app/Http/Requests/
backend/app/Http/Controllers/Api/Admin/
backend/app/Services/CouponService.php
backend/app/Models/Coupon.php
```

Also inspect:

```text
coupon migrations
database unique indexes

frontend coupon management views
frontend coupon API
frontend validation/error helpers

checkout coupon validation
coupon usage models
shop coupon relationships

existing coupon tests
admin browser tests
```

Search globally for:

```text
Coupon
coupon_code
code
unique
Rule::unique
store coupon
update coupon
create coupon
```

Do not assume the current field is named exactly `code`.

---

# 4. CONFIRM DATABASE UNIQUENESS

Inspect the actual coupon table.

Determine whether the database currently has:

```text
UNIQUE(code)
```

or something like:

```text
UNIQUE(shop_id, code)
```

This is critical.

Do NOT change validation rules until you know what uniqueness scope the business actually requires.

---

# 5. DEFINE THE COUPON UNIQUENESS POLICY

Determine which rule E-KHMER should use.

Potential models:

## Option A — Marketplace-global code uniqueness

```text
SAVE20
```

can exist only once across the entire marketplace.

Example:

```text
Shop A:
SAVE20

Shop B:
SAVE20
```

Not allowed.

---

## Option B — Shop-scoped uniqueness

Each shop may reuse the same code.

Example:

```text
Shop A:
SAVE20

Shop B:
SAVE20
```

Allowed.

But:

```text
Shop A:
SAVE20

Shop A:
SAVE20
```

Not allowed.

---

## Option C — Platform and Shop Scope

If E-KHMER supports:

```text
platform coupons
+
shop coupons
```

define exactly how conflicts work.

Example questions:

```text
Can platform SAVE20 coexist with Shop A SAVE20?

Can two shops use SAVE20?

Does coupon lookup include shop context?
```

Inspect F01 implementation and current database constraints.

Do NOT invent a new uniqueness policy.

---

# 6. PRESERVE F01

F01 established coupon shop ownership and eligibility.

F12 must not weaken:

```text
shop ownership
eligible subtotal
shop coupon scope
mixed-shop behavior
```

Uniqueness validation must align with F01's coupon model.

---

# 7. NORMALIZE COUPON CODE

Inspect whether coupon codes are currently normalized.

Recommended concept:

```text
trim whitespace
normalize case
```

Example:

```text
" SAVE20 "
→ "SAVE20"
```

and potentially:

```text
"save20"
→ "SAVE20"
```

if E-KHMER treats coupon codes case-insensitively.

Do NOT implement case normalization without checking existing checkout behavior.

---

# 8. CASE SENSITIVITY

Determine whether these should be considered the same:

```text
SAVE20
save20
Save20
```

Recommended marketplace behavior is usually:

```text
case-insensitive
```

but use current business rules.

The database, validation, checkout lookup, and UI must all agree.

---

# 9. DATABASE COLLATION

Do not rely accidentally on MySQL collation.

Example:

```text
utf8mb4_unicode_ci
```

may already make:

```text
SAVE20
save20
```

equivalent at the database level.

Explicitly document application semantics.

---

# 10. STORE VALIDATION

For coupon creation, apply correct Laravel uniqueness validation.

Concept for global uniqueness:

```php
Rule::unique('coupons', 'code')
```

Concept for shop-scoped uniqueness:

```php
Rule::unique('coupons', 'code')
    ->where('shop_id', $shopId)
```

Do not copy this blindly.

Use the real schema and authorization context.

---

# 11. UPDATE VALIDATION

Critical.

Editing an existing coupon must ignore itself.

Example:

Existing:

```text
Coupon ID:
10

Code:
SAVE20
```

Admin edits:

```text
Description
Expiry Date
Discount Value
```

without changing code.

This must pass.

Use equivalent logic to:

```php
Rule::unique(...)
    ->ignore($coupon->id)
```

with correct scope.

---

# 12. UPDATE TO ANOTHER EXISTING CODE

Example:

```text
Coupon A:
SAVE10

Coupon B:
SAVE20
```

Edit Coupon A:

```text
SAVE10
→ SAVE20
```

Expected:

```text
422

code:
Coupon code already exists.
```

No database exception.

---

# 13. SHOP-SCOPED UPDATE

If uniqueness is shop-scoped:

```text
Shop A Coupon A:
SAVE10

Shop A Coupon B:
SAVE20
```

Coupon A cannot become:

```text
SAVE20
```

But if Shop B has:

```text
SAVE20
```

that may be acceptable depending on policy.

Test explicitly.

---

# 14. DO NOT TRUST FRONTEND SHOP_ID

If coupon is created from:

```text
/seller/shops/{shop}/coupons
```

derive authorized shop context server-side.

Do NOT allow user to manipulate:

```json
{
  "shop_id": 999
}
```

to influence uniqueness scope.

Preserve F01 shop authorization.

---

# 15. PLATFORM COUPON CONTEXT

If Super Admin can create platform coupons:

```text
shop_id = null
```

validate uniqueness according to actual policy.

Do not accidentally compare:

```text
NULL
```

incorrectly in scoped uniqueness queries.

---

# 16. WHITESPACE DUPLICATES

Test:

```text
Existing:
SAVE20

New:
" SAVE20 "
```

If trimming is current policy:

Expected:

```text
duplicate
```

not a second logically equivalent coupon.

---

# 17. CASE DUPLICATES

If case-insensitive:

```text
Existing:
SAVE20

New:
save20
```

Expected:

```text
duplicate
```

---

# 18. SPECIAL CHARACTERS

Inspect current coupon format validation.

Possible valid examples:

```text
SAVE20
BLACKFRIDAY
WELCOME-10
NEW_USER
```

Preserve current supported syntax.

Do not broaden coupon format unnecessarily.

---

# 19. EMPTY CODE

Continue rejecting:

```text
""
null
whitespace-only
```

with normal required validation.

Do not allow normalization to turn whitespace into an empty value after required validation without checking it again.

---

# 20. LENGTH VALIDATION

Preserve or define coupon code length.

Example:

```text
max: 50
```

according to database column size.

Do not allow application validation to accept more than DB field capacity.

---

# 21. UNIQUE INDEX REMAINS REQUIRED

Even with Laravel validation:

Do NOT remove the database unique constraint.

Race condition example:

```text
Request A:
checks SAVE20
→ available

Request B:
checks SAVE20
→ available

Request A:
insert

Request B:
insert
```

Only the database can guarantee final uniqueness.

---

# 22. CONCURRENT CREATE

Test or reason explicitly about:

```text
two admins create SAVE20 at the same time
```

Expected:

```text
one succeeds
one fails safely
```

Not:

```text
500 Internal Server Error
```

---

# 23. DATABASE EXCEPTION FALLBACK

Even with pre-validation, catch/translate duplicate-key race conditions where appropriate.

Concept:

```text
unique constraint violation
→ convert to safe validation/conflict response
```

Do NOT expose:

```text
SQLSTATE[23000]
Duplicate entry
index name
database schema
```

to frontend users.

---

# 24. HTTP STATUS

Recommended normal validation conflict:

```text
422 Unprocessable Entity
```

with:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "code": [
      "The coupon code has already been taken."
    ]
  }
}
```

Use existing API conventions.

---

# 25. DO NOT RETURN 500

Duplicate coupon creation is a normal business validation error.

It must not become:

```text
500
```

unless an unexpected system failure occurs.

---

# 26. FRONTEND FORM ERROR

Frontend should display the field error beside:

```text
Coupon Code
```

Example:

```text
Coupon Code
[SAVE20]

The coupon code has already been taken.
```

Do not show only:

```text
Something went wrong.
```

if backend returned a known validation error.

---

# 27. KEEP USER INPUT

When duplicate validation fails:

Do not clear:

```text
discount type
discount value
minimum spend
maximum discount
start date
end date
usage limit
shop
```

The seller/admin should only need to correct the coupon code.

---

# 28. SUBMIT BUTTON

On failed request:

```text
Save Coupon
→ Saving...
→ validation fails
→ Save Coupon
```

The button must re-enable.

Preserve F08 async error-handling patterns.

---

# 29. DUPLICATE SUBMIT

Prevent double-clicking:

```text
Create Coupon
```

from sending unnecessary duplicate requests.

This is frontend UX protection.

Database uniqueness still remains authoritative.

---

# 30. CREATE VS UPDATE FORM

If same component handles both:

```text
create coupon
edit coupon
```

make sure validation errors and route handling differ correctly.

Editing unchanged code must not produce a false duplicate error.

---

# 31. SOFT DELETE

Inspect whether coupons use soft deletes.

Important policy question:

```text
Coupon SAVE20 deleted
```

Can user later create:

```text
SAVE20
```

again?

Possible policies:

## Policy A

Deleted code remains reserved forever.

## Policy B

Deleted code can be reused.

Use existing database unique index and business requirements.

Do not guess.

---

# 32. SOFT-DELETE + UNIQUE INDEX

If soft-deleted rows remain and database has:

```text
UNIQUE(code)
```

then Laravel:

```text
withoutTrashed()
```

alone may allow validation but database still rejects the insert.

Application rule and DB constraint must agree.

This is critical.

---

# 33. ARCHIVED / DISABLED COUPONS

Differentiate:

```text
disabled coupon
```

from:

```text
deleted coupon
```

A disabled coupon still exists.

Its code should normally remain reserved.

Do not allow duplicates merely because status is inactive.

---

# 34. EXPIRED COUPONS

Expired coupon:

```text
SAVE20
```

still exists.

Do not automatically allow another:

```text
SAVE20
```

unless business policy explicitly supports code recycling.

---

# 35. COUPON LOOKUP

Checkout coupon lookup must remain deterministic.

Bad situation:

```text
two active coupons:
SAVE20
SAVE20
```

Then lookup:

```text
where code = SAVE20
first()
```

becomes ambiguous.

F12 must ensure this cannot happen under the intended scope.

---

# 36. SHOP CONTEXT AT CHECKOUT

If two shops may legally reuse the same coupon code:

checkout must resolve coupon using proper context.

Example:

```text
Shop A:
SAVE20

Shop B:
SAVE20
```

Cart includes both shops.

This can create ambiguity.

If current architecture cannot safely resolve this, marketplace-global code uniqueness may be necessary.

Do NOT change policy blindly.

Inspect F01 behavior first.

---

# 37. VALIDATION SERVICE

Avoid embedding business rules only in controller.

Preferred structure:

```text
Form Request
→ shape + uniqueness validation

CouponService
→ business operations

DB
→ final uniqueness guarantee
```

Do not duplicate the same normalization differently in multiple layers.

---

# 38. NORMALIZATION LOCATION

If codes are uppercase:

centralize normalization.

Possible:

```text
Form Request prepareForValidation()
```

or:

```text
Coupon model mutator
```

or existing shared helper.

Use one predictable location.

---

# 39. CREATE FLOW

Correct create flow:

```text
Receive request
      ↓
Normalize code
      ↓
Validate format
      ↓
Validate uniqueness
      ↓
Authorize shop/admin
      ↓
Create coupon
      ↓
Return 201
```

---

# 40. UPDATE FLOW

Correct update flow:

```text
Receive request
      ↓
Normalize code
      ↓
Validate format
      ↓
Validate uniqueness excluding current coupon
      ↓
Authorize
      ↓
Update
      ↓
Return success
```

---

# 41. DATABASE TRANSACTION

If coupon creation includes related records:

```text
coupon
coupon categories
coupon products
usage restrictions
```

preserve existing transaction boundaries.

Duplicate validation must not leave partial records.

---

# 42. COUPON USAGE DATA

Do not change:

```text
coupon usages
usage_count
per-user limit
```

in F12.

This task is only duplicate-code validation.

---

# 43. F01 REGRESSION

Critical.

Verify:

```text
wrong-shop coupon rejected
mixed-shop coupon behavior correct
eligible subtotal correct
```

---

# 44. F03 REGRESSION

Verify:

```text
discount cap
checkout total
coupon amount
```

remain correct.

---

# 45. EXISTING COUPONS

Do not rename existing coupon codes automatically.

Do not uppercase the entire database without an explicit migration/review.

If normalization changes semantics, inspect existing collisions first.

Example:

```text
SAVE20
save20
```

might already both exist.

Do not create destructive automatic migration.

---

# 46. DETECT EXISTING DUPLICATES

Before changing database constraints, inspect whether duplicates already exist under the intended normalization.

Possible safe report:

```text
Duplicate normalized coupon codes:

SAVE20
IDs: 10, 52
```

Do not automatically delete either.

Report conflict for manual review.

---

# 47. SCHEMA CHANGE

Do not add a migration unless necessary.

Possible cases requiring schema work:

```text
missing unique index

wrong uniqueness scope

case-sensitive index conflicts with case-insensitive policy
```

Explain any migration clearly.

---

# 48. INDEX NAME

If adding/changing an index, use clear conventional names.

Do not expose index names in API error messages.

---

# 49. BACKEND TEST — CREATE UNIQUE

```text
Existing:
SAVE10

Create:
SAVE20
```

Expected:

```text
201
```

---

# 50. BACKEND TEST — DUPLICATE CREATE

```text
Existing:
SAVE20

Create:
SAVE20
```

Expected:

```text
422

errors.code exists
```

No database exception exposed.

---

# 51. BACKEND TEST — UPDATE SAME CODE

```text
Coupon 10:
SAVE20
```

Update:

```text
code = SAVE20
discount = 25%
```

Expected:

```text
success
```

The current coupon must be ignored by uniqueness rule.

---

# 52. BACKEND TEST — UPDATE TO DUPLICATE

```text
Coupon A:
SAVE10

Coupon B:
SAVE20
```

Update A:

```text
SAVE20
```

Expected:

```text
422
```

---

# 53. CASE TEST

If case-insensitive policy:

```text
Existing:
SAVE20

Create:
save20
```

Expected:

```text
422
```

---

# 54. WHITESPACE TEST

If trimming is used:

```text
Existing:
SAVE20

Create:
"  SAVE20  "
```

Expected:

```text
422
```

---

# 55. SHOP-SCOPE TEST

If shop-scoped uniqueness:

```text
Shop A:
SAVE20

Shop A creates:
SAVE20
→ 422

Shop B creates:
SAVE20
→ allowed
```

If global uniqueness:

both second creates must fail.

Test the actual chosen policy.

---

# 56. PLATFORM COUPON TEST

If platform coupon exists:

verify correct uniqueness behavior with:

```text
shop_id = null
```

and shop coupons.

---

# 57. DISABLED COUPON TEST

Existing disabled:

```text
SAVE20
```

Create another:

```text
SAVE20
```

Expected according to policy, normally:

```text
422
```

---

# 58. EXPIRED COUPON TEST

Existing expired:

```text
SAVE20
```

Duplicate behavior must follow explicit code-reuse policy.

---

# 59. SOFT DELETE TEST

If soft deletes are supported:

test deleted coupon reuse according to documented policy.

Ensure Laravel validation and MySQL constraint agree.

---

# 60. CONCURRENT CREATE TEST

Where practical:

Two requests:

```text
SAVE20
SAVE20
```

at nearly the same time.

Expected:

```text
one success

one safe duplicate validation/conflict response
```

No unhandled database exception.

If actual MySQL concurrency cannot be tested:

report:

```text
NOT VERIFIED
```

---

# 61. FRONTEND TESTS

Test coupon form.

Duplicate backend response:

```text
422
errors.code
```

Expected:

```text
field error visible
other values preserved
submit re-enabled
```

---

# 62. EDIT FORM TEST

Edit existing coupon without changing code.

Expected:

```text
save succeeds
```

No false duplicate error.

---

# 63. BROWSER / E2E TEST — CRITICAL F12 REPRODUCTION

Setup:

```text
Existing coupon:
SAVE20
```

Open admin/seller coupon form.

Enter:

```text
SAVE20
```

Submit.

Verify:

```text
request returns 422

form remains open

Coupon Code field shows duplicate error

no 500 page

no database error text

no duplicate coupon created
```

---

# 64. BROWSER UPDATE TEST

Open existing:

```text
SAVE20
```

Change:

```text
maximum discount
```

without changing code.

Submit.

Expected:

```text
success
```

---

# 65. SECURITY

Do not expose:

```text
database table
constraint name
SQL query
stack trace
```

in error response.

Only safe validation information.

---

# 66. RATE LIMITING

Do not add special coupon-form throttling in F12 unless existing admin API requires it.

This is not an abuse-protection task.

---

# 67. AUDIT LOG

If coupon create/update actions are already audited:

preserve audit behavior.

Failed validation should not create a successful "coupon created" audit event.

---

# 68. NOTIFICATIONS

Do not send coupon-created notifications on failed duplicate attempts.

Only successful creation/update may trigger existing notifications.

---

# 69. API RESPONSE CONTRACT

Use existing validation structure consistently.

Example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "code": [
      "The coupon code has already been taken."
    ]
  }
}
```

Do not invent a one-off response format only for coupons.

---

# 70. TRANSLATION

If project uses frontend/backend translation keys:

add duplicate-code message accordingly.

Do not hardcode English in five different places.

---

# 71. KHMER UI

If Khmer translations exist for coupon management, ensure the validation architecture supports translated display.

Do not block F12 on full translation completion if O09 still tracks broader translation work.

---

# 72. FRONTEND ERROR NORMALIZATION

Reuse F08's normalized API error handling.

Do not introduce another separate 422 parser.

---

# 73. F01 REGRESSION

Run coupon ownership tests.

---

# 74. F02 REGRESSION

Cart recovery unaffected.

---

# 75. F03 REGRESSION

Coupon discount-cap calculations unaffected.

---

# 76. F04 REGRESSION

COD lifecycle unaffected.

---

# 77. F05 REGRESSION

Guest/account checkout unaffected.

---

# 78. F06 REGRESSION

Catalog filters unaffected.

---

# 79. F07 REGRESSION

Mobile color filters unaffected.

---

# 80. F08 REGRESSION

Async form/API errors should remain safely handled.

---

# 81. F09 REGRESSION

Rich descriptions unaffected.

---

# 82. F10 REGRESSION

Review aggregates unaffected.

---

# 83. F11 REGRESSION

Low-stock notifications unaffected.

---

# 84. BACKEND FULL TESTS

Run focused coupon tests first:

```text
coupon create
coupon update
coupon uniqueness
coupon checkout
coupon usage
shop ownership
```

Then run relevant broader backend suite.

---

# 85. FRONTEND TYPE CHECK

Run existing TypeScript type check.

No:

```text
any
@ts-ignore
```

just to bypass form-error typing.

---

# 86. PRODUCTION BUILD

Run frontend production build.

Must pass.

---

# 87. DOCKER ONLY

Use project Docker containers for:

```text
PHP
Composer
Node
tests
build
```

according to repository conventions.

Do not wipe development data.

---

# 88. DO NOT START F13

Only solve F12.

Do not begin checkout/reservation abuse protection.

That is:

```text
F13 — Checkout & Reservation Abuse Protection
```

---

# 89. DEFINITION OF DONE

Do not mark F12 complete until:

```text
[ ] Coupon uniqueness policy explicitly defined

[ ] Database unique constraint matches business policy

[ ] Create duplicate rejected at application validation

[ ] Duplicate response uses field-level 422

[ ] Update unchanged code succeeds

[ ] Update to another existing code fails cleanly

[ ] Code normalization is defined

[ ] Case-sensitivity behavior is defined

[ ] Whitespace behavior is defined

[ ] Disabled coupon behavior is defined

[ ] Expired coupon behavior is defined

[ ] Soft-deleted coupon behavior is defined if applicable

[ ] Shop-scoped/global uniqueness behavior is tested

[ ] Platform coupon behavior is tested if applicable

[ ] Concurrent duplicate insert remains DB-protected

[ ] DB duplicate fallback does not expose SQL error

[ ] Frontend field error is visible

[ ] Form values are preserved after failure

[ ] Submit button recovers after failure

[ ] No duplicate record is created

[ ] F01 coupon ownership still passes

[ ] F03 coupon calculation still passes

[ ] Backend tests pass

[ ] Frontend tests pass

[ ] Browser regression passes

[ ] Type check passes

[ ] Production build passes

[ ] F01–F11 regressions pass
```

---

# 90. FINAL REPORT

At completion report exactly:

```text
F12 — DUPLICATE COUPON VALIDATION

Status:
PASS / NEEDS WORK

Root Cause:

Uniqueness Policy:
GLOBAL / SHOP-SCOPED / OTHER

Database Constraint:

Application Validation:

Coupon Code Normalization:

Case Sensitivity:

Whitespace Policy:

Create Duplicate Behavior:

Update Same-Code Behavior:

Update Duplicate-Code Behavior:

Disabled Coupon Policy:

Expired Coupon Policy:

Soft-Deleted Coupon Policy:

Platform Coupon Behavior:

Database Race Protection:

Concurrent Create:
PASS / NOT VERIFIED / FAIL

Frontend Validation UX:

Files Changed:

Schema Changes:
None / List

Indexes Changed:
None / List

Tests Added:

Backend Tests:
Passed / Failed

Frontend Tests:
Passed / Failed

Browser Tests:
Passed / Failed

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

MySQL Concurrency:
PASS / NOT VERIFIED / FAIL

Remaining Risks:

Related Follow-Up:
F13 — Checkout & Reservation Abuse Protection
```

Also provide this before/after example:

```text
BEFORE

Existing coupon:
SAVE20

Admin creates:
SAVE20

Validation:
PASS

Database:
Duplicate entry error

Result:
500 / database exception


AFTER

Existing coupon:
SAVE20

Admin creates:
SAVE20

Laravel validation:
FAIL

Response:
422

Field:
code

Message:
The coupon code has already been taken.

Database:
No duplicate insert attempted

Frontend:
Coupon Code field displays the error
```

Stop after F12.

Do NOT automatically begin F13.

I will review the result first.