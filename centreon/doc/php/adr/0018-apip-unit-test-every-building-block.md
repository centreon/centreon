# ADR-0018: Unit-test every `src/App` building block, and pin each rule at the lowest level

- **Status:** Accepted
- **Date:** 2026-10-28
- **Scope:** APIP

## Context

The team never wrote down what must be tested in `src/App`, nor at which level. A few recurring gaps keep surfacing in review: a new value object or aggregate root shipped without a unit test; a `*Criteria` or a `*Transformer` left untested because "the integration test covers it", so a regression surfaces far from its cause; a tautological unit test for a backed enum that has no behaviour; and the same rule asserted again, case by case, in a unit, an integration and an API test.

Two alternatives were weighed:

- **Rely on integration tests for the building blocks.** It lost: a failure is harder to localise, and only the cases the integration scenarios happen to reach are exercised.
- **Assert every rule again at each level**, unit, integration and API. It lost: one rule change breaks several tests, and the slow tests grow with every case.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

- **Unit-test every building block, by default.** Value objects, entities, aggregate roots, enums that carry behaviour, `*Criteria`, `*Transformer`, the ApiPlatform `*Input` DTOs that declare constraints, command and query handlers, domain and application services, factories, security voters and constraint validators each get their own unit test. A value object covers the happy path and **every invariant rejecting**; an aggregate root asserts its invariants and business methods; a criteria, a transformer or an `*Input` asserts every case it accepts or rejects. Being covered by an integration test does **not** excuse skipping the unit test.
- **Pin each rule once, at the lowest level that can prove it.** A rule pinned by a unit test is not asserted again case by case in an integration or API test, which proves only what a unit test cannot observe: the SQL against the real schema, the wiring, the HTTP round trip.
- **The only exemptions are the truly behaviourless.** A backed enum with no methods or invariants, a bare `<Agg>Id` (an `AggregateRootId` subclass with no own invariant), and an `*Output` DTO with no logic need no dedicated unit test: a test of them is tautological, and they are covered where they are used (DB mapping, HTTP round trip). The moment one gains a method or an invariant, it gets its own unit test.
- **Repositories are covered by integration tests, not unit tests.** A `Dbal*Repository` is exercised against a real database (a kernel-based integration test such as `KernelTestCase`), because the test's value is the SQL against the real schema. Handlers, by contrast, are unit-tested with fake repositories.
- **An ApiPlatform processor or provider is covered by its endpoint's API test** (`Tests\App\Shared\ApiTestCase`), which proves the wiring and the HTTP round trip: one happy path, then one case per status code the endpoint can return, not every rule again.

## Consequences

- Every building block is exercised directly, where a failure is cheapest to localise. `phpunit.new.xml` runs every `tests/php/App` test outside a context's `Infrastructure/` folder; the Infrastructure tests, unit or not, run in `phpunit.new.integration.xml`.
- Each rule is pinned once: the unit test pins the component's contract, the integration and API tests pin only how the pieces fit together. A test that re-asserts a rule already pinned at a lower level is not added.
- Existing building blocks without a test stay until they are touched.
- `MarkedClassesHaveTestRule` ([#7884](https://github.com/centreon/centreon/pull/7884)) requires a mirrored test file for command and event handlers, ApiPlatform processors and `DbalRepository` subclasses; the kind of test is checked in review. A repository that implements its interface directly, and the other building blocks, are invisible to CI and are checked in review.

## When to Amend

- The `src/App` layering changes such that the building blocks listed above are no longer the right unit-test boundary.
