# E-KHMER Improvement #10 — Review Aggregate Maintenance

Act as a senior **Laravel + Vue.js + MySQL Marketplace Engineer, Review/Rating Domain Engineer, Data-Consistency Engineer, Transaction Engineer, Security Engineer, and QA Engineer**.

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
```

Do NOT regress those fixes.

Now work ONLY on:

# F10 — Review Aggregate Maintenance

---

# 1. CURRENT DEFECT

The current audit reproduced this problem:

```text
Product has:

1 approved review
Rating = 5 stars
```

Stored product aggregate:

```text
rating = 5
review_count = 1
```

Admin then rejects that review.

Expected:

```text
rating = 0 / null according to current model
review_count = 0
```

Current broken behavior:

```text
review rejected

BUT product still stores:

rating = 5
review_count = 1
```

This creates stale public product ratings.

The audit also identified that direct admin review deletion can bypass aggregate recalculation.

---

# 2. PRIMARY GOAL

Create one reliable review lifecycle:

```text
Review Created
      ↓
Pending / Approved / Rejected
      ↓
Review State Changes
      ↓
Recalculate Product Aggregate
      ↓
Persist:

average rating
review count
```

Every supported review action must leave product aggregates correct.

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing anything.

Focus especially on:

```text
backend/app/Services/ReviewService.php
backend/app/Http/Controllers/Api/Admin/AdminReviewController.php
```

Also inspect:

```text
backend/app/Http/Controllers/Api/ReviewController.php
backend/app/Models/Review.php
backend/app/Models/Product.php

review migrations
product migrations
review resources
review request validation
review policies

frontend/src/views/account/ReviewsView.vue
frontend admin review management screens
frontend ProductDetailView.vue
frontend product cards

catalog rating filters
catalog services
existing review tests
existing browser tests
```

Search for:

```text
rating
average_rating
review_count
reviews_count
recalculate
aggregate
approved
rejected
pending
delete
destroy
approve
moderate
```

Do not assume exact field names.

---

# 4. IDENTIFY ALL REVIEW STATE-CHANGE PATHS

Find every way review data can change.

At minimum inspect:

```text
Customer creates review

Customer edits review

Customer deletes review

Admin approves review

Admin rejects review

Admin deletes review

Review status changed

Review restored if soft deletes exist
```

Also inspect any:

```text
seeders
console commands
admin bulk actions
factory helpers
```

that may modify review state.

The goal is to prevent aggregate maintenance from existing in only one path.

---

# 5. DEFINE AGGREGATE SOURCE OF TRUTH

Product aggregate should be based only on reviews that are valid for public rating.

Most likely:

```text
status = approved
```

But inspect the actual current business rules.

Recommended concept:

```text
Approved reviews only
```

Exclude:

```text
pending
rejected
deleted
soft-deleted
```

unless current requirements say otherwise.

---

# 6. DEFINE THE FORMULA

Example:

Approved ratings:

```text
5
4
3
```

Count:

```text
3
```

Average:

```text
(5 + 4 + 3) / 3
= 4.0
```

Persist:

```text
review_count = 3
average_rating = 4.0
```

Adapt to actual product fields.

---

# 7. ZERO-REVIEW BEHAVIOR

Define explicitly what happens when no approved reviews remain.

Possible current schema behavior:

```text
average_rating = 0
review_count = 0
```

or:

```text
average_rating = null
review_count = 0
```

Use existing product conventions.

Do not introduce inconsistency between:

```text
API
database
product cards
rating filters
```

---

# 8. CENTRALIZE RECALCULATION

Do NOT spread logic across controllers.

Bad:

```text
AdminReviewController
→ manually update rating

ReviewController
→ different calculation

ReviewService
→ another calculation
```

Preferred:

```text
ReviewService
    ↓
recalculateProductRating(Product $product)
```

or an equivalent domain service.

Every relevant state-changing operation must call the same logic.

---

# 9. CONTROLLER SHOULD NOT OWN BUSINESS LOGIC

Admin controller should not directly do:

```php
$review->status = 'rejected';
$review->save();

$product->rating = ...
```

Prefer:

```text
Controller
→ ReviewService
→ transaction
→ state change
→ aggregate recalculation
```

Keep domain behavior centralized.

---

# 10. APPROVE REVIEW

Scenario:

```text
Product currently:

Approved reviews:
4, 5

Average:
4.5

Count:
2
```

Pending review:

```text
3 stars
```

Admin approves.

Expected:

```text
Average:
4.0

