# ADR index

Decisions taken by the PHP team on the backend architecture, by topic. The terms they use are defined in the [glossary](../glossary.md). To write a new record, see the [README](README.md).

## Guides

The guides show how to apply the records; the code examples live there.

| Guide | Records |
|---|---|
| [database.md](../database.md) | 0027, 0028, 0029, 0030 |
| [logging.md](../logging.md) | 0016, 0017, 0026, 0041 |
| [html-escaping.md](../html-escaping.md) | 0022 |
| [vault.md](../vault.md) | 0020 |

## Validation

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0001](0001-apip-validate-in-input-and-handler.md) | Validate write requests in both input and handler | APIP | Accepted |
| [0021](0021-apip-enforce-invariants-with-webmozart-assert.md) | Enforce invariants with `webmozart/assert`, not hand-rolled assertions and custom exceptions | APIP | Accepted |

## Domain model

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0009](0009-apip-aggregates-readonly-by-default.md) | Make aggregate properties `readonly` by default, and mutate them only through business methods | APIP | Accepted |
| [0012](0012-apip-change-aggregate-internals-through-the-root.md) | Change an aggregate's inner entities and value objects only through its aggregate root | APIP | Accepted |

## Project structure

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0002](0002-apip-name-domain-folder-model.md) | Name the domain folder `Model`, not `Aggregate` | APIP | Accepted |
| [0019](0019-apip-group-per-aggregate-only-domain-model-and-apiplatform.md) | Group classes per aggregate only in the Domain model and ApiPlatform layers | APIP | Accepted |
| [0042](0042-apip-suffix-interfaces-except-domain-repositories.md) | Suffix every new `src/App` interface with `Interface`, except a repository interface | APIP | Accepted |
| [0043](0043-core-suffix-every-src-core-interface-with-interface.md) | Suffix every new `src/Core` interface with `Interface`, repository interfaces included | Core | Accepted |

## Transformers

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0003](0003-apip-name-transformers-after-their-output.md) | Name a Transformer after the object it produces | APIP | Accepted |
| [0004](0004-apip-transformers-are-dedicated-classes.md) | Implement Transformers as dedicated classes behind `TransformerInterface` | APIP | Accepted |

## Legacy boundaries

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0005](0005-apip-isolate-legacy-in-infrastructure-legacy.md) | Isolate legacy code used from `src/App` in `Infrastructure/Legacy` adapters | APIP | Accepted |
| [0006](0006-legacy-expose-symfony-services-to-legacy-via-service-locator.md) | Give legacy code access to Symfony services through a dedicated service locator | Legacy | Accepted |

## Logging

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0016](0016-core-log-src-core-through-adaptation-logger.md) | Log from `src/Core` only through `Adaptation\Log\Logger` | Core | Accepted |
| [0017](0017-legacy-log-legacy-code-through-adaptation-logger.md) | Log from legacy code only through `Adaptation\Log\Logger` | Legacy | Accepted |

## Database

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0010](0010-apip-core-legacy-parameterize-repository-queries.md) | Build repository SQL with the `QueryBuilder` and bound parameters, never string concatenation | APIP/Core/Legacy | Accepted |
| [0027](0027-core-reach-the-database-from-src-core-through-connection-interface.md) | Reach the database from `src/Core` only through `ConnectionInterface` and its builders | Core | Accepted |
| [0029](0029-core-make-src-core-repositories-throw-repository-exception.md) | Make `src/Core` repositories throw a `RepositoryException`, never log or swallow a failure | Core | Accepted |
| [0028](0028-legacy-reach-the-database-from-legacy-code-through-connection-interface.md) | Reach the database from legacy code only through `ConnectionInterface` and its builders | Legacy | Accepted |
| [0030](0030-legacy-make-legacy-repositories-throw-repository-exception.md) | Make legacy repositories throw a `RepositoryException`, never log or swallow a failure | Legacy | Accepted |

## Upgrade

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0041](0041-legacy-write-upgrade-scripts-as-logged-re-entrant-actions.md) | Write upgrade scripts as logged, re-entrant actions that fail loudly | Legacy | Accepted |

