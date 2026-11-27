# ADR-0047: Use Ramda and dayjs in `packages/ui`, never lodash, moment or date-fns

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`packages/ui` uses Ramda for functional utilities and dayjs for dates, like the applications that consume it. A second utility or date library imported by the package would be shipped to every consumer, Centreon Web and the module frontends alike, next to the one they already use.

One alternative was weighed: **allow any utility library in the package.** It lost: the package's dependencies are every consumer's dependencies.

Settled in [#12827](https://github.com/centreon/centreon/pull/12827).

## Decision

Use **Ramda** for functional utilities and **dayjs** for dates. **Never import lodash, moment or date-fns.**

## Consequences

- A new dependency of the package is a dependency of every consumer and is weighed as such in review.
- The rule is enforced in review; a `noRestrictedImports` rule in the Biome configuration is the candidate enforcement, not configured at the time of writing.

## When to Amend

- Ramda or dayjs is abandoned upstream, or the team adopts another library for one of the two jobs.
