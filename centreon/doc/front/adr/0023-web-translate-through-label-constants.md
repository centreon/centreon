# ADR-0023: Translate `www/front_src` text through label constants, never a string literal in `t()`

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

`www/front_src` is translated with `react-i18next`: `t()` takes a message id, and the translations come from the `.po` files of `lang/`. The message ids are English strings. Each [feature](../glossary.md#feature) exports them as constants from its `translatedLabels.ts` ([label constants](../glossary.md#label-constant)), and its components and tests import them.

A string literal in `t('Save')` works, but it is invisible: a search for the label finds the constant, not the literal; a test that finds an element by its label imports the constant, and a typo in the literal is a missing translation found by a user.

One alternative was weighed: **extract message ids from the source with a tool and allow literals.** It lost: the tests and the components would still refer to the string twice, and the extraction tool is not wired in CI.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Every user-facing string is an **exported English constant of the feature's `translatedLabels.ts`**. **`t()` only ever takes such a constant, never a string literal.** Tests find elements by the same constants.

## Consequences

- A label exists once; its uses, in components and tests, are found by its name.
- A label shared by several features goes in the nearest common `translatedLabels.ts`, or in `packages/ui` when it is not specific to Centreon Web.
- The rule is enforced in review.

## When to Amend

- The translation pipeline extracts message ids from `t()` calls and checks them in CI.
