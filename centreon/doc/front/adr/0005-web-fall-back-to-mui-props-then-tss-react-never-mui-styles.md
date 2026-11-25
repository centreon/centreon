# ADR-0005: Fall back to MUI props, then `tss-react/mui`, only for what Tailwind cannot express

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

Tailwind utilities are the styling of new `www/front_src` code. Some styles cannot be expressed as a utility: a style that depends on a runtime value, a MUI internal slot, a selector a utility cannot target. The code base offers three fallbacks: the props of the MUI component (`sx`, slot props), `makeStyles` from `tss-react/mui`, and `@mui/styles`, which survives in 3 files at the time of writing.

The earlier shared rule said "never use `makeStyles`" without naming `tss-react`, whose primary API is `makeStyles`: it steered away from the dominant pattern of the code base and left no fallback at all. It was replaced in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

Two alternatives were weighed:

- **No fallback: everything in Tailwind.** It lost: some styles cannot be expressed as a utility, and forcing them leads to `!important` and selector hacks.
- **`@mui/styles` as the fallback.** It lost: the package is the legacy MUI styling solution, present in 3 files only, and `tss-react/mui` is its maintained replacement already used everywhere else.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

For what Tailwind cannot express, and only then, fall back **first to the props of the MUI component**, **then to `makeStyles` from `tss-react/mui`**. **Never `@mui/styles`.** Before falling back, read the computed style in the browser: a utility that seems not to apply usually loses to a more specific selector, and the fix is the selector, not a fallback.

## Consequences

- A fallback is the exception and says why in the pull request.
- The 3 files importing `@mui/styles` are migrated when touched.
- The rule is enforced in review.

## When to Amend

- `tss-react` is removed from the dependencies.
- MUI changes how its components accept styles.
