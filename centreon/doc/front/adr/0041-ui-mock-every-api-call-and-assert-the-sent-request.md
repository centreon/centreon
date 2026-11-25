# ADR-0041: Mock every API call of a `packages/ui` component test, and assert the request it sends

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

Some components of `packages/ui` fetch on their own: a connected autocomplete field searches as the user types, a listing's filters load their options. Their [browser tests](../glossary.md#browser-test) use the shared [API mocks](../glossary.md#api-mock) of `test-setup/api.ts`: `interceptAPIRequest` answers a request, `waitForRequest('@alias')` returns the request the mock received. The migration in [#12148](https://github.com/centreon/centreon/pull/12148) found that the search mock of `MultiConnectedAutocompleteField` never matched the request the field sent, so the search was never tested: a mock that is never hit proves nothing unless the test asserts the request.

Two alternatives were weighed:

- **Mock the hooks instead of the network.** It lost: the test then skips the endpoint the component builds, which is what a consumer depends on.
- **A real backend.** It lost: a published component owns no data.

Settled in [#12148](https://github.com/centreon/centreon/pull/12148).

## Decision

**Mock every API call** a component makes with `interceptAPIRequest`, with an **alias**. **Assert the request the component sends** with `const { request } = await waitForRequest('@alias')`: a mock that is never awaited is a mock that may never match.

## Consequences

- `waitForRequest` resolves when the response is sent, before the component applied it: what it shows next is checked with `findBy*` or `waitFor`.
- A test of a pending state passes a `delay` to the mock.
- The rule is enforced in review.

## When to Amend

- The test setup replaces MSW.
