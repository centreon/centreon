# ADR-0046: Use Ramda and dayjs in `www/front_src`, never lodash, moment or date-fns

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

`www/front_src` and `packages/ui` use Ramda for functional utilities (796 files import it at the time of writing) and dayjs for dates (73 files). No file imports lodash, moment or date-fns, yet nothing says so: `lodash` is pinned in `pnpm-workspace.yaml` as a transitive dependency, so an import of it would resolve, and a developer or an assistant used to another library brings it along with the first utility they need.

One alternative was weighed: **allow any utility library, and let the bundler deduplicate.** It lost: two libraries for one job means two idioms in review and two bundles for the user.

Settled in [MON-216175](https://centreon.atlassian.net/browse/MON-216175).

## Decision

Use **Ramda** for functional utilities and **dayjs** for dates. **Never import lodash, moment or date-fns.** A utility neither offers is written in the feature, or in `packages/ui` when shared.

## Consequences

- The bundle carries one utility library and one date library.
- The rule is enforced in review; a `noRestrictedImports` rule in the Biome configuration is the candidate enforcement, not configured at the time of writing.

## When to Amend

- Ramda or dayjs is abandoned upstream, or the team adopts another library for one of the two jobs.
