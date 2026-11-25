# ADR-0015: Fetch in `www/front_src` through `useFetchQuery` and `useMutationQuery`, never `useRequest`, axios or `useEffect`

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

`@centreon/ui` wraps TanStack Query in two hooks, `useFetchQuery` for reads and `useMutationQuery` for writes (`packages/ui/src/api`). Both go through `customFetch`, built on the native `fetch` with an `AbortSignal`. They give every call the same caching, deduplication, cancellation on unmount and error handling.

Three older ways remain in `www/front_src`: the legacy `useRequest` hook (53 files at the time of writing), direct axios calls (21 files), and `useEffect` + `useState` fetching. An earlier shared rule still allowed axios "with cancel tokens"; it was dropped in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

Two alternatives were weighed:

- **Keep axios as an allowed second client.** It lost: two HTTP clients means two error formats, two cancellation models, and a cache that only one of them feeds.
- **`useEffect` + `useState` for simple cases.** It lost: no cancellation, a race between two renders, and no cache; the simple case is the one that grows.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Fetch through **`useFetchQuery`** and mutate through **`useMutationQuery`** from `@centreon/ui`. **No new `useRequest`, axios call or `useEffect` fetching.** Existing ones are migrated when touched.

## Consequences

- Every call shares the TanStack Query cache; a listing refetched after a mutation is a cache invalidation, not a second fetch mechanism.
- A one-off call outside a component uses the same `customFetch`, never axios.
- The rule is enforced in review.

## When to Amend

- `@centreon/ui` replaces its data hooks.
