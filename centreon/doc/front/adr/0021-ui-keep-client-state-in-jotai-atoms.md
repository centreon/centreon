# ADR-0021: Keep `packages/ui` client state in Jotai atoms, never Redux or a new React Context

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

The state a `packages/ui` component shares beyond its own tree is held in Jotai [atoms](../glossary.md#atom): 7 `atoms.ts` files in the package at the time of writing. The application-wide atoms the applications and the module frontends read are exported by `@centreon/ui-context`, which, despite its name, exports Jotai atoms and no React Context. A context provided by a published component would have to be mounted by every consumer; an atom does not.

Two alternatives were weighed:

- **A React Context per component family.** It lost: every consumer re-renders on any change of the context value, and the provider must be mounted by each application.
- **Redux.** It lost: no application uses it, and a published store would force one on them.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Keep the state a component shares in **Jotai atoms**, in an **`atoms.ts`** next to the component. **No Redux. No new React Context.** State that the applications and module frontends read goes in `@centreon/ui-context`.

## Consequences

- A published component needs no provider from its consumer.
- An atom exported by the package is public API: it follows the compatibility rules of the package.
- The rule is enforced in review.

## When to Amend

- Jotai is replaced.
