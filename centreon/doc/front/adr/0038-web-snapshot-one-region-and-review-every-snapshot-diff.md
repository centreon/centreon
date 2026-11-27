# ADR-0038: Snapshot one region at most in `www/front_src` tests, and update a snapshot only after reviewing its diff

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

The legacy Cypress specs take [visual snapshots](../glossary.md#visual-snapshot) with `cy.makeSnapshot()`, compared with a baseline image at a 7% tolerance. A failing snapshot is the most common wrong shortcut: regenerating the baselines wholesale makes the spec pass and records the regression as the new truth. [Browser tests](../glossary.md#browser-test) have no screenshot; rstest can snapshot the HTML of an element with `toMatchSnapshot()`, and a whole-page HTML snapshot breaks on every change while asserting nothing readable.

[MON-212527](https://centreon.atlassian.net/browse/MON-212527) weighed a hook that would block wholesale snapshot updates and dropped it: the rule, stated and reviewed, costs less than 170 lines of shell.

Two alternatives were weighed:

- **Whole-page snapshots as the main assertion.** It lost: the diff of a whole page is unreadable, so it is accepted without being read.
- **No snapshot at all.** It lost: a small region with a stable structure (a rendered label, a tooltip) is cheaper to snapshot than to assert attribute by attribute.

Settled in [#12178](https://github.com/centreon/centreon/pull/12178).

## Decision

In a browser test, **assert what matters explicitly**; snapshot **the HTML of one region at most**, never the whole page. In a legacy spec, **update a snapshot only after reviewing its diff**: run the one spec (`pnpm cypress:run --spec <path>`), read its `*.diff.png`, and update that spec's baselines only when the change is intended (`pnpm cypress:run:updateSnapshot --spec <path>`). **Never regenerate baselines wholesale**, and never touch the baselines of a spec the change does not cover.

## Consequences

- A snapshot diff is read like a code diff, in the pull request.
- A migrated spec replaces each visual snapshot by assertions on what its baseline showed.
- The rule is enforced in review.

## When to Amend

- No Cypress spec remains in `www/front_src`; the legacy part of the record then goes.
- A visual testing tool with reviewable diffs is adopted for browser tests.
