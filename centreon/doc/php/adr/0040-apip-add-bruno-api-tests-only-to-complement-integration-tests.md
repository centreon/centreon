# ADR-0040: Add Bruno API tests only to complement the `src/App` integration tests

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** APIP

## Context

A `src/App` endpoint is covered by unit tests for its building blocks and by integration tests, including its API test, as set by [ADR-0018](0018-apip-unit-test-every-building-block.md). Bruno API tests run against a deployed platform and are slower to run and to maintain. When they re-assert what the unit and integration tests already prove, the same rule is maintained in several places.

One alternative was weighed:

- **Cover every endpoint with Bruno tests as well.** It lost: the cases already pinned by the unit and integration tests would be maintained twice, and a failure would be reported twice.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Add a Bruno API test only for what the unit and integration tests together cannot cover**, never to re-assert what they already cover.

## Consequences

- Bruno tests stay few, and each one covers something the other tests cannot.
- The rule is enforced in review.

## When to Amend

- The integration test harness can no longer cover the HTTP round trip.