Count:
3
```

Verify exact rounding policy.

---

# 11. REJECT REVIEW

Scenario:

```text
Approved reviews:
5, 4, 3
```

Reject:

```text
5-star review
```

Expected:

```text
Remaining:
4, 3

Average:
3.5

Count:
2
```

---

# 12. REJECT THE ONLY APPROVED REVIEW

Critical F10 reproduction:

```text
Approved:
5
```

Reject it.

Expected:

```text
Count:
0

Average:
0 / null
```

according to project convention.

Not:

```text
5
1
```

---

# 13. ADMIN DELETE

Critical second path.

Scenario:

```text
Approved reviews:
5
4
```

Admin deletes 5-star review.

Expected:

```text
Count:
1

Average:
4
```

Deletion must not bypass aggregate maintenance.

---

# 14. CUSTOMER DELETE

If customers can delete their own reviews:

```text
customer deletes approved review
```

product aggregate must recalculate.

If customers cannot delete reviews, preserve existing rules.

---

# 15. CUSTOMER EDIT

If customer edits:

```text
rating 5
→ rating 3
```

and the review remains approved according to existing rules:

recalculate.

If edits reset review to pending:

```text
approved
→ edited
→ pending
```

then remove it from public aggregate until approved again.

Use actual business rules.

---

# 16. PENDING REVIEWS

Pending review must not affect public aggregate if moderation is required.

Example:

```text
Approved:
4

Pending:
5
```

Public aggregate:

```text
4
Count = 1
```

not:

```text
4.5
Count = 2
```

---

# 17. REJECTED REVIEWS

Rejected reviews must not contribute to:

```text
average rating
review count
catalog rating filter
public rating display
```

unless existing business requirements explicitly say otherwise.

---

# 18. SOFT DELETES

Inspect whether reviews use:

```text
SoftDeletes
```

If yes:

```text
deleted_at != null
```

must normally exclude review from aggregate.

Test:

```text
approved review
→ soft delete
→ aggregate recalculated
```

If restore is supported:

```text
restore
→ recalculate again
```

only if review state still qualifies.

---

# 19. PRODUCT SOFT DELETE

Do not spend F10 redesigning deleted-product behavior.

If product is soft deleted:

review aggregate consistency should not create errors.

---

# 20. VERIFIED PURCHASE RULE

Current project has verified purchase reviews.

Do NOT weaken:

```text
delivered purchase required
ownership
verified purchase
```

F10 only fixes aggregate consistency.

---

# 21. REVIEW DUPLICATES

Preserve existing rule about:

```text
one review per purchased item
one review per product/order
```

Do not modify duplicate-review policy unless required for correctness.

---

# 22. ROUNDING POLICY

Define one rating precision.

Examples:

```text
4.333333
```

could store/display as:

```text
4.33
```

or:

```text
4.3
```

Use existing product/API convention.

Do not let:

```text
database = 4.33
API = 4.3
filter = rounded 4.5
```

produce inconsistent behavior.

---

# 23. F06 INTERACTION

F06 rating filters depend on product rating data.

After F10:

```text
rating filter
```

must use correct updated aggregate.

Example:

```text
Product rating before moderation:
4.8
```

After rejecting reviews:

```text
actual rating:
4.2
```

Filter:

```text
4.5+
```

must no longer include the product.

This is an important regression test.

---

# 24. PRODUCT DETAIL PAGE

After moderation:

```text
Product Detail
```

should immediately/after refresh show:

```text
correct average
correct review count
correct approved reviews
```

No stale summary.

---

# 25. PRODUCT CARD

Product cards should display the updated aggregate.

Example:

Before:

```text
5.0 ★ (1)
```

After rejecting only review:

```text
No reviews
```

or current zero-review UI.

---

# 26. ADMIN REVIEW LIST

Admin review status change should update:

```text
status badge
```

and affected product rating where visible.

Do not require manual DB correction.

---

# 27. TRANSACTION SAFETY

Review state change and aggregate recalculation should be atomic.

Concept:

```text
BEGIN

lock/change review

recalculate approved reviews

update product aggregate

COMMIT
```

If something fails:

```text
ROLLBACK
```

Avoid:

```text
review rejected
but product aggregate update failed
```

---

# 28. CONCURRENT MODERATION

Scenario:

```text
Admin A approves review X

Admin B rejects review Y

