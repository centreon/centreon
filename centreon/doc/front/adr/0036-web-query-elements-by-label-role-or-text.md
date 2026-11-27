# ADR-0036: Query elements in `www/front_src` tests by label, role or text; a test id is the last resort

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

A [browser test](../glossary.md#browser-test) finds elements with Testing Library queries. A [semantic query](../glossary.md#semantic-query) (`getByRole('combobox', { name })`, `getByLabelText(label)`, `findByText(label)`) finds the element the way a user does, through the label constants of `translatedLabels.ts`, and fails when the element loses its accessible name. A `data-testid` finds it whatever it renders: a button that lost its label, a field that is no longer labelled, still pass.

The canonical specs mix both: `Configuration/Hosts/Specs/` has 237 queries by test id against 139 semantic ones at the time of writing, partly inherited from the Cypress specs they were migrated from, and partly because `@centreon/ui` text fields put the same `data-testid` on the wrapper and the input, which forces an index.

Two alternatives were weighed:

- **Test ids everywhere, for stability.** It lost: a test id proves nothing about what the user sees, and it is one more attribute to keep in the markup.
- **Semantic queries only.** It lost: some elements have no accessible name yet, and the rule would block the test rather than the markup.

Settled in [#12827](https://github.com/centreon/centreon/pull/12827).

## Decision

Find a **control** (a button, a field, a link, a tab) **by its role and accessible name** (`getByRole('button', { name: labelSave })`) or **by its label** (`getByLabelText`), **an image by its alternative text** (`getByAltText`) or its role, and **other content by its text**: a translated text through its [label constant](../glossary.md#label-constant), a value from the API through the mock data that returned it. A **`data-testid` is the last resort**, when no query by role and name, label, alternative text or text can reach the element, and the pull request says why.

## Consequences

- A test fails when a control loses its accessible name, which is an accessibility bug.
- A test id a new test needs is a signal that the markup lacks a label or a role; fixing the markup is preferred to adding the id.
- Existing test id queries stay until the test is touched.
- The rule is enforced in review.

## When to Amend

- Every `@centreon/ui` control exposes an accessible name; the last resort may then go.
