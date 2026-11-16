# ADR index

Decisions taken by the PHP team on the backend architecture, by topic. The terms they use are defined in the [glossary](../glossary.md). To write a new record, see the [README](README.md).

## Guides

The guides show how to apply the records; the code examples live there.

| Guide                                   | Records                                              |
| --------------------------------------- | ---------------------------------------------------- |
| [database.md](../database.md)           | 0010, 0027, 0028, 0029, 0030, 0048, 0049, 0050, 0051 |
| [logging.md](../logging.md)             | 0016, 0017, 0020, 0026, 0041, 0055, 0057             |
| [html-escaping.md](../html-escaping.md) | 0022                                                 |
| [vault.md](../vault.md)                 | 0020, 0055                                           |

## Validation

| ADR                                                           | Decision                                                                                     | Scope | Status   |
| ------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ----- | -------- |
| [0001](0001-apip-validate-in-input-and-handler.md)            | Validate write requests in both input and handler                                            | APIP  | Accepted |
| [0021](0021-apip-enforce-invariants-with-webmozart-assert.md) | Enforce invariants with `webmozart/assert`, not hand-rolled assertions and custom exceptions | APIP  | Accepted |

## Domain model

| ADR                                                                 | Decision                                                                                        | Scope | Status   |
| ------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- | ----- | -------- |
| [0009](0009-apip-aggregates-readonly-by-default.md)                 | Make aggregate properties `readonly` by default, and mutate them only through business methods  | APIP  | Accepted |
| [0012](0012-apip-change-aggregate-internals-through-the-root.md)    | Change an aggregate's inner entities and value objects only through its aggregate root          | APIP  | Accepted |
| [0044](0044-apip-back-every-enum-with-a-business-value.md)          | Back every new `src/App` enum with a business value, a string by default                        | APIP  | Accepted |
| [0057](0057-apip-return-a-collection-for-a-set-of-objects.md)       | Return a `Collection` for a set of objects, never an array of objects                           | APIP  | Accepted |
| [0068](0068-apip-keep-the-api-format-out-of-the-domain.md)          | Keep the API format out of the `src/App` Domain                                                 | APIP  | Accepted |
| [0074](0074-apip-read-the-current-time-from-the-clock.md)           | Read the current time in `src/App` from `ClockInterface`, never from `new \DateTimeImmutable()` | APIP  | Accepted |
| [0045](0045-core-back-every-enum-with-a-business-value.md)          | Back every new `src/Core` enum with a business value, a string by default                       | Core  | Accepted |
| [0058](0058-core-return-a-collection-for-a-set-of-objects.md)       | Return a `Collection` for a set of objects in `src/Core`, never an array of objects             | Core  | Accepted |
| [0069](0069-core-keep-the-api-format-out-of-the-src-core-domain.md) | Keep the API format out of the `src/Core` Domain                                                | Core  | Accepted |

## Project structure

| ADR                                                                                             | Decision                                                                                             | Scope  | Status   |
| ----------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | ------ | -------- |
| [0002](0002-apip-name-domain-folder-model.md)                                                   | Name the domain folder `Model`, not `Aggregate`                                                      | APIP   | Accepted |
| [0019](0019-apip-group-per-aggregate-only-domain-model-and-apiplatform.md)                      | Group classes per aggregate only in the Domain model and ApiPlatform layers                          | APIP   | Accepted |
| [0042](0042-apip-suffix-interfaces-except-domain-repositories.md)                               | Suffix every new `src/App` interface with `Interface`, except a repository interface                 | APIP   | Accepted |
| [0053](0053-apip-suffix-every-src-app-enum-with-enum.md)                                        | Suffix every new `src/App` enum with `Enum`                                                          | APIP   | Accepted |
| [0056](0056-apip-keep-only-domain-typed-ports-and-business-rules-in-domain-service.md)          | Keep only domain-typed ports and pure business rules in `Domain/Service`                             | APIP   | Accepted |
| [0059](0059-apip-name-a-fetch-find-when-it-may-be-missing-and-get-when-it-must-exist.md)        | Name a `src/App` fetch `find*` when its result may be missing, and `get*` when it must exist         | APIP   | Accepted |
| [0075](0075-apip-wire-services-with-autowiring-and-attributes.md)                               | Wire `src/App` services with autowiring and attributes, not with service definitions                 | APIP   | Accepted |
| [0043](0043-core-suffix-every-src-core-interface-with-interface.md)                             | Suffix every new `src/Core` interface with `Interface`, repository interfaces included               | Core   | Accepted |
| [0052](0052-core-name-repository-interfaces-read-or-write-and-implementations-after-storage.md) | Name a `src/Core` repository interface `Read*` or `Write*`, and its implementation after its storage | Core   | Accepted |
| [0054](0054-core-suffix-every-src-core-enum-with-enum.md)                                       | Suffix every new `src/Core` enum with `Enum`                                                         | Core   | Accepted |
| [0060](0060-core-name-a-fetch-find-when-it-may-be-missing-and-get-when-it-must-exist.md)        | Name a `src/Core` fetch `find*` when its result may be missing, and `get*` when it must exist        | Core   | Accepted |
| [0061](0061-legacy-name-a-fetch-find-when-it-may-be-missing-and-get-when-it-must-exist.md)      | Name a legacy fetch `find*` when its result may be missing, and `get*` when it must exist            | Legacy | Accepted |

