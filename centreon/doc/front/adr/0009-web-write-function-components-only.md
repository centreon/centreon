# ADR-0009: Write `www/front_src` components as functions, error boundaries excepted

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

`www/front_src` is written with function components and hooks. Data fetching (`useFetchQuery`), state (Jotai atoms) and forms (`Form` from `@centreon/ui`) are all hooks or hook-based components: a class component cannot use them without a wrapper. React keeps one capability for classes only: an error boundary needs `getDerivedStateFromError` or `componentDidCatch`, which have no hook equivalent, and `federatedModules/Load/ErrorBoundary.tsx` is such a class.

One alternative was weighed: **allow class components where a developer prefers them.** It lost: two component models in one code base, and the hooks the rules require are unreachable from a class.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Write every component as a **function**. The only class component is an **error boundary**.

## Consequences

- Logic that a class would keep in methods goes into a custom hook.
- A new error boundary is a class, kept to the boundary itself: the fallback it renders is a function component.
- The rule is enforced in review.

## When to Amend

- React offers a hook for error boundaries; the exception then goes.
