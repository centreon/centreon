# ADR-0017: Write new tests with PHPUnit, and unit-test the Domain

- **Status:** Proposed
- **Date:** 2026-10-28

## Context

The backend test suite runs on **Pest** (`pestphp/pest`, the `composer test` entry point), while the modern code under `src/App` is tested with plain **PHPUnit** `TestCase` classes, and PHPUnit configurations (`phpunit.new.xml`, `phpunit.new.integration.xml`, …) already drive those runs. Two runners for one suite split the idioms — `pest()` closures versus `TestCase` methods, two sets of helpers — and reviewers have to know which applies where.

Beyond the runner, the team never wrote down what must be tested. A few recurring gaps keep surfacing in review: a new value object or aggregate root shipped without a unit test; a listing `*Criteria` or a `*Transformer` assumed to be covered by an integration test that in fact only asserts a row count; a tautological unit test for a backed enum that has no behaviour; and an endpoint's Cloud and on-premise cases split across two files, so the pair drifts.

## Decision

- **Runner — PHPUnit, not Pest.** Every **new** test is a PHPUnit `TestCase`, including tests for legacy code (outside `src/App`). Do **not** add new Pest files. Existing Pest tests may still be edited in place; they are migrated opportunistically, not in a sweep.
- **Unit-test the Domain.** A value object and an aggregate root each get a dedicated unit test: the value object covers the happy path and **every invariant rejecting**; the aggregate root asserts it exposes its identity and members. A trivial `<Agg>Id` (a bare `AggregateRootId` subclass with no own invariant) is exempt.
- **Criteria and transformers may skip a dedicated unit test only when truly covered.** A `*Criteria` or a `*Transformer` skips its own unit test **only** when an integration test (the repository test for a criteria or a DBAL transformer, the provider HTTP test for a resource transformer) provably exercises **every** case and **asserts the produced output**, not merely a count or a status code. If that coverage cannot be pointed to, the dedicated unit test is required.
- **Fakes, not mocks.** Use hand-written fakes rather than a mocking framework, and place them in a `Double/` folder next to the tests that use them.
- **No unit test for a behaviourless backed enum.** A backed enum with no methods or invariants is covered where it is used (its DB mapping, its HTTP round-trip), not by a tautological test of its own declaration.
- **One file per endpoint, both platforms.** When an endpoint behaves differently on Cloud and on-premise, both cases live in the endpoint's own test file, selected with `forcePlatform`. No dedicated `*CloudTest` / `*OnPremTest`.

## Consequences

- The suite converges on a single runner and a single set of idioms. Pest stays until the last file is migrated, then its dependency is dropped.
- The Domain's invariants are exercised directly, where a failure is cheapest to localise, instead of only through an endpoint.
- Allowing a criteria or transformer to skip its unit test puts the burden on the reviewer to open the integration test and confirm the coverage — the proof is the price of the skip.
- Some of this is enforced by tooling (`MarkedClassesHaveTestRule` guards handlers and `Dbal*Repository`), but value objects, aggregate roots and the skip proof are invisible to CI, so they are checked in review.

## When to Amend

- Pest is removed from the project and no Pest file remains.
- The `src/App` layering changes such that the Domain is no longer the right unit-test boundary.
