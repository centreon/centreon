# ADR-0031: Add a test for every change to `src/Core`, at the lowest level that proves it

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Core

## Context

Some `src/Core` classes have no test. A change to one of them often ships untested, because writing the first test of the class looks like a task of its own. A regression then surfaces far from its cause, in an integration scenario, a QA campaign or production.

One alternative was weighed:

- **Require the whole class to be covered when it is changed.** It lost: on a large class the cost is out of proportion with the change, so the rule would be skipped.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**When you change `src/Core` code, add a test covering that change, as a reflex**, even when the class has no test today: a new test class, or a new case in an existing test file. **Write it at the lowest level that can prove the change**: a unit test for logic, an integration test under `tests/php/Core/Integration` for a query. **Cover the change, not the whole class.**

A rename or a move that changes nothing at run time needs no test. When the changed code cannot be tested without a refactor, state in the pull request why no test is added.

## Consequences

- Each `src/Core` change leaves a test behind, and its test coverage grows where the code moves.
- The first test of an untested class can cost more than the change itself; that cost is accepted.
- The rule is enforced in review.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
