# ADR-0004: Migrate the `makeStyles` rules a `packages/ui` change touches to Tailwind, and add none

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

[Legacy styles](../glossary.md#legacy-styles) (`makeStyles` from `tss-react/mui`) remain in many `packages/ui` components, next to components already styled with Tailwind utilities. The question is what to do with the legacy styles of a component a change touches.

Two alternatives were weighed:

- **A one-off migration of the whole package.** It lost: every consumer, the module frontends included, would receive the visual changes of every component at once, with no way to review them per component.
- **Leave legacy styles alone and only write new components in Tailwind.** It lost: a touched component then carries both systems for the same element, and the `*.styles.ts` file never empties.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

When a change touches a component that uses `makeStyles`, **migrate the styles the change touches to Tailwind in the same pull request**, and the whole component when feasible. **Delete the `*.styles.ts` file once it is empty.** **Add no new `makeStyles` rule**, except as the last-resort fallback for a style that neither Tailwind nor the MUI props can express.

## Consequences

- The migration advances component by component, each change reviewable on its own.
- A migrated component is published to every consumer: its browser tests, and the Storybook story when it has one, are re-checked in the same pull request.
- Exception: a mechanical edit that leaves a `makeStyles` block in a diff file is stated in the pull request as an explicit question to the reviewer, with the cost of migrating it.
- The rule is enforced in review.

## When to Amend

- No `makeStyles` call remains in `packages/ui`; the record is then history.
- A one-off migration is scheduled and owned.
