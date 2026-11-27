# ADR-0011: Default-export `www/front_src` components, and name-export everything else

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

The routes of `www/front_src/src/reactRoutes/index.ts` are loaded with `React.lazy(() => import(...))`, and the host loads the [exposed entries](../glossary.md#exposed-entry) of the federated modules the same way (`federatedModules/Load/index.tsx`). `React.lazy` resolves the module's **default** export. At the time of writing, 743 `.tsx` files of `www/front_src/src` default-export, against 63 that do not.

An earlier shared rule asked for named exports only. It contradicted the code and the loader, and was dropped in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

Two alternatives were weighed:

- **Named exports only, with `.then((m) => ({ default: m.X }))` on every lazy import.** It lost: a wrapper on each of the lazy routes and federated entries, for no gain over the convention the code already follows.
- **Default exports for everything.** It lost: a hook, an atom, a model or a label constant imported by name is found by its name across the code base; a default export is renamed at every import site.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

A **component** file **default-exports** its component. **Hooks, atoms, models, decoders, endpoints and label constants are named exports.**

## Consequences

- A lazy route or a federated entry needs no wrapper.
- A component file may also name-export its props type or a sub-component used by its tests.
- The rule is enforced in review. Biome does not check it.

## When to Amend

- Routes and federated entries are loaded by a mechanism that does not require a default export.
