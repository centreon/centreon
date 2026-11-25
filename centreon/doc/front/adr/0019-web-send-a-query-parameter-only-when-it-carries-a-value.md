# ADR-0019: Send a query parameter from `www/front_src` only when it carries a value

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

An [endpoint builder](../glossary.md#endpoint-builder) composes the query string of a listing from the user's filters: a search, a selection, a sort. When a filter is not set, the builder can still append it with an empty value (`name[lk]=`, an empty list in `search`). The API then receives a filter the user did not set, and the page shows a result the user did not ask for, or an error.

The trap is to test the filter's container rather than its value: after a selection, an autocomplete's `search` object exists and holds only a `$ni` condition, so a check on the object is true while the user typed nothing. `buildListingEndpoint` from `@centreon/ui` and its `getSearchQueryParameterValue` handle the shared cases; a feature's own parameters are its own responsibility.

One alternative was weighed: **let the API ignore empty filters.** It lost: the frontend cannot assume it, and a request that differs from the user's intent is wrong even when the API tolerates it.

Settled in [#12827](https://github.com/centreon/centreon/pull/12827).

## Decision

An endpoint builder **adds a query parameter only when its value is defined and non-empty**. **Condition on the value sent, not on its container.** Every builder written or changed has a **Jest test of the built URL with and without the value**.

## Consequences

- The built URL is the contract: a test reads it, as `packages/ui/src/api/buildListingEndpoint/index.test.ts` does for the shared builder.
- A parameter the API requires even when empty is passed explicitly and the test says so.
- The rule is enforced by the tests and in review.

## When to Amend

- The API documents that it ignores empty filters on every listing; the test then stays and the rule relaxes to "no empty parameter in a new builder".
