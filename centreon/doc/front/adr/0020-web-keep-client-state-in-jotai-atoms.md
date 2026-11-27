# ADR-0020: Keep `www/front_src` client state in Jotai atoms, never Redux or a new React Context

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

Client state in `www/front_src` is held in Jotai [atoms](../glossary.md#atom): 595 files import `jotai` at the time of writing, Redux is imported by none, and `createContext` appears in 2 files. The application-wide state the module frontends also read (ACL, platform features, refresh intervals) is exported as atoms by `@centreon/ui-context`, a package whose name predates its content: it exports Jotai atoms, not a React Context. Server state is not client state: it belongs to the TanStack Query cache.

Two alternatives were weighed:

- **Redux.** It lost: it was removed from the application, and an atom covers the same need with no reducer, action or store wiring.
- **React Context for feature-local state.** It lost: every consumer of a context re-renders on any change of its value, and a context must be provided from above, which an atom does not need.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Keep client state in **Jotai atoms**, in the feature's **`atoms.ts`**. **No Redux. No new React Context.** State that the module frontends read goes in `@centreon/ui-context`.

## Consequences

- An atom is read where it is used; no provider tree to maintain.
- A derived value is a derived atom, not a second atom kept in sync by an effect.
- The 2 existing `createContext` uses stay until touched.
- The rule is enforced in review.

## When to Amend

- Jotai is replaced.
