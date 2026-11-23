# ADR-0039: Test both platforms of a block in its own test file

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** APIP

## Context

Some `src/App` blocks behave differently on Cloud and on-premise. Their tests already cover both platforms in one file, forcing the platform per case (`forcePlatform()`, as in `ListHostsProviderTest`). Nothing states it, and splitting the cases into a `*CloudTest` and an `*OnPremTest` would let the pair drift: a case added on one platform would be forgotten on the other. The host template creation endpoint left its Cloud branch untested in review ([#12536](https://github.com/centreon/centreon/pull/12536#discussion_r4490754419)).

One alternative was weighed:

- **One test file per platform.** It lost: nothing ties the two files together, so a reviewer cannot see at a glance that both platforms are covered.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**When a block behaves differently on Cloud and on-premise, both cases live in that block's own test file** (the handler's, the validator's, or the endpoint's API test when the branch is in the wiring), which forces the platform per case. Do **not** add a dedicated `*CloudTest` or `*OnPremTest`.

## Consequences

- A block's behaviour on both platforms is read in one place, at the level [ADR-0018](0018-apip-unit-test-every-building-block.md) pins it.
- The rule is enforced in review.

## When to Amend

- The Cloud and on-premise platforms converge, or are served by separate code.
