# ADR-0004: Implement Transformers as dedicated classes behind `TransformerInterface`

- **Status:** Accepted
- **Date:** 2026-09-03

## Context

A Dbal Transformer hydrates domain objects from the array returned by the database. It acts as a private class of its repository, and would disappear if an ORM were used. That raises two questions: should it just be a private method of the repository, and is `TransformerInterface` worth keeping?

Two alternatives were weighed:

- Make the Transformer a private method of the repository. It lost because a Transformer can then neither be reused by other Transformers nor be unit-tested on its own.
- Rename the pattern to Hydrator or Mapper. It lost because a Transformer covers more than hydration: it also converts between a resource and a domain object.

## Decision

Implement each Transformer as a **dedicated class**, never as a private repository method. Make every Transformer implement **`TransformerInterface`**, with a single `transform()` entry point. Keep the name **Transformer**.

## Consequences

- A Transformer can depend on other Transformers to build a full object graph.
- The interface unifies the entry point, and each class can be unit-tested on its own.
- Transformers stay pure: no I/O, no repository or connection injected.
- A `reverse()` capability is agreed as useful, but the mechanism is not settled: an abstract base class with a default `reverse()` that throws, or a second interface. It is decided when it is first needed. Until then, no Transformer is required to implement `reverse()`.

## When to Amend

- `reverse()` is first needed. This ADR then gets a follow-up for the mechanism.
- An ORM replaces the hand-written Dbal hydration.
