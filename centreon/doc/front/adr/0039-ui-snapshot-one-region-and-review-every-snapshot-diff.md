# ADR-0039: Snapshot one region at most in `packages/ui` tests, and update a snapshot only after reviewing its diff

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

The migration of the package's component tests in [#12148](https://github.com/centreon/centreon/pull/12148) deleted 160 [visual snapshot](../glossary.md#visual-snapshot) baselines and replaced each by explicit assertions on what the baseline showed. The chart specs that stay on Cypress keep theirs, updated with `pnpm cypress:run:updateSnapshots --spec <path>` from `packages/ui`. A whole-page HTML snapshot in a [browser test](../glossary.md#browser-test) breaks on every change while asserting nothing readable, and a baseline regenerated without reading its diff records a regression as the new truth, for every consumer of the component.

Two alternatives were weighed:

- **Whole-component HTML snapshots as the main assertion.** It lost: the diff is unreadable, so it is accepted without being read.
- **No snapshot at all.** It lost: a small region with a stable structure is cheaper to snapshot than to assert attribute by attribute.

Settled in [#12148](https://github.com/centreon/centreon/pull/12148).

## Decision

In a browser test, **assert what matters explicitly**; snapshot **the HTML of one region at most**. In a chart spec, **update a snapshot only after reviewing its diff**, for that one spec. **Never regenerate baselines wholesale.**

## Consequences

- A snapshot diff is read like a code diff, in the pull request.
- The rule is enforced in review.

## When to Amend

- The chart specs are migrated; the legacy part of the record then goes.
- A visual testing tool with reviewable diffs is adopted for browser tests.
