# Agent Review Prompt: Requirements & Implementation Plans

**Task:** Review two critical architecture documents for the Woosoo Tablet Ordering System.

**Documents to Review:**
1. `docs/IMPLEMENTATION_PLAN_SERVER_AUTHORITATIVE_ORDER_TRANSACTION_2026-05-09.md`
2. `docs/LONG_TERM_REQUIREMENTS_2026-05-09.md`

**Repos Context:**
- `tech-artificer/woosoo-nexus` (Laravel backend)
- `tech-artificer/tablet-ordering-pwa` (Nuxt tablet client)

---

## Review Objectives

### 1. Completeness Check

**IMPLEMENTATION_PLAN Review:**
- [ ] All database migrations have complete schema definitions
- [ ] All service classes have defined public methods
- [ ] All API endpoints have request/response examples
- [ ] All TypeScript types are fully specified
- [ ] All test scenarios are actionable (can be written as actual tests)
- [ ] No TODOs or placeholders that block implementation

**LONG_TERM_REQUIREMENTS Review:**
- [ ] All 5 phases have clear acceptance criteria
- [ ] Phase 0 (Nexus CI fix) is sufficiently detailed to start immediately
- [ ] Every P0/P1/P2 item has an owner repo
- [ ] No conflicting requirements between phases
- [ ] All "Forbidden Mixing" rules are enforceable

### 2. Consistency Check

Cross-reference between documents:
- [ ] Order payload schema in IMPLEMENTATION_PLAN matches LONG_TERM_REQUIREMENTS Section 7.1
- [ ] Idempotency approach is consistent across both documents
- [ ] Terminology (`security_code` vs `token`) is consistent
- [ ] Channel naming conventions match between broadcast sections
- [ ] Environment/deployment rules don't contradict

### 3. Technical Feasibility

**Database Schema:**
- [ ] `order_quotes` table can handle quote expiration efficiently (index on `expires_at`)
- [ ] `order_transactions` status enum covers all states without gaps
- [ ] `device_order_items` schema migration is backward-compatible
- [ ] JSON columns have reasonable size limits

**Service Architecture:**
- [ ] `OrderTransactionService` methods have clear inputs/outputs
- [ ] `OrderPlanner` can build deterministic plans (required for idempotency)
- [ ] `PosOrderWriter` has strategy for capturing inserted POS row IDs
- [ ] Service interactions don't create circular dependencies

**API Design:**
- [ ] Quote endpoint response size is reasonable for mobile
- [ ] Commit endpoint failure modes are distinguishable (retryable vs fatal)
- [ ] Error responses follow consistent format

**Frontend State:**
- [ ] `stores/Flow.ts` phase machine has no unreachable states
- [ ] Quote staleness detection covers all mutation paths
- [ ] Idempotency key storage doesn't leak across devices

### 4. Security & Risk Review

**Critical Security Items:**
- [ ] Private channel authorization rules prevent cross-device snooping
- [ ] Idempotency keys can't be guessed/brute-forced
- [ ] Quote expiration prevents price-lock abuse
- [ ] No secrets in event envelopes
- [ ] Offline mutation disabled (MVP) prevents replay attacks

**Risk Mitigation:**
- [ ] Recovery scenario (POS success + local failure) is fully specified
- [ ] Duplicate detection handles network retries correctly
- [ ] Stale event rejection prevents session hijacking
- [ ] Settings PIN properly scoped as "convenience only"

### 5. Test Coverage Validation

**Backend Tests Required:**
For each test file mentioned, verify scenarios cover:
- [ ] Happy path
- [ ] Validation failures (each rule independently)
- [ ] Idempotency scenarios (same key, different payload, replay)
- [ ] Recovery scenarios (POS success + local failure)
- [ ] Race conditions (concurrent commits with same quote)