same product
same time
```

Final product aggregate must represent final database state.

Do not rely on fragile incremental math like:

```text
count = count - 1
average = old formula
```

without concurrency safety.

---

# 29. PREFER RECOMPUTATION OVER FRAGILE INCREMENTAL MATH

For correctness, prefer:

```text
SELECT COUNT(...)
SELECT AVG(...)
WHERE product_id = ?
AND status = approved
```

or an efficient equivalent.

This is often safer than:

```text
old average
+
manual arithmetic
```

especially with:

```text
approval
rejection
editing
deletion
```

Optimize later only if necessary.

---

# 30. DATABASE LOCKING

If concurrency matters:

inspect whether existing service uses:

```text
transaction
lockForUpdate
```

Use only where appropriate.

Do not over-lock unrelated products/reviews.

---

# 31. BULK MODERATION

Search for:

```text
bulk approve
bulk reject
bulk delete
```

If present:

aggregate affected products correctly.

Avoid recalculating same product hundreds of times unnecessarily.

Potential safe pattern:

```text
collect affected product IDs
→ complete mutations
→ recalculate each product once
```

Only implement if bulk behavior exists.

---

# 32. DATABASE EVENTS / OBSERVERS

Inspect whether project already uses:

```text
Model Observer
Events
Listeners
```

Do not add an observer if service-level consistency already fits architecture.

But if reviews can be modified from many code paths, an observer/domain event may reduce missed recalculation paths.

Choose one architecture deliberately.

---

# 33. AVOID DOUBLE RECALCULATION

If adding:

```text
ReviewObserver
```

do not also call recalculation manually everywhere unless intentionally idempotent.

Avoid unnecessary duplicate queries.

---

# 34. PUBLIC REVIEW QUERY

Confirm public product reviews query only returns:

```text
approved
```

reviews.

F10 should verify public review list and aggregate use the same qualification rules.

---

# 35. REVIEW COUNT DEFINITION

Clarify:

```text
review_count
```

means:

```text
number of approved public reviews
```

not:

```text
all submitted reviews
```

unless current project defines otherwise.

---

# 36. ADMIN COUNT VS PUBLIC COUNT

Admin may still need:

```text
pending count
rejected count
all reviews
```

but public product aggregate should remain approved-only.

Do not confuse admin moderation metrics with public rating metrics.

---

# 37. REVIEW IMAGES

Review images should not affect aggregate calculation.

Reject/delete review should follow existing review-image cleanup/visibility rules.

Do not redesign media storage in F10.

---

# 38. CACHE INVALIDATION

Inspect whether product/catalog responses use caching.

If rating aggregate changes:

invalidate/update relevant cache.

Potential:

```text
product detail cache
catalog cache
shop storefront cache
```

Do not leave stale rating visible because DB is correct but cache is old.

---

# 39. SEARCH / SORT BY RATING

If catalog supports:

```text
sort by rating
```

or popularity ranking:

updated aggregates must affect those results.

Do not redesign ranking logic.

Just verify stale aggregates are not retained.

---

# 40. API RESOURCE

Confirm product resource returns rating fields from the correct source.

Example:

```json
{
  "rating": 4.5,
  "review_count": 12
}
```

Do not calculate one value in resource while storing another in product.

---

# 41. DATABASE FIELD TYPES

Inspect:

```text
average_rating / rating
review_count
```

Possible:

```text
DECIMAL
FLOAT
integer
```

Do not change schema unless required.

Avoid floating-point precision mistakes if current schema is DECIMAL.

---

# 42. HISTORICAL STALE DATA

Important:

Existing database may already contain stale aggregates from the old bug.

Fixing future mutations is not enough.

Determine whether a safe repair mechanism is needed.

Possible:

```text
Artisan command:
reviews:recalculate-ratings
```

or equivalent.

Do NOT automatically modify production data.

Create safe repair tooling if appropriate.

---

# 43. REPAIR COMMAND

If added, concept:

```text
php artisan reviews:recalculate-ratings
```

Should:

```text
scan products
calculate approved review count
calculate approved average
update only incorrect aggregates
report summary
```

Optional flags:

```text
--product=123
--dry-run
```

if useful.

Do not require this if existing project already has repair logic.

---

# 44. DRY RUN

If implementing repair tooling, strongly prefer:

```text
--dry-run
```

Example:

```text
Products scanned: 500

Incorrect aggregates: 12

