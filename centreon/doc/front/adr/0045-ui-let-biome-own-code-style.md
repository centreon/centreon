# ADR-0045: Let Biome own `packages/ui` code style; no formatting rule in prose

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

Code style in `packages/ui` is checked and fixed by Biome: its `lint` script runs `biome check ./src/ --error-on-warnings`, and the shared configuration lives in the sibling package `packages/js-config/biome/`. The package README still says to lint with ESLint, and `packages/js-config/eslint/` still exists while nothing references it: prose and leftovers drift, the configuration does not.

One alternative was weighed: **keep a short style guide for what Biome cannot check.** It lost: what Biome cannot check is a decision, and a decision is an ADR.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

**The Biome configuration is the code style.** No document restates a formatting or lint rule, and no code works around one: a disagreement with a rule is a **change to `packages/js-config/biome/biome.json`**, reviewed as such.

## Consequences

- `packages/js-config/biome/` is published with `@centreon/js-config`: a change there changes the style of Centreon Web and of every module frontend.
- The ESLint leftovers of `packages/js-config` and the README mentions of ESLint are removed when touched.
- The rule is enforced by Biome in CI and in the editor hook.

## When to Amend

- Biome is replaced.