## Transformers

| ADR                                                       | Decision                                                                  | Scope | Status   |
| --------------------------------------------------------- | ------------------------------------------------------------------------- | ----- | -------- |
| [0003](0003-apip-name-transformers-after-their-output.md) | Name a Transformer after the object it produces                           | APIP  | Accepted |
| [0004](0004-apip-transformers-are-dedicated-classes.md)   | Implement Transformers as dedicated classes behind `TransformerInterface` | APIP  | Accepted |
| [0071](0071-apip-keep-transformers-free-of-io.md)         | Keep `src/App` Transformers free of I/O                                   | APIP  | Accepted |

## Legacy boundaries

| ADR                                                                          | Decision                                                                        | Scope  | Status   |
| ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------- | ------ | -------- |
| [0005](0005-apip-isolate-legacy-in-infrastructure-legacy.md)                 | Isolate legacy code used from `src/App` in `Infrastructure/Legacy` adapters     | APIP   | Accepted |
| [0006](0006-legacy-expose-symfony-services-to-legacy-via-service-locator.md) | Give legacy code access to Symfony services through a dedicated service locator | Legacy | Accepted |

## Logging

| ADR                                                                  | Decision                                                                                                 | Scope  | Status   |
| -------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- | ------ | -------- |
| [0055](0055-apip-log-only-from-the-src-app-infrastructure-layer.md)  | Log only from the `src/App` Infrastructure layer, never from the Application, the Domain or a repository | APIP   | Accepted |
| [0016](0016-core-log-src-core-through-adaptation-logger.md)          | Log from `src/Core` only through the `Adaptation\Log` loggers                                            | Core   | Accepted |
| [0057](0057-core-log-only-from-the-src-core-infrastructure-layer.md) | Log only from the `src/Core` Infrastructure layer, never from a use case, the Domain or a repository     | Core   | Accepted |
| [0017](0017-legacy-log-legacy-code-through-adaptation-logger.md)     | Log from legacy code only through the `Adaptation\Log` loggers                                           | Legacy | Accepted |

## Database

| ADR                                                                                     | Decision                                                                                      | Scope            | Status   |
| --------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- | ---------------- | -------- |
| [0010](0010-apip-core-legacy-parameterize-repository-queries.md)                        | Build repository SQL with the `QueryBuilder` and bound parameters, never string concatenation | APIP/Core/Legacy | Accepted |
| [0027](0027-core-reach-the-database-from-src-core-through-connection-interface.md)      | Reach the database from `src/Core` only through `ConnectionInterface` and its builders        | Core             | Accepted |
| [0029](0029-core-make-src-core-repositories-throw-repository-exception.md)              | Make `src/Core` repositories throw a `RepositoryException`, never log or swallow a failure    | Core             | Accepted |
| [0050](0050-core-extend-database-repository-in-every-new-src-core-repository.md)        | Extend `DatabaseRepository` in every new `src/Core` repository                                | Core             | Accepted |
| [0028](0028-legacy-reach-the-database-from-legacy-code-through-connection-interface.md) | Reach the database from legacy code only through `ConnectionInterface` and its builders       | Legacy           | Accepted |
| [0030](0030-legacy-make-legacy-repositories-throw-repository-exception.md)              | Make legacy repositories throw a `RepositoryException`, never log or swallow a failure        | Legacy           | Accepted |
| [0051](0051-legacy-extend-database-repository-in-every-new-legacy-repository.md)        | Extend `DatabaseRepository` in every new legacy repository                                    | Legacy           | Accepted |

