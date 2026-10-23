# ADR-0012: Change an aggregate's inner entities and value objects only through its aggregate root

- **Status:** Accepted
- **Date:** 2026-10-15

## Context

The team separates the aggregate, the set of entities, value objects and collections that form one consistent unit, from the aggregate root, the single entity that is its entry point. The open question is how code outside the aggregate changes something inside it. Take the size of a car's wheels, when the wheel is an entity of the car aggregate: is it changed through the wheel or through the car? And with setters, public properties or withers?

Changing an inner entity directly bypasses the rules that keep the whole aggregate consistent: changing the wheels may also require redoing the alignment. An account makes the same point: the balance is not changed directly, the aggregate is asked to withdraw, and it checks solvency.

## Decision

Let code outside an aggregate **read** its inner entities and value objects, but make **every change go through a business method of the aggregate root**, such as `changeWheels()` or `withdraw()`, never through the inner entity or a generic setter. Inside that method, the root **replaces a value object** with a new instance, because value objects are immutable, or **calls the inner entity's own business method** to mutate it.

Make every root method leave the **whole aggregate consistent**: before and after each call, the aggregate satisfies all its invariants. Give each aggregate **exactly one aggregate root**. Put business logic that combines the aggregate's own data, such as formatting two of its values together, in a **method of the aggregate**, not in a separate formatter service.

## Consequences

- Inner entities expose no public mutator that code outside the aggregate could use to bypass the root.
- Each change is a named business operation on the root, consistent with [ADR-0009](0009-aggregates-readonly-by-default.md).
- Roots can grow methods that only delegate. That is the price of a single, enforceable entry point.
- The terms used here are defined in [ADR-0013](0013-adopt-the-team-building-block-vocabulary.md).

## When to Amend

- PHP gains visibility features, such as package-private or friend classes, that change how encapsulation is enforced.
- The team moves to event-sourced aggregates, where state changes are applied differently.
