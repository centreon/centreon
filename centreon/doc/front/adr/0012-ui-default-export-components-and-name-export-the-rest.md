# ADR-0012: Default-export `packages/ui` components, and name-export everything else

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`packages/ui` has two export surfaces. Inside the package, a component file default-exports its component, as the applications do: 337 `.tsx` files of `packages/ui/src` default-export at the time of writing. Outside, consumers import from the package entry, `src/index.ts`, which re-exports the public components, hooks and types; a consumer never imports a file path of the package.

An earlier shared rule asked for named exports only. It was dropped in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

Two alternatives were weighed:

- **Named exports only in component files.** It lost: it diverges from the applications for no gain, since the package entry already exposes everything by name.
- **Default exports for everything.** It lost: a hook, a type or a constant imported by name is found by its name across the package and its consumers.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

A **component** file **default-exports** its component. **Hooks, atoms, models, decoders and label constants are named exports.** The package entry **`src/index.ts` re-exports by name** what consumers may use; a consumer imports from `@centreon/ui`, never from a file path.

## Consequences

- Adding a component to the package means adding it to `src/index.ts`; what is not re-exported is private to the package.
- The rule is enforced in review. Biome does not check it.

## When to Amend

- The package moves to per-file entry points (`@centreon/ui/<component>`).
