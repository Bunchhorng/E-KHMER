# 10. Change Request Log

> Tracks changes to requirements/scope after baseline. Newest on top. Every CR gets an impact analysis before approval.

| CR-# | Date | Raised by | Change description | Reason | Impact (scope/schedule/cost/risk) | Files/areas affected | Status | Approved by | Date closed |
|------|------|-----------|--------------------|--------|-----------------------------------|----------------------|--------|-------------|-------------|
| — | — | — | — | — | — | — | New / In analysis / Approved / Rejected / Implemented | — | — |

## Change request template

```markdown
## CR-#### — <title>
- Raised by / date:
- Area: backend / frontend / db / infra / docs
- Current requirement:
- Requested change:
- Reason / business need:
- Impact analysis (scope, schedule, effort estimate, data migration, API contract, affected modules):
- Options considered:
- Decision: Approved / Rejected / Deferred  (ref: 09-Decision-Log.md #n)
- Implementation owner(s):
- Verification: (tests, build, docs updated)
```

## Rules

- No requirement change enters `develop` without a CR row.
- CRs affecting DB schema or API contract require the module owner + M1 sign-off.
- Closed ≠ implemented: move to *Implemented* only after merge + tests green.