## Upgrade

| ADR                                                                       | Decision                                                             | Scope  | Status   |
| ------------------------------------------------------------------------- | -------------------------------------------------------------------- | ------ | -------- |
| [0041](0041-legacy-write-upgrade-scripts-as-logged-re-entrant-actions.md) | Write upgrade scripts as logged, re-entrant actions that fail loudly | Legacy | Accepted |

## API

| ADR                                                         | Decision                                                                                            | Scope | Status   |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------- | ----- | -------- |
| [0007](0007-apip-serve-new-endpoints-under-api-only.md)     | Run legacy and new endpoints side by side, and serve new endpoints under `/api` only                | APIP  | Accepted |
| [0008](0008-apip-one-resource-per-path.md)                  | Keep one API Platform resource as far as possible, and add Input or Output DTOs when formats differ | APIP  | Accepted |
| [0011](0011-apip-keep-selector-endpoints-out-of-api-doc.md) | Keep selector endpoints out of the published API documentation                                      | APIP  | Accepted |

## Messaging

| ADR                                                          | Decision                                                                                  | Scope | Status   |
| ------------------------------------------------------------ | ----------------------------------------------------------------------------------------- | ----- | -------- |
| [0013](0013-apip-messages-carry-serializable-values-only.md) | Make commands, queries and events carry serializable values only                          | APIP  | Accepted |
| [0014](0014-apip-one-aggregate-per-command.md)               | Change one aggregate per command, and let other aggregates react through events           | APIP  | Accepted |
| [0020](0020-apip-let-the-command-bus-own-the-transaction.md) | Let the command bus own the transaction, and keep handlers free of transaction management | APIP  | Accepted |

## Error handling

| ADR | Decision | Status |
|---|---|---|
| [0073](0073-apip-pass-json-throw-on-error-to-every-json-call.md) | Pass `JSON_THROW_ON_ERROR` to every `json_encode()` and `json_decode()` call in `src/App` | Accepted |
| [0074](0074-core-pass-json-throw-on-error-to-every-json-call.md) | Pass `JSON_THROW_ON_ERROR` to every new `json_encode()` and `json_decode()` call in `src/Core` | Accepted |
| [0075](0075-legacy-pass-json-throw-on-error-to-every-json-call.md) | Pass `JSON_THROW_ON_ERROR` to every new `json_encode()` and `json_decode()` call in legacy code | Accepted |

## Security

| ADR                                                                                        | Decision                                                                                     | Scope  | Status   |
| ------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------- | ------ | -------- |
| [0015](0015-apip-scope-resource-access-with-acting-user-and-access-scope.md)               | Scope resource access with an acting user and an access scope                                | APIP   | Accepted |
| [0026](0026-apip-mark-every-secret-sensitive.md)                                           | Mark every secret a command or query carries `#[Sensitive]` so the logging pipeline masks it | APIP   | Accepted |
| [0047](0047-apip-drop-the-driver-exception-when-a-repository-write-of-a-secret-fails.md)   | Drop the driver exception when a repository write of a secret fails                          | APIP   | Accepted |
| [0073](0073-apip-authorize-every-write-operation-through-a-voter.md)                       | Authorize every `src/App` write operation through a voter declared on the operation          | APIP   | Accepted |
| [0048](0048-core-drop-the-driver-exception-when-a-repository-write-of-a-secret-fails.md)   | Drop the driver exception when a `src/Core` repository write of a secret fails               | Core   | Accepted |
| [0022](0022-legacy-escape-at-www-html-output.md)                                           | Escape user-controlled and database-sourced strings at the point of `www/` HTML output       | Legacy | Accepted |
| [0049](0049-legacy-drop-the-driver-exception-when-a-repository-write-of-a-secret-fails.md) | Drop the driver exception when a legacy repository write of a secret fails                   | Legacy | Accepted |

## Error handling

