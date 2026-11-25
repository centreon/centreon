# ADR-0043: Load federated entries with `React.lazy`, so every exposed entry default-exports its component

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

Centreon Web is the [host](../glossary.md#host) of the module frontends: the rspack configuration of `@centreon/js-config` (`rspack/base/index.js`) sets up module federation, and the host loads each [exposed entry](../glossary.md#exposed-entry) of a [federated module](../glossary.md#federated-module) at runtime with `React.lazy` (`www/front_src/src/federatedModules/Load/index.tsx`). `React.lazy` resolves a module's default export, so an exposed entry with a named export only renders nothing.

The contract has two sides: the host's loader, owned by this repository, and the modules' `exposes` and `moduleFederation.json`, owned by `centreon-modules`, whose rules already say that an exposed entry default-exports its component.

One alternative was weighed: **a loader that accepts named exports through a mapping.** It lost: one more convention to declare per entry, for no gain over a default export.

Settled in [centreon-modules#9605](https://github.com/centreon/centreon-modules/pull/9605).

## Decision

The host **loads every federated entry with `React.lazy`**. Therefore **every exposed entry default-exports its component**, and the loader of `@centreon/js-config` and `federatedModules/` keeps that contract.

## Consequences

- A change to how the host loads entries is a breaking change for every module frontend, and follows the compatibility rule of the package.
- A module entry that fails to load is an error boundary's fallback (`federatedModules/Load/ErrorBoundary.tsx`), not a blank page.
- The rule is enforced in review, on both repositories.

## When to Amend

- The host loads entries by another mechanism than `React.lazy`.
