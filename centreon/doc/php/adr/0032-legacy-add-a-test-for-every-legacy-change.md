# ADR-0032: Add a test for every change to legacy code, at the lowest level that proves it

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Legacy

## Context

Much legacy code has no test, and many `www/` scripts work on globals that a unit test cannot set up. A change to one of them often ships untested, because writing the first test of the class looks like a task of its own. A regression then surfaces far from its cause, in an integration scenario, a QA campaign or production.

One alternative was weighed:

- **Require the whole class to be covered when it is changed.** It lost: on a large legacy class the cost is out of proportion with the change, so the rule would be skipped.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**When you change code in the [Legacy scope](../glossary.md#scope), add a test covering that change, as a reflex**, even when the class has no test today: a new test class, or a new case in an existing test file. **Write it at the lowest level that can prove the change**, usually a unit test. **Cover the change, not the whole class.**

A rename or a move that changes nothing at run time needs no test. When the changed code cannot be tested without a refactor, such as a `www/` script working on globals, extract the changed logic into a function or class and test that, or state in the pull request why no test is possible.

## Consequences

- Each legacy change leaves a test behind, or a stated reason, and the legacy test coverage grows where the code moves.
- The first test of untested code can cost more than the change itself, an extraction included; that cost is accepted.
- The rule is enforced in review.

## When to Amend

- No legacy code remains.
