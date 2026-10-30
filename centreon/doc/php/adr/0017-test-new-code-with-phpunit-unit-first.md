# ADR-0017: Test new code with PHPUnit, unit-first by default

- **Status:** Accepted
- **Date:** 2026-10-28

## Context

The backend test suite runs on **Pest** (`pestphp/pest`, the `composer test` entry point), while the modern code under `src/App` is tested with plain **PHPUnit** `TestCase` classes, and PHPUnit configurations (`phpunit.new.xml`, `phpunit.new.integration.xml`, …) already drive those runs. Two runners for one suite split the idioms — `pest()` closures versus `TestCase` methods, two sets of helpers — and reviewers have to know which applies where.

Beyond the runner, the team never wrote down what must be tested. A few recurring gaps keep surfacing in review: a new value object or aggregate root shipped without a unit test; a `*Criteria` or a `*Transformer` left untested because "the integration test covers it", so the component has no fast, isolated test of its own and relies on the slower suite running to catch a regression; a tautological unit test for a backed enum that has no behaviour; and an endpoint's Cloud and on-premise cases split across two files, so the pair drifts.

## Decision

One convention governs every new test: write it with PHPUnit, and unit-test each building block by default. The rules below are the facets of that convention.

- **Runner — PHPUnit, not Pest.** Every **new** test is a PHPUnit `TestCase`, including tests for legacy code (outside `src/App`). Do **not** add new Pest files. Existing Pest tests may still be edited in place; they are migrated opportunistically, not in a sweep.
- **Unit-test every building block, by default.** Value objects, entities, aggregate roots, enums that carry behaviour, `*Criteria`, `*Transformer`, and the ApiPlatform `*Input` / `*Output` DTOs each get their own dedicated unit test. A value object covers the happy path and **every invariant rejecting**; an aggregate root asserts it exposes its identity and members; a criteria or a transformer asserts every case it produces. Being covered by an integration test does **not** excuse skipping the unit test — the two are complementary: the unit test gives fast, isolated feedback that does not depend on the integration suite running, and neither replaces the other.
- **The only exemptions are the truly behaviourless.** A backed enum with no methods or invariants, and a bare `<Agg>Id` (an `AggregateRootId` subclass with no own invariant), need no dedicated unit test — a test of them is tautological, and they are covered where they are used (DB mapping, HTTP round-trip). The moment an enum gains a method or an invariant, it gets its own unit test.
- **Fakes, not mocks.** Use hand-written fakes rather than a mocking framework, and place them in a `Double/` folder next to the tests that use them.
- **One file per endpoint, both platforms.** When an endpoint behaves differently on Cloud and on-premise, both cases live in the endpoint's own test file, selected with `forcePlatform`. No dedicated `*CloudTest` / `*OnPremTest`.

## Consequences

- The suite converges on a single runner and a single set of idioms. Pest stays until the last file is migrated, then its dependency is dropped.
- Every building block is exercised directly, where a failure is cheapest to localise, and the suite catches a regression without waiting for the integration tests to run.
- Testing a component that an integration test already touches is deliberate duplication, not waste: the unit test pins the component's own contract, the integration test pins how the pieces fit together.
- Some of this is enforced by tooling (`MarkedClassesHaveTestRule` guards handlers and `Dbal*Repository`), but value objects, entities, aggregate roots, criteria, transformers and the DTOs are invisible to CI, so they are checked in review.

## When to Amend

- Pest is removed from the project and no Pest file remains.
- The `src/App` layering changes such that the Domain is no longer the right unit-test boundary.
