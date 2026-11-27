# ADR-0003: Migrate the `makeStyles` rules a `www/front_src` change touches to Tailwind, and add none

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

[Legacy styles](../glossary.md#legacy-styles) (`makeStyles` from `tss-react/mui`, in `*.styles.ts` files) are the majority of `www/front_src` styling: 118 `*.styles.ts` files at the time of writing. New code uses Tailwind utilities. The question is what to do with the legacy styles of a component a change touches.

Two alternatives were weighed:

- **A one-off migration of every component.** It lost: hundreds of components, each with visual snapshots to re-check, with no feature value to ship alongside; no one owns such a sweep.
- **Leave legacy styles alone and only write new code in Tailwind.** It lost: a touched component then carries both systems for the same element, and the `*.styles.ts` file never empties.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

When a change touches a component that uses `makeStyles`, **migrate the styles the change touches to Tailwind in the same pull request**, and the whole component when feasible. **Delete the `*.styles.ts` file once it is empty.** **Add no new `makeStyles` rule**, except as the last-resort fallback for a style that neither Tailwind nor the MUI props can express.

## Consequences

- The migration advances with the features, and a reviewer sees it next to the change that motivated it.
- The cost is a larger diff on a touched component, and its visual snapshots or browser tests to re-check.
- Exception: a mechanical edit (a rename, an import move) that leaves a `makeStyles` block in a diff file is stated in the pull request as an explicit question to the reviewer, with the cost of migrating it, not as a settled choice.
- The rule is enforced in review.

## When to Amend

- No `makeStyles` call remains in `www/front_src`; the record is then history.
- A one-off migration is scheduled and owned.
