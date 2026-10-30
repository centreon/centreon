# Glossary

The team mixes vocabularies from DDD, CQRS, hexagonal architecture and MVC, and the same word means different things to different people. This page holds the team's definitions, agreed in dedicated sessions. Use them when designing, naming and reviewing code, and point to them in reviews instead of debating a term again.

A new term, or a change to a definition, goes through a pull request on this page. Decisions that build on these terms live in the [ADRs](adr/index.md).

## Table of contents

1. [Domain building blocks](#1-domain-building-blocks)
2. [Messaging](#2-messaging)
3. [Ports and adapters](#3-ports-and-adapters)
4. [Common misreadings](#4-common-misreadings)
5. [Not defined yet](#5-not-defined-yet)

---

## 1. Domain building blocks

### Invariant

A business rule that does not change over time, such as "a price is positive". Entities and value objects contain invariants; they are not themselves invariant.

### Entity

An object identified by its ID, whatever the ID's type. Two entities are equal when their IDs are equal. It has a lifecycle (create, modify, delete) and is mutable, but not anemic: it carries behaviour and contains invariants.

### Value object

An object with no identity of its own, identified and compared by all of its values. It may hold several values and compose other value objects. It validates itself and contains invariants. It is immutable: it has no setter, and a change produces a new instance (a wither).

### Aggregate

Not a class. It is the set of entities, value objects and collections that form one consistent, transactional unit, consistent with itself at every moment. It references other aggregates by ID only.

### Aggregate root

The single entity that is the aggregate's entry point, one per aggregate. Changes inside the aggregate go through it, as set by [ADR-0012](adr/0012-change-aggregate-internals-through-the-root.md).

### Read model

A behaviourless data structure built for one use, possibly crossing several aggregates. It holds scalars and value objects only, and is never validated against business rules.

### Output

A read model dedicated to API Platform.

The placement and shape of a class follow from its definition: a `final readonly`, self-validating class compared by value is a value object; an identified, mutable class with business methods is an entity. Where each one lives is set by [ADR-0002](adr/0002-name-domain-folder-model.md), and how aggregate properties are mutated by [ADR-0009](adr/0009-aggregates-readonly-by-default.md).

---

## 2. Messaging

### CQS and CQRS

CQS separates reads and writes at method level; CQRS does it with classes. The team does CQRS at level zero: separate query and command classes. Splitting repositories or storage is not decided.

### Query

A read-only message that fetches data and never changes the system. It is not tied to a transport: HTTP GET, CLI or, as an exception, POST. It has **exactly one handler**.

### Command

A message expressing an intent to change the system, from a user, the CLI or automation. It is a use case, **named with a verb**. It runs in **one transaction**: on failure, nothing changed. It may read data in order to write. It has **exactly one handler**. **It may return data** (affected rows, business information): a deliberate deviation from CQS, so reviews do not reject a handler that returns data on CQS grounds.

### Event

A message signalling that something happened, **named in the past tense** (`HostCreated`). The emitter does not know who listens: **0 to n handlers**, optionally ordered by priority. Commands and queries are not events.

### Bus

The channel that routes a message to its handlers and hosts generic behaviour through middleware (transaction, logging). **One bus per message type**: command, query, event. The bus configuration enforces the handler count and chooses the transport, synchronous or asynchronous. A second handler on a command or a query is a configuration error, not a design option.

What a message may carry is set by [ADR-0013](adr/0013-messages-carry-serializable-values-only.md), and how many aggregates a command may change by [ADR-0014](adr/0014-one-aggregate-per-command.md).

---

## 3. Ports and adapters

### Port

An interface in the domain or application layer, expressed in business language.

### Adapter

A class implementing one or more ports, usually in the infrastructure layer. It converts between business types (value objects) and the technology behind it.

When a port has several adapters, the chosen one is wired explicitly in the Symfony container, which fails to compile otherwise. Test adapters rely on the same mechanism. One adapter implementing several ports is allowed, for example a repository implementing a read and a write interface, but naming gets harder: one port per adapter is preferred when practical.

### Adaptation layer

The `src/Adaptation` namespace, a set of ports and adapters that wrap cross-cutting technical concerns — logging (`Adaptation\Log`) and the database connection (`Adaptation\Database`) — behind clean interfaces. It is the bridge both the modern stack and legacy code share for these concerns. New code outside `src/App` reaches logging and the database only through it, as set by [ADR-0016](adr/0016-access-logging-and-database-through-adaptation-layer.md).

---

## 4. Common misreadings

The sessions that produced these definitions corrected the following readings:

| Misreading | Definition |
|---|---|
| "Invariant" means "immutable". | [Invariant](#invariant) |
| A value object is "an entity without an ID, holding no invariants". | [Value object](#value-object) |
| "Aggregate" and "aggregate root" are synonyms. | [Aggregate](#aggregate), [Aggregate root](#aggregate-root) |
| A query is "idempotent", or "a GET". | [Query](#query) |
| A command "returns nothing", as CQS has it. | [Command](#command) |
| A command can have several handlers, since commands, queries and events all go through buses. | [Bus](#bus) |

---

## 5. Not defined yet

Bounded context, DDD, hexagonal architecture.
