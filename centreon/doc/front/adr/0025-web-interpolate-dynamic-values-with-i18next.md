# ADR-0025: Put dynamic values in `www/front_src` labels with i18next interpolation, never concatenation

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

A label often carries a value: a name, a count, a date. Built by concatenation, `` `${t(labelExit)} ${name}` ``, the sentence a translator sees is `Exit`, with no way to put the name first in a language that needs it, and the translated fragment never matches the whole sentence. i18next interpolates placeholders inside the message id: `'Exit {{name}}'` with `t(labelExit, { name })`, so the translator sees and reorders the whole sentence.

One alternative was weighed: **concatenate when the value comes last in English.** It lost: it comes last in English only.

Settled in [#12109](https://github.com/centreon/centreon/pull/12109).

## Decision

A dynamic value goes into the label through **i18next interpolation**: the [label constant](../glossary.md#label-constant) holds the placeholder, `'Exit {{name}}'`, and the call passes the value, `t(labelExit, { name })`. **Never concatenate a translated fragment with a value.** A plural uses i18next's `count`.

## Consequences

- The message id in the `.po` files holds the placeholder, and translators reorder it freely.
- A test finds the rendered text, not the constant, when the label is interpolated.
- The rule is enforced in review.

## When to Amend

- i18next is replaced.
