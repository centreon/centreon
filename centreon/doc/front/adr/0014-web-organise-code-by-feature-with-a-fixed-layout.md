# ADR-0014: Organise `www/front_src` code by feature, with a fixed layout

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

A [feature](../glossary.md#feature) of `www/front_src/src` owns everything it needs: its API calls, its components, its hooks, its state and its labels. The recent configuration pages follow the same layout, with `Configuration/HostGroups` as the canonical one:

```
<Feature>/
  api/endpoints.ts        endpoint builders
  api/decoders.ts         decoders of the responses
  models.ts               TypeScript types of the feature
  atoms.ts                Jotai atoms of the feature
  translatedLabels.ts     label constants
  <Feature>.tsx, index.tsx, Form/, Columns/, hooks
  Specs/                  the feature's tests
```

An earlier shared rule placed the models in `api/models.ts` and named the form hook `useInputs.ts`. The audit of [MON-208227](https://centreon.atlassian.net/browse/MON-208227) counted 76 feature-root `models.ts` against 3 `api/models.ts`, and the rule was dropped in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

One alternative was weighed: **organise by kind (`components/`, `hooks/`, `api/`) across the application.** It lost: a change to one screen then touches every top-level folder, and nothing says which files belong together.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

**Organise code by feature.** A feature directory holds, under the names above, its **`api/endpoints.ts`** and **`api/decoders.ts`**, and at its root **`models.ts`**, **`atoms.ts`** and **`translatedLabels.ts`**. A new feature copies the shape of **`Configuration/HostGroups`**; a configuration page builds on `Configuration/ConfigurationBase`.

## Consequences

- A reviewer finds a feature's endpoints, decoders, state and labels at the same place in every feature.
- A file a feature does not need (no state, no form) is simply absent; an empty one is not created.
- Shared code goes up one level only when two features use it, and into `packages/ui` when it is not specific to Centreon Web.
- The rule is enforced in review.

## When to Amend

- The canonical feature changes; the record then names the new one.
