# ADR-0035: Migrate a `packages/ui` Cypress spec test for test, dropping nothing

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

Migrating a Cypress spec to a [browser test](../glossary.md#browser-test) is a translation, and a translation can silently stop testing. The migration of the package's 27 specs in [#12148](https://github.com/centreon/centreon/pull/12148) set the method: 184 `it()` with the same names and nesting, checked by script; every visual snapshot replaced by assertions on what its baseline showed; bugs injected into each component to prove the migrated tests catch them.

One alternative was weighed: **rewrite the tests from the component's behaviour.** It lost: the spec is the record of what the team decided to cover.

Settled in [#12148](https://github.com/centreon/centreon/pull/12148).

## Decision

A migrated spec keeps **every `it()`, with the same name, in the same `describe`**. **Nothing is dropped, merged or skipped**: `it.skip`, `it.todo` and `.only` are not allowed. **Every `.should(...)` and `expect(...)` has an equivalent**; the only check that may go is a visual snapshot, replaced by assertions on what its baseline showed, and the pull request says so. The mapping is the one of `test-setup/MIGRATING_FROM_CYPRESS.md`.

## Consequences

- A reviewer compares the two files test by test.
- A published component whose migration reveals a bug gets the fix in the same pull request, as `RichTextEditor` did in #12148, or the bug is stated in it.
- The rule is enforced in review.

## When to Amend

- No Cypress spec remains in `packages/ui`; the record is then history.
