# ADR-0024: Translate `packages/ui` text through label constants, never a string literal in `t()`

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

The components of `packages/ui` carry user-facing text (a "Clear" button, a "No result" placeholder, column headers of a listing) that every consumer translates with `react-i18next` and the applications' `.po` files. The package exports those message ids as constants from `translatedLabels.ts` files, 18 of them at the time of writing, so a consumer and its tests can import the exact id. A literal in `t()` inside the package would be an id no consumer can find or test against.

One alternative was weighed: **let consumers pass every label as a prop.** It lost: a default is still needed, and the default is the constant.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Every user-facing string of a component is an **exported English constant of a `translatedLabels.ts`** next to it. **`t()` only ever takes such a constant, never a string literal.** A component that lets its consumer override a label takes the override as a prop and uses the constant as the default.

## Consequences

- The constants of the package are public API: a consumer's test may import them.
- A label changed in the package changes a message id for every consumer, and their `.po` files.
- The rule is enforced in review.

## When to Amend

- The translation pipeline extracts message ids from `t()` calls and checks them in CI.
