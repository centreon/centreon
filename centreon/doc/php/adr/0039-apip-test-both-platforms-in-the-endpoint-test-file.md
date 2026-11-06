# ADR-0039: Test both platforms of an endpoint in its single test file

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** APIP

## Context

Some `src/App` endpoints behave differently on Cloud and on-premise. Their cases were sometimes split across two files, a `*CloudTest` and an `*OnPremTest`, and the pair drifts: a case added on one platform is forgotten on the other.

One alternative was weighed:

- **One test file per platform.** It lost: nothing ties the two files together, so a reviewer cannot see at a glance that both platforms are covered.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**When an endpoint behaves differently on Cloud and on-premise, both cases live in the endpoint's own test file**, which forces the platform per case (as `forcePlatform()` does in `CreateHostProcessorTest`). Do **not** add a dedicated `*CloudTest` or `*OnPremTest`.

## Consequences

- An endpoint's behaviour on both platforms is read in one place.
- The rule is enforced in review.

## When to Amend

- The Cloud and on-premise platforms converge, or are served by separate code.
