# ADR-0012: Default-export `packages/ui` components, and name-export everything else

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`packages/ui` has two export surfaces. Inside the package, a component file default-exports its component, as the applications do: 337 `.tsx` files of `packages/ui/src` default-export at the time of writing. Outside, consumers import from the entry points the `exports` field of `package.json` declares: `@centreon/ui` (`src/index.ts`), which re-exports the public components, hooks and types, and the subpaths `@centreon/ui/components` (`src/components/index.ts`), `@centreon/ui/tailwindcss` and `@centreon/ui/test/testRenderer`. A consumer never imports a file path that `exports` does not declare.

An earlier shared rule asked for named exports only. It was dropped in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

Two alternatives were weighed:

- **Named exports only in component files.** It lost: it diverges from the applications for no gain, since the package entry already exposes everything by name.
- **Default exports for everything.** It lost: a hook, a type or a constant imported by name is found by its name across the package and its consumers.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

A **component** file **default-exports** its component. **Hooks, atoms, models, decoders and label constants are named exports.** The **entry points declared in `exports` re-export by name** what consumers may use (`src/index.ts`, and `src/components/index.ts` for `@centreon/ui/components`); a consumer imports from `@centreon/ui` or a subpath that `exports` declares, never from a file path.

## Consequences

- Adding a component to the package means adding it to an entry point (`src/index.ts` or `src/components/index.ts`); what no entry point re-exports is private to the package.
- The rule is enforced in review. Biome does not check it.

## When to Amend

- The package moves to per-file entry points (`@centreon/ui/<component>`).
