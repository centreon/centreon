# ADR-0013: Make commands, queries and events carry serializable values only

- **Status:** Accepted
- **Date:** 2026-10-21

## Context

Commands, queries and events go through Symfony Messenger buses. Today the transport is synchronous and in memory, but the bus configuration can switch a message to an asynchronous transport (RabbitMQ, Redis, Doctrine) without changing code. That only holds if the message can be serialized and deserialized without loss. An asynchronous message may also be consumed later, after the data it describes has changed, for example a host renamed in the meantime.

One alternative was weighed: allow scalars only in messages. It lost because it would wrongly exclude ID value objects.

## Decision

Make commands, queries and events carry **only values that serialize and deserialize faithfully**: scalars, arrays or collections, and value objects, ID value objects included. **Never put an aggregate or an entity in a message.** Pass the ID; the handler loads what it needs.

## Consequences

- Moving a message that complies with this ADR to an asynchronous transport needs no code change in the message or its handler.
- Existing messages that carry model instances must be migrated before asynchronous routing.
- Handlers work on current data for whatever they load from an ID. The other values in the message are a snapshot taken when it was dispatched.
- A handler may need an extra repository call to load what the caller already had in memory.
- The terms are defined in the [glossary](../glossary.md#2-messaging).

## When to Amend

- The team adopts a message format or serializer that round-trips full object graphs safely.
- The team decides that some message types stay synchronous for good.