**Frontend Tests Required:**
- [ ] Intent builder edge cases (empty items, duplicate menu_ids, max quantities)
- [ ] Quote staleness detection (all cart mutation paths)
- [ ] Idempotency key persistence (survives app restart, cleared on success)
- [ ] Endpoint constants validation (no raw strings)

### 6. Migration & Rollback

- [ ] Feature flag `useServerAuthoritativeOrders` is defined
- [ ] Old endpoint compatibility is time-boxed (deprecation schedule)
- [ ] Database migrations are reversible
- [ ] Rollback plan for Phase 0 failure

---

## Review Output Format

Provide findings in this structure:

```markdown
## Executive Summary
- [ ] Documents are ready for implementation
- [ ] Documents need revision before implementation
- [ ] Critical blockers identified

## Critical Issues (Must Fix)
| # | Issue | Location | Recommended Fix |
|---|-------|----------|-----------------|
| 1 | ... | ... | ... |

## Warnings (Should Fix)
| # | Issue | Location | Recommended Fix |
|---|-------|----------|-----------------|
| 1 | ... | ... | ... |

## Gaps / Missing Items
| # | Gap | Impact | Recommendation |
|---|-----|--------|----------------|
| 1 | ... | ... | ... |

## Questions for Clarification
1. ...

## Positive Findings
- ...

## Implementation Readiness
| Phase | Ready? | Blockers |
|-------|--------|----------|
| 0 | [ ] | ... |
| 1 | [ ] | ... |
| 2 | [ ] | ... |
```

---

## Key Areas to Scrutinize

### 1. POS Row ID Capture
The plan mentions capturing `ordered_menus.id` after insertion. Verify:
- Does the stored procedure return the ID?
- Is there a fallback query if not?
- How is parent linkage handled if Krypton doesn't return IDs?

### 2. Idempotency Key Scope
The plan uses `crypto.randomUUID()`. Verify:
- Is this available in all target browsers?
- Should there be a fallback?
- Is the key scoped correctly (device + quote vs global)?

### 3. Quote Expiration Edge Cases
- What happens if customer is mid-review when quote expires?
- Is there a grace period?
- Does expiration auto-refresh or require manual action?

### 4. Concurrent Quote Creation
- Can same device create multiple active quotes?
- What happens to old quote if new one created?
- Is there a cleanup mechanism for abandoned quotes?

### 5. Recovery State Machine
The `order_transactions` table has status `recovery_required`. Verify:
- Who triggers recovery? (scheduled job? manual admin?)
- How is recovery success/failure tracked?
- What prevents infinite recovery loops?

### 6. Menu Contract Cache
The `GET /api/device/menu-contract` endpoint suggests server controls rules. Verify:
- How often is this fetched?
- Is there caching? (browser? service worker?)
- How are stale contracts invalidated?

---

## Cross-Cutting Concerns

### Performance
- [ ] Quote endpoint < 200ms (database + calculation)
- [ ] Commit endpoint < 500ms (POS write + local mirror)
- [ ] No N+1 queries in OrderPlanner

### Scalability
- [ ] `order_quotes` table has cleanup strategy (abandoned quotes)
- [ ] `order_transactions` table has retention policy
- [ ] Idempotency keys have expiration

### Observability
- [ ] Key metrics defined (quote-to-commit conversion, recovery rate)
- [ ] Alerting thresholds specified
- [ ] Debugging logs specified for transaction tracing

---

## Review Checklist Summary

**Before marking review complete:**

- [ ] Read both documents fully
- [ ] Cross-referenced related sections
- [ ] Identified at least 3 potential issues (even if minor)
- [ ] Verified test coverage is comprehensive
- [ ] Checked for conflicts with existing codebase
- [ ] Confirmed Phase 0 can start immediately
- [ ] Provided actionable recommendations

---

**Review Output Location:**
Create findings as `docs/REVIEW_FINDINGS_2026-05-09.md`

**Time Estimate:** 30-45 minutes for thorough review

**Success Criteria:**
- Documents are either approved for implementation OR
- Critical blockers are clearly identified with fix recommendations
