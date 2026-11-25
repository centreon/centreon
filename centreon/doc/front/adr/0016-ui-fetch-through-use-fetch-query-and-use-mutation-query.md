# ADR-0016: Fetch in `packages/ui` through `useFetchQuery` and `useMutationQuery`, never `useRequest`, axios or `useEffect`

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`packages/ui` defines the data hooks of the frontend: `useFetchQuery` for reads and `useMutationQuery` for writes (`src/api`), both built on `customFetch` and the native `fetch`. Some of its own components fetch data (connected autocomplete fields, a listing's filters): 8 files of the package use `useFetchQuery` at the time of writing. A component of the package that fetched another way would bring a second HTTP client into every consumer's bundle.

Two alternatives were weighed:

- **Let a published component accept a fetcher from the consumer.** It lost for the general case: it moves the caching and cancellation to every consumer; a component may still accept an endpoint or a decoder.
- **`useEffect` + `useState` for simple cases.** It lost: no cancellation, a race between two renders, and no cache.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

A component or hook of the package fetches through **`useFetchQuery`** and mutates through **`useMutationQuery`**. **No `useRequest`, axios call or `useEffect` fetching.**

## Consequences

- The package's own data hooks are the only HTTP layer it ships.
- A component that fetches takes its endpoint, and where the shape matters its decoder, as props, so the consumer owns the contract.
- The rule is enforced in review.

## When to Amend

- The package replaces its data hooks.
