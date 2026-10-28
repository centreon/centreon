# Architecture Decision Records

Decisions taken by the PHP team on the backend architecture. Each record states the context, the decision, its consequences and the facts that would reopen it. The terms they use are defined in the [glossary](../glossary.md).

To propose a decision, copy [`template.md`](template.md) to the next free number and open a pull request. Supersede a record with a new one rather than rewriting it.

## By topic

### Validation

| ADR | Decision | Status |
|---|---|---|
| [0001](0001-validate-in-input-and-handler.md) | Validate write requests in both input and handler | Accepted |

### Domain model

| ADR | Decision | Status |
|---|---|---|
| [0002](0002-name-domain-folder-model.md) | Name the domain folder `Model`, not `Aggregate` | Accepted |
| [0009](0009-aggregates-readonly-by-default.md) | Make aggregate properties `readonly` by default, and mutate them only through business methods | Accepted |
| [0012](0012-change-aggregate-internals-through-the-root.md) | Change an aggregate's inner entities and value objects only through its aggregate root | Accepted |

### Transformers

| ADR | Decision | Status |
|---|---|---|
| [0003](0003-name-transformers-after-their-output.md) | Name a Transformer after the object it produces | Accepted |
| [0004](0004-transformers-are-dedicated-classes.md) | Implement Transformers as dedicated classes behind `TransformerInterface` | Accepted |

### Legacy boundaries

| ADR | Decision | Status |
|---|---|---|
| [0005](0005-isolate-legacy-in-infrastructure-legacy.md) | Isolate legacy code used from `src/App` in `Infrastructure/Legacy` adapters | Accepted |
| [0006](0006-expose-symfony-services-to-legacy-via-service-locator.md) | Give legacy code access to Symfony services through a dedicated service locator | Accepted |
| [0016](0016-access-logging-and-database-through-adaptation-layer.md) | Access logging and the database from new legacy code only through the `Adaptation` layer | Proposed |

### API

| ADR | Decision | Status |
|---|---|---|
| [0007](0007-serve-new-endpoints-under-api-only.md) | Run legacy and new endpoints side by side, and serve new endpoints under `/api` only | Accepted |
| [0008](0008-one-resource-per-path.md) | Keep one API Platform resource as far as possible, and add Input or Output DTOs when formats differ | Accepted |
| [0011](0011-keep-selector-endpoints-out-of-api-doc.md) | Keep selector endpoints out of the published API documentation | Accepted |

### Messaging

| ADR | Decision | Status |
|---|---|---|
| [0013](0013-messages-carry-serializable-values-only.md) | Make commands, queries and events carry serializable values only | Accepted |
| [0014](0014-one-aggregate-per-command.md) | Change one aggregate per command, and let other aggregates react through events | Accepted |

### Security

| ADR | Decision | Status |
|---|---|---|
| [0010](0010-parameterize-repository-queries.md) | Build repository SQL with the `QueryBuilder` and bound parameters, never string concatenation | Accepted |
| [0015](0015-scope-resource-access-with-acting-user-and-access-scope.md) | Scope resource access with an acting user and an access scope | Accepted |
