# ADR-0010: Write `packages/ui` components as functions, error boundaries excepted

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`packages/ui` is written with function components and hooks, and it is where the hooks the applications depend on live (`useFetchQuery`, `useMutationQuery`, the `Form` component). A class component cannot use a hook, so a class in the package would have to be wrapped by every consumer that needs one. React keeps one capability for classes only: an error boundary needs `getDerivedStateFromError` or `componentDidCatch`, which have no hook equivalent.

One alternative was weighed: **allow class components where a developer prefers them.** It lost: two component models in one package, and a published class component constrains every consumer.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Write every component as a **function**. The only class component is an **error boundary**.

## Consequences

- Logic that a class would keep in methods goes into a custom hook, exported when a consumer needs it.
- A new error boundary is a class, kept to the boundary itself: the fallback it renders is a function component.
- The rule is enforced in review.

## When to Amend

- React offers a hook for error boundaries; the exception then goes.
