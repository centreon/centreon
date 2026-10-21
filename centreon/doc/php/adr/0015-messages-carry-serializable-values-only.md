# ADR-0015: Make commands, queries and events carry serializable values only

- **Status:** Accepted
- **Date:** 2026-10-21

## Context

Commands, queries and events go through Symfony Messenger buses. Today the transport is synchronous and in memory, but the bus configuration can switch a message to an asynchronous transport (RabbitMQ, Redis, Doctrine) without changing code. That only holds if the message can be serialized and deserialized without loss. An asynchronous message may also be consumed later, after the data it describes has changed, for example a host renamed in the meantime.

One alternative was weighed: allow scalars only in messages. It lost because it would wrongly exclude ID value objects.

## Decision

Make commands, queries and events carry **only values that serialize and deserialize faithfully**: scalars, arrays of them, and value objects, ID value objects included. **Never put an aggregate, an entity or another model instance in a message.** Pass the ID; the handler loads what it needs.

## Consequences

- Moving a message to an asynchronous transport needs no code change in the message or its handler.
- Handlers always work on current data, not on a snapshot taken when the message was dispatched.
- A handler may need an extra repository call to load what the caller already had in memory.
- The terms are defined in [ADR-0014](0014-define-messaging-and-hexagonal-building-blocks.md).

## When to Amend

- The team adopts a message format or serializer that round-trips full object graphs safely.
- The team decides that some message types stay synchronous for good.
