---
status: archived
archived_reason: One-off AI delegation prompt, superseded by root AGENTS.md and per-app .agents.md.
superseded_by: AGENTS.md
archived_on: 2026-05-14
---
# Workstream Delegation Prompt Template

## Current Status
**WS2:** Backend Print Idempotency - ✅ MERGED to staging
**WS4:** PWA Submission Idempotency - ⚠️ Ready to merge to staging  
**WS5:** Performance Improvements - ⚠️ Ready to merge to staging
**WS3:** Print Bridge Event-Only Cleanup - ❌ Blocked (needs WS2 validation first)

## Immediate Tasks Required

### 1. Merge WS4 (PWA) to Staging
**Repository:** tablet-ordering-pwa
**Branch:** feature/ws4-submission-idempotency
**Commands:**
```bash
cd c:\laragon\www\tablet-ordering-pwa
git checkout staging
git pull origin staging
git merge feature/ws4-submission-idempotency --no-edit
git push origin staging
```

### 2. Merge WS5 (PWA Bundle) to Staging  
**Repository:** tablet-ordering-pwa
**Branch:** perf/ws5-pwa-bundle
**Commands:**
```bash
cd c:\laragon\www\tablet-ordering-pwa
git checkout staging
git merge perf/ws5-pwa-bundle --no-edit
git push origin staging
```

### 3. Merge WS5 (Bridge) to Staging
**Repository:** woosoo-print-bridge
**Branch:** perf/ws5-bridge-logging-metrics
**Commands:**
```bash
cd c:\laragon\www\woosoo-print-bridge
git checkout staging
git pull origin staging
git merge perf/ws5-bridge-logging-metrics --no-edit
git push origin staging
```

### 4. Docker Validation (CRITICAL)
**Repository:** woosoo-nexus
**Commands:**
```bash
cd c:\laragon\www\woosoo-nexus
docker compose exec app php artisan test --filter PrintTicketServiceTest
docker compose exec app php artisan test --filter DeviceOrderIntentContractTest
docker compose exec app php artisan migrate:fresh --env=testing
docker compose exec app php artisan migrate:rollback --step=3
docker compose exec app php artisan migrate:status
```

### 5. WS3 Implementation (After WS2 Validation)
**Repository:** woosoo-print-bridge
**Branch:** feature/ws3-event-only-print-bridge
**Scope:** Remove polling, harden idempotency, reduce logging
**Dependencies:** Requires WS2 Docker validation to complete first

## Risk Assessment
- **WS2:** Merged without Docker validation - requires immediate testing
- **WS4/WS5:** Low risk, isolated changes
- **WS3:** High dependency on WS2, must wait for validation

## Expected Timeline
- **Merging:** 15 minutes
- **Docker Validation:** 20 minutes  
- **WS3 Implementation:** 30 minutes (after WS2 validation)

## Quality Gates
1. All merges complete without conflicts
2. Docker tests pass for WS2
3. No PWA/bridge files affected by WS2 changes
4. WS3 only starts after WS2 validation green

## Instructions for Agent
1. Complete the merge operations in order
2. Run Docker validation immediately after WS2 merge
3. Report any merge conflicts or test failures
4. Do not start WS3 until WS2 validation is complete
5. Update status after each completed step

## Contact Information
If issues arise during Docker validation or merging, escalate immediately as this affects the shipping deadline.
