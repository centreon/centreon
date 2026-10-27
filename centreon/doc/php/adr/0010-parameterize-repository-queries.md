# ADR-0010: Build repository SQL with bound `QueryParameters`, never string concatenation

- **Status:** Accepted
- **Date:** 2026-06-17

## Context

A [review comment on PR 8803](https://github.com/centreon/centreon/pull/8803/changes#r3378773489) found SQL built by concatenating request data. The same pattern existed in every `filterByCriteria` in `src/App`. [MON-201322](https://centreon.atlassian.net/browse/MON-201322) was created to fix the existing code.

## Decision

Pass every user-provided value in a repository query as a **bound parameter**, through `ConnectionInterface` with **`QueryParameters`**, for example `QueryParameters::create([QueryParameter::string(...)])`. **Never** concatenate or interpolate request data into SQL, including `LIKE` patterns and dynamic filters.

## Consequences

- The existing `filterByCriteria` implementations need a pass, tracked in [MON-201322](https://centreon.atlassian.net/browse/MON-201322).
- Review checks the rule. A search for concatenated SQL is only a heuristic, so a hit is confirmed by reading the code.

## When to Amend

- No foreseeable trigger. Revisit only if a concrete problem surfaces.
