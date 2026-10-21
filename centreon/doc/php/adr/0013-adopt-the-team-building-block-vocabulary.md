# ADR-0013: Adopt the team's definitions of the domain building blocks

- **Status:** Accepted
- **Date:** 2026-10-15

## Context

The team mixes vocabularies from DDD, CQRS, hexagonal architecture and MVC, and the same word means different things to different people. A dedicated session went through each term and corrected the definitions together. It surfaced several misreadings:

- "invariant" read as "immutable";
- a value object described as "an entity without an ID, holding no invariants";
- "aggregate" and "aggregate root" used as synonyms.

Writing the outcome down lets developers and AI assistants classify classes the same way.

## Decision

Use these definitions when designing, naming and reviewing domain code:

- **Invariant:** a business rule that does not change over time, such as "a price is positive". Entities and value objects contain invariants; they are not themselves invariant.
- **Entity:** an object identified by its ID, whatever the ID's type. Two entities are equal when their IDs are equal. It has a lifecycle (create, modify, delete) and is mutable, but not anemic: it carries behaviour and contains invariants.
- **Value object:** an object with no identity of its own, identified and compared by all of its values. It may hold several values and compose other value objects. It validates itself and contains invariants. It is immutable: it has no setter, and a change produces a new instance (a wither).
- **Aggregate:** not a class. It is the set of entities, value objects and collections that form one consistent, transactional unit, consistent with itself at every moment. It references other aggregates by ID only.
- **Aggregate root:** the single entity that is the aggregate's entry point, one per aggregate. Changes inside the aggregate go through it, as set by [ADR-0012](0012-change-aggregate-internals-through-the-root.md).
- **Read model:** a behaviourless data structure built for one use, possibly crossing several aggregates. It holds scalars and value objects only, and is never validated against business rules.
- **Output:** a read model dedicated to API Platform.

## Consequences

- The placement and shape of a class follow from its definition: a `final readonly`, self-validating class compared by value is a value object; an identified, mutable class with business methods is an entity. Where each one lives is set by [ADR-0002](0002-name-domain-folder-model.md), and how aggregate properties are mutated by [ADR-0009](0009-aggregates-readonly-by-default.md).
- Reviews can point to a term's definition instead of debating it again.
- Not covered yet: command, query, event, bus, bounded context, DDD, hexagonal architecture, ports and adapters.

## When to Amend

- The remaining terms are defined. They are added in a new ADR rather than by editing this one.
- A definition here contradicts a later decision on read-model placement or on CQRS.
