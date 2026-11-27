# ADR-0034: Migrate a `www/front_src` Cypress spec test for test, dropping nothing

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

Migrating a Cypress spec to a [browser test](../glossary.md#browser-test) is a translation, and a translation can silently stop testing: a test merged into another, a `.should()` with no counterpart, an `it.skip` left behind, a visual snapshot dropped with the checks it stood for. The migrated suite is green and covers less. `test-setup/MIGRATING_FROM_CYPRESS.md`, added with the first migration in [#12099](https://github.com/centreon/centreon/pull/12099), maps every Cypress command and assertion to its rstest equivalent.

One alternative was weighed: **rewrite the tests from the component's behaviour rather than from the spec.** It lost: the spec is the record of what the team decided to cover; a rewrite forgets the cases that were added after a bug.

Settled in [#12141](https://github.com/centreon/centreon/pull/12141).

## Decision

A migrated spec keeps **every `it()`, with the same name, in the same `describe`**. **Nothing is dropped, merged or skipped**: `it.skip`, `it.todo` and `.only` are not allowed. **Every `.should(...)` and `expect(...)` has an equivalent**; the only check that may go is a visual snapshot, replaced by assertions on what its baseline showed, and the pull request says so. The mapping is the one of `MIGRATING_FROM_CYPRESS.md`.

## Consequences

- A reviewer compares the two files test by test; the same names make it mechanical.
- A behaviour the Cypress spec covered only through a screenshot gets an explicit assertion, which usually catches more.
- A bug the migration reveals in the component is fixed in the same pull request or stated in it.
- The rule is enforced in review.

## When to Amend

- No Cypress spec remains in `www/front_src`; the record is then history.