## API

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0007](0007-apip-serve-new-endpoints-under-api-only.md) | Run legacy and new endpoints side by side, and serve new endpoints under `/api` only | APIP | Accepted |
| [0008](0008-apip-one-resource-per-path.md) | Keep one API Platform resource as far as possible, and add Input or Output DTOs when formats differ | APIP | Accepted |
| [0011](0011-apip-keep-selector-endpoints-out-of-api-doc.md) | Keep selector endpoints out of the published API documentation | APIP | Accepted |

## Messaging

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0013](0013-apip-messages-carry-serializable-values-only.md) | Make commands, queries and events carry serializable values only | APIP | Accepted |
| [0014](0014-apip-one-aggregate-per-command.md) | Change one aggregate per command, and let other aggregates react through events | APIP | Accepted |
| [0020](0020-apip-let-the-command-bus-own-the-transaction.md) | Let the command bus own the transaction, and keep handlers free of transaction management | APIP | Accepted |

## Security

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0015](0015-apip-scope-resource-access-with-acting-user-and-access-scope.md) | Scope resource access with an acting user and an access scope | APIP | Accepted |
| [0026](0026-apip-mark-every-secret-sensitive.md) | Mark every secret `#[Sensitive]` so the logging pipeline masks it | APIP | Accepted |
| [0022](0022-legacy-escape-at-www-html-output.md) | Escape user-controlled and database-sourced strings at the point of `www/` HTML output | Legacy | Accepted |

## Static analysis

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0023](0023-apip-fix-phpstan-findings-in-src-app-never-suppress-them.md) | Fix PHPStan findings in new and changed `src/App` code, never suppress them | APIP | Accepted |
| [0024](0024-core-fix-phpstan-findings-in-src-core-never-suppress-them.md) | Fix PHPStan findings in new and changed `src/Core` code, never suppress them | Core | Accepted |
| [0025](0025-legacy-fix-phpstan-findings-in-legacy-code-never-suppress-them.md) | Fix PHPStan findings in new and changed legacy code, never suppress them | Legacy | Accepted |

## Testing

| ADR | Decision | Scope | Status |
|---|---|---|---|
| [0018](0018-apip-unit-test-every-building-block.md) | Unit-test every `src/App` building block, and pin each rule at the lowest level | APIP | Accepted |
| [0033](0033-apip-write-src-app-tests-as-phpunit-classes.md) | Write `src/App` tests as PHPUnit classes, never Pest | APIP | Accepted |
| [0036](0036-apip-use-hand-written-fakes-in-src-app-tests.md) | Use hand-written fakes in `src/App` tests, not mocks | APIP | Accepted |
| [0039](0039-apip-test-both-platforms-in-the-endpoint-test-file.md) | Test both platforms of an endpoint in its single test file | APIP | Accepted |
| [0040](0040-apip-add-bruno-api-tests-only-to-complement-integration-tests.md) | Add Bruno API tests only to complement the `src/App` integration tests | APIP | Accepted |
| [0031](0031-core-add-a-test-for-every-src-core-change.md) | Add a test for every change to `src/Core`, at the lowest level that proves it | Core | Accepted |
| [0034](0034-core-write-new-src-core-tests-as-phpunit-classes.md) | Write new `src/Core` tests as PHPUnit classes, not Pest | Core | Accepted |
| [0037](0037-core-use-fakes-in-src-core-tests-and-mocks-only-as-a-last-resort.md) | Use hand-written fakes in `src/Core` tests, and mocks only as a last resort | Core | Accepted |
| [0032](0032-legacy-add-a-test-for-every-legacy-change.md) | Add a test for every change to legacy code, at the lowest level that proves it | Legacy | Accepted |
| [0035](0035-legacy-write-new-legacy-tests-as-phpunit-classes.md) | Write new legacy tests as PHPUnit classes, not Pest | Legacy | Accepted |
| [0038](0038-legacy-use-fakes-in-legacy-tests-and-mocks-only-as-a-last-resort.md) | Use hand-written fakes in legacy tests, and mocks only as a last resort | Legacy | Accepted |