| ADR                                                                        | Decision                                                                                    | Scope  | Status   |
| -------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- | ------ | -------- |
| [0062](0062-apip-never-silence-an-error-with-the-at-operator.md)           | Never silence an error with the `@` operator in `src/App`                                   | APIP   | Accepted |
| [0065](0065-apip-pass-json-throw-on-error-to-every-json-call.md)           | Pass `JSON_THROW_ON_ERROR` to every `json_encode()` and `json_decode()` call in `src/App`   | APIP   | Accepted |
| [0063](0063-core-never-silence-an-error-with-the-at-operator.md)           | Never silence an error with the `@` operator in `src/Core`                                  | Core   | Accepted |
| [0066](0066-core-pass-json-throw-on-error-to-every-json-call.md)           | Pass `JSON_THROW_ON_ERROR` to every `json_encode()` and `json_decode()` call in `src/Core`  | Core   | Accepted |
| [0070](0070-core-build-src-core-exceptions-with-named-static-factories.md) | Build `src/Core` exceptions with named static factories                                     | Core   | Accepted |
| [0064](0064-legacy-never-silence-an-error-with-the-at-operator.md)         | Never silence an error with the `@` operator in legacy code                                 | Legacy | Accepted |
| [0067](0067-legacy-pass-json-throw-on-error-to-every-json-call.md)         | Pass `JSON_THROW_ON_ERROR` to every `json_encode()` and `json_decode()` call in legacy code | Legacy | Accepted |

## Static analysis

| ADR                                                                            | Decision                                                                     | Scope  | Status   |
| ------------------------------------------------------------------------------ | ---------------------------------------------------------------------------- | ------ | -------- |
| [0023](0023-apip-fix-phpstan-findings-in-src-app-never-suppress-them.md)       | Fix PHPStan findings in new and changed `src/App` code, never suppress them  | APIP   | Accepted |
| [0072](0072-apip-suffix-every-phpstan-type-alias-with-type-alias.md)           | Suffix every `src/App` `@phpstan-type` alias with `TypeAlias`                | APIP   | Accepted |
| [0024](0024-core-fix-phpstan-findings-in-src-core-never-suppress-them.md)      | Fix PHPStan findings in new and changed `src/Core` code, never suppress them | Core   | Accepted |
| [0025](0025-legacy-fix-phpstan-findings-in-legacy-code-never-suppress-them.md) | Fix PHPStan findings in new and changed legacy code, never suppress them     | Legacy | Accepted |

## Testing

| ADR                                                                              | Decision                                                                                    | Scope  | Status   |
| -------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- | ------ | -------- |
| [0018](0018-apip-unit-test-every-building-block.md)                              | Unit-test every `src/App` building block, and pin each rule at the lowest level             | APIP   | Accepted |
| [0033](0033-apip-write-src-app-tests-as-phpunit-classes.md)                      | Write `src/App` tests as PHPUnit classes, never Pest                                        | APIP   | Accepted |
| [0036](0036-apip-use-hand-written-fakes-in-src-app-tests.md)                     | Use hand-written fakes in `src/App` tests, and mocks only when a fake is impossible         | APIP   | Accepted |
| [0039](0039-apip-test-both-platforms-in-the-block-test-file.md)                  | Test both platforms of a block in its own test file                                         | APIP   | Accepted |
| [0040](0040-apip-add-bruno-api-tests-only-to-complement-integration-tests.md)    | Add Bruno API tests only to complement the `src/App` integration tests                      | APIP   | Accepted |
| [0046](0046-apip-assert-behaviour-with-phpunit-in-src-app-tests.md)              | Assert behaviour with PHPUnit in `src/App` tests, and use `Assert::*` only to narrow a type | APIP   | Accepted |
| [0031](0031-core-add-a-test-for-every-src-core-change.md)                        | Add a test for every change to `src/Core`, at the lowest level that proves it               | Core   | Accepted |
| [0034](0034-core-write-new-src-core-tests-as-phpunit-classes.md)                 | Write new `src/Core` tests as PHPUnit classes, not Pest                                     | Core   | Accepted |
| [0037](0037-core-use-fakes-in-src-core-tests-and-mocks-only-as-a-last-resort.md) | Use hand-written fakes in `src/Core` tests, and mocks only as a last resort                 | Core   | Accepted |
| [0032](0032-legacy-add-a-test-for-every-legacy-change.md)                        | Add a test for every change to legacy code, at the lowest level that proves it              | Legacy | Accepted |
| [0035](0035-legacy-write-new-legacy-tests-as-phpunit-classes.md)                 | Write new legacy tests as PHPUnit classes, not Pest                                         | Legacy | Accepted |
| [0038](0038-legacy-use-fakes-in-legacy-tests-and-mocks-only-as-a-last-resort.md) | Use hand-written fakes in legacy tests, and mocks only as a last resort                     | Legacy | Accepted |
