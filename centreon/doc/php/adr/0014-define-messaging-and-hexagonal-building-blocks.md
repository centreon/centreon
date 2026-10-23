# ADR-0014: Adopt the team's definitions of query, command, event, bus, port and adapter

- **Status:** Accepted
- **Date:** 2026-10-21

## Context

[ADR-0013](0013-adopt-the-team-building-block-vocabulary.md) defined the domain building blocks and left command, query, event, bus, ports and adapters for a later session. A second session defined them the same way, and surfaced several misreadings:

- a query described as "idempotent" and as "a GET";
- a command described as "returning nothing", as CQS has it, while the team's handlers do return data;
- the doubt whether a command can have several handlers, since commands, queries and events all go through buses.

This ADR extends ADR-0013; it does not change it.

## Decision

Use these definitions when designing, naming and reviewing code:

- **CQS and CQRS:** CQS separates reads and writes at method level; CQRS does it with classes. The team does CQRS at level zero: separate query and command classes. Splitting repositories or storage is not decided.
- **Query:** a read-only message that fetches data and never changes the system. It is not tied to a transport: HTTP GET, CLI or, as an exception, POST. It has **exactly one handler**.
- **Command:** a message expressing an intent to change the system, from a user, the CLI or automation. It is a use case, **named with a verb**. It runs in **one transaction**: on failure, nothing changed. It may read data in order to write. It has **exactly one handler**. **It may return data** (affected rows, business information): a deliberate deviation from CQS.
- **Event:** a message signalling that something happened, **named in the past tense** (`HostCreated`). The emitter does not know who listens: **0 to n handlers**, optionally ordered by priority. Commands and queries are not events.
- **Bus:** the channel that routes a message to its handlers and hosts generic behaviour through middleware (transaction, logging). **One bus per message type**: command, query, event. The bus configuration enforces the handler count and chooses the transport, synchronous or asynchronous.
- **Port:** an interface in the domain or application layer, expressed in business language.
- **Adapter:** a class implementing one or more ports, usually in the infrastructure layer. It converts between business types (value objects) and the technology behind it.

## Consequences

- Commands are named with a verb, events in the past tense.
- A second handler on a command or a query is a configuration error, not a design option.
- A handler that returns data is acceptable. Reviews do not reject it on CQS grounds.
- When a port has several adapters, the chosen one is wired explicitly in the Symfony container, which fails to compile otherwise. Test adapters rely on the same mechanism.
- One adapter implementing several ports is allowed, for example a repository implementing a read and a write interface, but naming gets harder. One port per adapter is preferred when practical.
- Not covered yet: bounded context, DDD, hexagonal architecture.

## When to Amend

- The team moves to a further CQRS level: split read and write repositories, or split storage.
- The team adopts event sourcing.
- The remaining terms are defined. They are added in a new ADR rather than by editing this one.
