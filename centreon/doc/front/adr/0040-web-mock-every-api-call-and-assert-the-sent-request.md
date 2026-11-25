# ADR-0040: Mock every API call of a `www/front_src` component test, and assert the request it sends

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

A component of `www/front_src` fetches through `useFetchQuery` and `useMutationQuery`. In a [browser test](../glossary.md#browser-test), `test-setup/api.ts` registers [API mocks](../glossary.md#api-mock) with MSW: `interceptAPIRequest({ alias, ... })` answers a request, `waitForRequest('@alias')` returns the request the mock received, with its URL, method and parsed body, and `getRequestCalls('@alias')` lists them. An unmocked request fails the test rather than reaching a server.

What a component sends is half of its contract with the API: a filter missing from a listing URL, a field missing from a form payload, is invisible to a test that only checks what the page shows after the mocked answer.

Two alternatives were weighed:

- **A real backend in component tests.** It lost: it is the end-to-end suite's job, and it makes a component test depend on data it does not own.
- **Mock the hooks instead of the network.** It lost: the test then skips the endpoint builder and the decoder, which is where the contract lives.

Settled in [#12141](https://github.com/centreon/centreon/pull/12141).

## Decision

**Mock every API call** the component makes with `interceptAPIRequest` from `@test-setup/api`, with an **alias**, in the feature's `initialize.tsx`; mock responses live in its `utils.ts`. **Assert the request the component sends** with `const { request } = await waitForRequest('@alias')`: its URL for a listing, its body for a write.

## Consequences

- `waitForRequest` resolves when the response is sent, before the page applied it: what the page shows next is checked with `findBy*` or `waitFor`, never a plain `getBy*`.
- A mocked response comes back at once; a test of a pending state passes a `delay` to the mock.
- The request log and the handlers are reset after each test; URL state is not, and is reset by the test's `initialize`.
- The rule is enforced in review.

## When to Amend

- The test setup replaces MSW.
