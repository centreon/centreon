# ADR-0026: Put dynamic values in `packages/ui` labels with i18next interpolation, never concatenation

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

A component of `packages/ui` often renders a label with a value: a count of selected rows, a page number, a name. Built by concatenation, the sentence a translator sees is a fragment, and the word order is English in every language. i18next interpolates placeholders inside the message id: `'{{count}} selected'` with `t(labelSelected, { count })`, so the translator sees the whole sentence. In a published component the fragment would reach every consumer's translators.

One alternative was weighed: **let the consumer format the sentence.** It lost: the default rendering is the package's, and it must be translatable on its own.

Settled in [#12109](https://github.com/centreon/centreon/pull/12109).

## Decision

A dynamic value goes into the label through **i18next interpolation**: the [label constant](../glossary.md#label-constant) holds the placeholder and the call passes the value. **Never concatenate a translated fragment with a value.** A plural uses i18next's `count`.

## Consequences

- The message ids the package exports hold their placeholders; consumers translate whole sentences.
- The rule is enforced in review.

## When to Amend

- i18next is replaced.