No database changes performed.
```

Useful before production correction.

---

# 45. DO NOT RESEED

Do not solve stale data by:

```text
migrate:fresh
db:wipe
reseed everything
```

This is unacceptable for production-style data.

---

# 46. LOGGING

Unexpected aggregate-recalculation failure should be logged.

Do not log sensitive customer review metadata unnecessarily.

Useful context:

```text
review_id
product_id
action
actor_id
```

---

# 47. ERROR HANDLING

If review state change fails:

return safe error.

Do not leave UI pretending moderation succeeded.

Example:

```text
Could not update this review. Please try again.
```

---

# 48. AUTHORIZATION

Preserve:

```text
Customer
→ own allowed review actions

Seller
→ view according to current rules

Super Admin
→ moderation
```

Do not allow seller to manipulate customer rating content/status beyond current policy.

---

# 49. SELLER RESTRICTION

Current marketplace design says seller should not secretly edit:

```text
customer rating
customer comment
```

Preserve this.

F10 should only ensure aggregates follow authorized moderation actions.

---

# 50. BACKEND UNIT TESTS

Add tests for recalculation service.

## Test 1

```text
No approved reviews
→ count 0
→ rating 0/null
```

## Test 2

```text
Approved:
5

→ count 1
→ rating 5
```

## Test 3

```text
Approved:
5, 4

→ count 2
→ average 4.5
```

## Test 4

```text
Approved:
5
Pending:
1

→ count 1
→ average 5
```

## Test 5

```text
Approved:
5
Rejected:
1

→ count 1
→ average 5
```

---

# 51. APPROVAL FEATURE TEST

Scenario:

```text
Product:
rating 4
count 1

Pending review:
5
```

Approve.

Expected:

```text
rating 4.5
count 2
```

---

# 52. REJECTION FEATURE TEST

Critical:

```text
Only approved review:
5
```

Reject.

Expected:

```text
count 0
rating 0/null
```

This reproduces the audit defect.

---

# 53. ADMIN DELETE FEATURE TEST

Scenario:

```text
Approved:
5
4
```

Delete 5.

Expected:

```text
rating 4
count 1
```

---

# 54. CUSTOMER DELETE TEST

If customer deletion exists:

approved review deletion must recalculate.

---

# 55. CUSTOMER EDIT TEST

If edit exists:

```text
5 → 2
```

verify aggregate follows current moderation policy.

---

# 56. STATUS TRANSITION TEST

Test:

```text
pending → approved
approved → rejected
rejected → approved
```

if all transitions are supported.

Each must result in correct aggregate.

---

# 57. SOFT DELETE TEST

If soft deletes:

```text
approved
→ soft delete
```

must remove from aggregate.

Restore behavior tested if supported.

---

# 58. MULTIPLE PRODUCTS TEST

Review changes for:

```text
Product A
```

must not alter:

```text
Product B
```

aggregate.

---

# 59. CONCURRENCY TEST

If practical in current test setup:

simulate simultaneous moderation.

Final aggregate must equal:

```text
AVG(final approved review rows)
COUNT(final approved review rows)
```

If MySQL concurrency cannot be tested now, report:

```text
NOT VERIFIED
```

Do not claim concurrency safety from SQLite alone.

---

# 60. CACHE TEST

If product/catalog cache exists:

change review status.

Then verify:

```text
next product/catalog response
```

shows new aggregate.

---

# 61. FRONTEND PRODUCT DETAIL TEST

Fixture:

```text
Before:
5.0 ★
1 review

Admin rejects review
```

After refresh/API update:

```text
No reviews
```

or current zero-review state.

---

# 62. FRONTEND ADMIN TEST

Admin:

```text
click Reject
```

Verify:

```text
review becomes rejected
```

and UI does not show success if backend fails.

---

# 63. F06 REGRESSION TEST

This is critical.

Fixture:

```text
Product rating before:
4.8

After moderation:
4.2
```

Filter:

```text
4.5 & up
```

Expected:

```text
product excluded
```

This confirms review aggregation and rating filtering remain consistent.

---

# 64. PRODUCT CARD REGRESSION

Product cards should show updated:

```text
average rating
review count
```

after data refresh.

---

# 65. BROWSER / E2E TEST — CRITICAL F10 REPRODUCTION

Use isolated fixture:

```text
Product A

Approved review:
5 stars
```

Verify initially:

```text
rating = 5
count = 1
```

Admin rejects review.

Then customer/product view:

```text
rating = zero/no reviews
count = 0
```

No stale 5-star display.

---

# 66. BROWSER ADMIN DELETE TEST

Fixture:

```text
5-star approved
4-star approved
```

Admin deletes 5-star review.

Expected customer rating:

```text
4.0
1 review
```

---

# 67. REPAIR EXISTING DATA TEST

If repair command exists:

Create deliberately incorrect aggregate:

```text
stored:
rating = 5
count = 10

