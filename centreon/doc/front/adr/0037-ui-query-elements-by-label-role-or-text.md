# ADR-0037: Query elements in `packages/ui` tests by label, role or text; a test id is the last resort

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

A [browser test](../glossary.md#browser-test) of a published component finds elements with Testing Library queries. A [semantic query](../glossary.md#semantic-query) finds the element the way a user, and a consumer's test, does: by role, label or text. A `data-testid` finds it whatever it renders, so a component that lost its accessible name still passes its own tests, and every consumer inherits the regression. Some controls of the package put the same `data-testid` on a wrapper and its input, which forces an index in every test that uses it.

Two alternatives were weighed:

- **Test ids everywhere, for stability.** It lost: a test id proves nothing about what a user or a consumer's test sees.
- **Semantic queries only.** It lost: some elements have no accessible name yet, and the rule would block the test rather than the markup.

Settled in [#12827](https://github.com/centreon/centreon/pull/12827).

## Decision

Find a **control** (a button, a field, a link, a tab) **by its role and accessible name** (`getByRole('button', { name })`) or **by its label** (`getByLabelText`), **an image by its alternative text** (`getByAltText`) or its role, and **other content by its text**. A **`data-testid` is the last resort**, when no query by role and name, label, alternative text or text can reach the element, and the pull request says why.

## Consequences

- A component whose test needs a test id is a component without an accessible name; fixing the component is preferred, and benefits every consumer.
- A `data-testid` the package renders is public API: a consumer's test may depend on it, so it is not removed lightly.
- The rule is enforced in review.

## When to Amend

- Every control of the package exposes an accessible name; the last resort may then go.
