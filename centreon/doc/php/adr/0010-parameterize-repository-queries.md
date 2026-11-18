# ADR-0010: Build repository SQL with the `QueryBuilder` and bound parameters, never string concatenation

- **Status:** Accepted
- **Date:** 2026-06-17

## Context

A [review comment on PR 8803](https://github.com/centreon/centreon/pull/8803/changes#r3378773489) found SQL built by concatenating request data. The same pattern existed in every `filterByCriteria` in `src/App`. [PR 10789](https://github.com/centreon/centreon/pull/10789) fixed the existing code.

## Decision

Build repository queries with the **`QueryBuilder`** rather than raw SQL strings. Pass every value as a **bound query parameter**: `setParameter()` or `createNamedParameter()` on the `QueryBuilder`, or `QueryParameters` where a query goes through `ConnectionInterface`. **Never** concatenate or interpolate request data into SQL, including `LIKE` patterns and dynamic filters.

## Consequences

- The existing `filterByCriteria` implementations were fixed in [PR 10789](https://github.com/centreon/centreon/pull/10789).
- Review checks the rule. A search for concatenated SQL is only a heuristic, so a hit is confirmed by reading the code.

## When to Amend

- No foreseeable trigger. Revisit only if a concrete problem surfaces.
