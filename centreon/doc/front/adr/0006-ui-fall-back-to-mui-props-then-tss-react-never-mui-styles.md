# ADR-0006: Fall back to MUI props, then `tss-react/mui`, only for what Tailwind cannot express in `packages/ui`

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

Tailwind utilities are the styling of new `packages/ui` code. Some styles cannot be expressed as a utility: a style that depends on a runtime value, a MUI internal slot, a selector a utility cannot target. The package offers three fallbacks: the props of the MUI component (`sx`, slot props), `makeStyles` from `tss-react/mui`, and `@mui/styles`, the legacy MUI styling solution.

The earlier shared rule said "never use `makeStyles`" without naming `tss-react`, whose primary API is `makeStyles`. It was replaced in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

Two alternatives were weighed:

- **No fallback: everything in Tailwind.** It lost: some styles cannot be expressed as a utility, and forcing them leads to `!important` and selector hacks that every consumer inherits.
- **`@mui/styles` as the fallback.** It lost: `tss-react/mui` is its maintained replacement, already used by the package.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

For what Tailwind cannot express, and only then, fall back **first to the props of the MUI component**, **then to `makeStyles` from `tss-react/mui`**. **Never `@mui/styles`.** Before falling back, read the computed style in the browser: a utility that seems not to apply usually loses to a more specific selector.

## Consequences

- A fallback is the exception and says why in the pull request.
- A style set through a fallback is not overridable by a consumer's utilities the way a utility is: the pull request states what the consumer can still change.
- The rule is enforced in review.

## When to Amend

- `tss-react` is removed from the dependencies.
- MUI changes how its components accept styles.