actual approved reviews:
4, 2
```

Dry-run identifies mismatch.

Execution repairs to:

```text
rating = 3
count = 2
```

---

# 68. PERFORMANCE

Do not perform:

```text
recalculate every product
```

whenever one review changes.

Recalculate only affected product(s).

---

# 69. LARGE REVIEW COUNT

Test service with many reviews if practical.

Aggregation should use database functions rather than loading thousands of Review models into PHP just to average them.

Prefer:

```text
AVG()
COUNT()
```

at database level.

---

# 70. F01 REGRESSION

Coupon isolation unaffected.

---

# 71. F02 REGRESSION

Cart recovery unaffected.

---

# 72. F03 REGRESSION

Checkout totals unaffected.

---

# 73. F04 REGRESSION

COD lifecycle unaffected.

---

# 74. F05 REGRESSION

Guest/account checkout behavior unaffected.

---

# 75. F06 REGRESSION

Critical:

```text
rating filters still work with recalculated ratings
```

---

# 76. F07 REGRESSION

Mobile color filters unaffected.

---

# 77. F08 REGRESSION

Async cart actions unaffected.

---

# 78. F09 REGRESSION

Safe rich product descriptions unaffected.

---

# 79. BACKEND FULL TESTS

Run focused review tests first.

Then run broader relevant backend suite.

Do not mark F10 complete just because new tests pass while existing review/catalog tests fail.

---

# 80. FRONTEND TYPE CHECK

Run existing TypeScript type check.

Do not add:

```text
any
@ts-ignore
```

to bypass review API typing issues.

---

# 81. PRODUCTION BUILD

Run frontend production build.

Must pass.

---

# 82. DOCKER ONLY

Use project Docker containers for:

```text
PHP
Composer
Node
tests
build
```

according to project conventions.

Do not wipe development data.

Use isolated test data.

---

# 83. DO NOT START F11

Only solve F10.

Do not begin low-stock notification lifecycle work.

That is:

```text
F11 — Low-Stock Notification Lifecycle
```

---

# 84. DEFINITION OF DONE

Do not mark F10 complete until:

```text
[ ] Product rating uses approved reviews only

[ ] Product review_count uses approved reviews only

[ ] Approving review recalculates aggregate

[ ] Rejecting review recalculates aggregate

[ ] Rejecting only approved review resets aggregate

[ ] Admin deleting review recalculates aggregate

[ ] Customer delete recalculates where supported

[ ] Customer edit recalculates according to moderation policy

[ ] Soft delete recalculates where applicable

[ ] Review restore recalculates where applicable

[ ] Public review list matches aggregate qualification rules

[ ] Product detail displays updated rating

[ ] Product cards display updated rating

[ ] F06 rating filter uses updated aggregate

[ ] Cache invalidation works if applicable

[ ] Review actions are transactional

[ ] Concurrent updates are handled or clearly reported as not verified

[ ] Existing stale aggregate data has a repair plan

[ ] Repair tooling is safe if added

[ ] Backend tests pass

[ ] Frontend tests pass

[ ] Browser regression passes

[ ] Type check passes

[ ] Production build passes

[ ] F01–F09 regressions pass
```

---

# 85. FINAL REPORT

At completion report exactly:

```text
F10 — REVIEW AGGREGATE MAINTENANCE

Status:
PASS / NEEDS WORK

Root Cause:

Old Behavior:

New Aggregate Rule:

Public Aggregate Includes:
List

Public Aggregate Excludes:
List

Average Rating Formula:

Zero-Review Behavior:

Rounding Policy:

Recalculation Location:

Transaction Strategy:

Concurrency Strategy:

Cache Invalidation:

Existing Stale Data:
YES / NO / UNKNOWN

Repair Strategy:

Repair Command:
None / Command

Files Changed:

Schema Changes:
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

MySQL Concurrency:
PASS / NOT VERIFIED / FAIL

Remaining Risks:

Related Follow-Up:
F11 — Low-Stock Notification Lifecycle
```

Also provide this before/after example:

```text
BEFORE

Product:
5.0 ★
1 review

Admin rejects the only review

Database still:
rating = 5
review_count = 1

Customer still sees:
5.0 ★


AFTER

Product:
5.0 ★
1 approved review

Admin rejects that review

Aggregate recalculation:

approved reviews = 0
review_count = 0
rating = 0/null

Customer sees:
No reviews
```

Stop after F10.

Do NOT automatically begin F11.

I will review the result first.