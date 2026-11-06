# Glossary

The team mixes vocabularies from DDD, CQRS, hexagonal architecture and MVC, and the same word means different things to different people. This page holds the team's definitions, agreed in dedicated sessions. Use them when designing, naming and reviewing code, and point to them in reviews instead of debating a term again.

A new term, or a change to a definition, goes through a pull request on this page. Decisions that build on these terms live in the [ADRs](adr/index.md).

## Table of contents

1. [Domain building blocks](#1-domain-building-blocks)
2. [Messaging](#2-messaging)
3. [Ports and adapters](#3-ports-and-adapters)
4. [Security](#4-security)
5. [Code scopes](#5-code-scopes)
6. [Testing](#6-testing)
7. [Common misreadings](#7-common-misreadings)
8. [Not defined yet](#8-not-defined-yet)

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

The single entity that is the aggregate's entry point, one per aggregate. Changes inside the aggregate go through it, as set by [ADR-0012](adr/0012-apip-change-aggregate-internals-through-the-root.md).

### Read model

A behaviourless data structure built for one use, possibly crossing several aggregates. It holds scalars and value objects only, and is never validated against business rules.

### Output

A read model dedicated to API Platform.

The placement and shape of a class follow from its definition: a `final readonly`, self-validating class compared by value is a value object; an identified, mutable class with business methods is an entity. Where each one lives is set by [ADR-0002](adr/0002-apip-name-domain-folder-model.md), and how aggregate properties are mutated by [ADR-0009](adr/0009-apip-aggregates-readonly-by-default.md).

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

What a message may carry is set by [ADR-0013](adr/0013-apip-messages-carry-serializable-values-only.md), and how many aggregates a command may change by [ADR-0014](adr/0014-apip-one-aggregate-per-command.md).

---

## 3. Ports and adapters

### Port

An interface in the domain or application layer, expressed in business language.

### Adapter

A class implementing one or more ports, usually in the infrastructure layer. It converts between business types (value objects) and the technology behind it.

When a port has several adapters, the chosen one is wired explicitly in the Symfony container, which fails to compile otherwise. Test adapters rely on the same mechanism. One adapter implementing several ports is allowed, for example a repository implementing a read and a write interface, but naming gets harder: one port per adapter is preferred when practical.

### Adaptation layer

The `src/Adaptation` namespace: the logging (`Adaptation\Log`) and database (`Adaptation\Database`) wrappers used by code outside `src/App`. `src/App` reaches it only through an `Infrastructure/Legacy/` adapter ([ADR-0005](adr/0005-apip-isolate-legacy-in-infrastructure-legacy.md)); deptrac's `AdaptationLog` allowance ([#11313](https://github.com/centreon/centreon/pull/11313)) is a historical exception. Its use is set by [ADR-0016](adr/0016-core-log-src-core-through-adaptation-logger.md), [ADR-0017](adr/0017-legacy-log-legacy-code-through-adaptation-logger.md), [ADR-0027](adr/0027-core-reach-the-database-from-src-core-through-connection-interface.md) and [ADR-0028](adr/0028-legacy-reach-the-database-from-legacy-code-through-connection-interface.md).

---

## 4. Security

### Secret

A value that grants access: a password, a token, a key, a client secret, a session ID, an SNMP community or passphrase, a password macro's value, or a URL that embeds credentials. A vault path is not a secret.

### Keyword net

The name-based masking of the logging pipeline: a field whose name contains a keyword of `SensitiveKeywordDenylist` (`password`, `token`, `secret`, …) is logged as `***`. It masks message payloads and the context of a direct log call, but misses a secret carried by an unlisted name.

How a secret is kept out of the logs is set by [ADR-0026](adr/0026-apip-mark-every-secret-sensitive.md).

---

## 5. Code scopes

### Scope

The part of the codebase a rule applies to:

- **APIP:** `src/App`, modules included (such as `centreon-bam/src/App`), and `config.new`, except `src/App/Kernel.php`.
- **Core:** `src/Core`, modules included.
- **Legacy:** everything else, including `config/` with its kernel `src/App/Kernel.php`, and `src/Adaptation`, shared with `src/Core` and removed last.

Every [ADR](adr/index.md) states its scope, so a scope's records can be dropped with its code. A record before ADR-0016 may list several scopes.

### Bounded context

A business area of `src/App` with its own folder (`src/App/<Context>`, such as `MonitoringConfiguration` or `Upgrade`), its own model and its own language. A bounded context does not use another one's classes. `Shared` is not a bounded context: every context may use it. `src/Core` is split into contexts the same way (`src/Core/<Context>`), without this isolation rule.

### Touched code

The code a pull request adds or changes: a new file, class, method or function, or an existing line it modifies. Unless a record says otherwise, a rule applies to touched code, and the rest of the existing code follows it when it is next touched.

---

## 6. Testing

### Building block

One of the kinds of class `src/App` is built from: value object, entity, aggregate root, enum, criteria, transformer, input or output DTO, service, factory, handler, processor, provider, repository, security voter, constraint validator. Which ones get their own unit test, and how the others are tested, is set by [ADR-0018](adr/0018-apip-unit-test-every-building-block.md).

### Platform

The deployment a Centreon instance runs on: **Cloud** or **on-premise**. The code reads it from the `IS_CLOUD_PLATFORM` environment variable, and some endpoints behave differently on each.

### Test double

An object that replaces a dependency in a test. A **fake** is a hand-written class that implements the dependency's interface with working, in-memory behaviour; the test asserts the resulting state. A **mock** is generated by PHPUnit's `createMock()` and asserts the calls made. Which one a test uses is set per scope by [ADR-0036](adr/0036-apip-use-hand-written-fakes-in-src-app-tests.md), [ADR-0037](adr/0037-core-use-fakes-in-src-core-tests-and-mocks-only-as-a-last-resort.md) and [ADR-0038](adr/0038-legacy-use-fakes-in-legacy-tests-and-mocks-only-as-a-last-resort.md).

---

## 7. Common misreadings

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

## 8. Not defined yet

DDD, hexagonal architecture.
