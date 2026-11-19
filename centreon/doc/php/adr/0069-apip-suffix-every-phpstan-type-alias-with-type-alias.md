# ADR-0069: Suffix every `src/App` `@phpstan-type` alias with `TypeAlias`

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** APIP

## Context

deptrac does not parse PHPStan type aliases: it reads a `@phpstan-type` or `@phpstan-import-type` name as a class it cannot place in any layer, and reports it. `deptrac_hexa.yaml` and `deptrac_bc.yaml` therefore collect every name ending in `TypeAlias` in a layer that every `src/App` layer and context may use. An alias named otherwise fails `backend-lint`, and the error does not say why.

Two alternatives were weighed:

- **Skip the violations one by one in deptrac.** It lost: every new alias needs a configuration change.
- **Avoid type aliases.** It lost: an alias names an array shape once instead of repeating it.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**Name every `@phpstan-type` alias of `src/App` with the `TypeAlias` suffix** (`RowTypeAlias`), and import it under that name.

## Consequences

- deptrac places every alias without configuration.
- An alias without the suffix fails `backend-lint` in CI.

## When to Amend

- deptrac parses PHPStan type aliases.
