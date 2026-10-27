# ADR-0009: Make aggregate properties `readonly` by default, and mutate them only through business methods

- **Status:** Accepted
- **Date:** 2026-07-22

## Context

Some aggregates already have `readonly` properties because they have no setters. The question is whether to make that the rule, and how an edit use case changes state.

## Decision

Declare aggregate properties **`readonly`** by default, except the `AggregateRootId`. When an **edit use case** needs to change a property, remove `readonly` from that property and add a **business method** that performs the change, such as `rename()` or `disable()`. **Never add a generic setter.**

## Consequences

- Uncontrolled mutations are prevented. Every state change goes through a named business operation that can enforce its invariants.
- A property that is not `readonly` is a signal: some use case mutates it.
- Changes to an aggregate's inner entities and value objects also go through the aggregate root, as set by [ADR-0012](0012-change-aggregate-internals-through-the-root.md).
- The rule is not enforced by tooling.

## When to Amend

- The semantics of `readonly` in PHP change, for example with clone-with.
- The team adopts a different mutation model, such as event-sourced aggregates or withers returning new instances.
