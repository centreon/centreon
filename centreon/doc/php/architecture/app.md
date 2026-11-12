# `src/App` architecture

How the target architecture fits together. This is a guide, not a rule book:
the rules are the [ADRs](../adr/index.md) and the terms are in the
[glossary](../glossary.md). If this guide and an ADR disagree, the ADR wins and
this guide is fixed.

## Two kernels

`centreon/` runs two Symfony kernels side by side
([ADR-0007](../adr/0007-serve-new-endpoints-under-api-only.md)):

|            | New kernel                                 | Legacy kernel                                 |
| ---------- | ------------------------------------------ | --------------------------------------------- |
| Class      | `App\Shared\Infrastructure\Symfony\Kernel` | `App\Kernel` (`src/App/Kernel.php`)           |
| Config     | `config.new/`                              | `config/`                                     |
| Console    | `bin/console.new`                          | `bin/console`                                 |
| HTTP entry | `api/index.php`, endpoints under `/api`    | `www/api/index.php`, endpoints under `/api/latest` |

`src/App` is wired in the new kernel: one services file per bounded context in
`config.new/services/`. Despite its path, `src/App/Kernel.php` is the legacy
kernel.

## Bounded contexts

One folder per bounded context under `src/App/`, each split into `Domain/`,
`Application/` and `Infrastructure/`. `Shared/` is the shared kernel (buses,
`Collection`, base events, DBAL helpers, legacy wrappers).

Deptrac checks both boundaries (`composer dep:new`):

- `deptrac_bc.yaml`: a context imports only itself and `Shared`.
- `deptrac_hexa.yaml`: `Domain` depends only on a few helper libraries (PSR,
  some Symfony attributes), `Application` also on `Domain`, `Infrastructure`
  on both and on vendors. Each allowed call into legacy code
  is listed in its `skip_violations`.

## Layers

- **Domain**: aggregates, entities and value objects in `Domain/Model`
  ([ADR-0002](../adr/0002-name-domain-folder-model.md),
  [0009](../adr/0009-aggregates-readonly-by-default.md),
  [0012](../adr/0012-change-aggregate-internals-through-the-root.md)),
  repository interfaces in `Domain/Repository`, events in `Domain/Event`.
- **Application**: commands and their handlers, event handlers
  ([0001](../adr/0001-validate-in-input-and-handler.md),
  [0013](../adr/0013-messages-carry-serializable-values-only.md),
  [0014](../adr/0014-one-aggregate-per-command.md)).
- **Infrastructure**: API Platform resources, state providers and processors in
  `Infrastructure/ApiPlatform`
  ([0008](../adr/0008-one-resource-per-path.md),
  [0011](../adr/0011-keep-selector-endpoints-out-of-api-doc.md)), DBAL
  repositories and transformers in `Infrastructure/Dbal`
  ([0003](../adr/0003-name-transformers-after-their-output.md),
  [0004](../adr/0004-transformers-are-dedicated-classes.md),
  [0010](../adr/0010-parameterize-repository-queries.md)), adapters to legacy
  code in `Infrastructure/Legacy`
  ([0005](../adr/0005-isolate-legacy-in-infrastructure-legacy.md)), voters
  ([0015](../adr/0015-scope-resource-access-with-acting-user-and-access-scope.md)).

## Request flow

- **Write**: an API Platform processor dispatches a command on `command.bus`.
  Its middlewares validate the command, log, and wrap the handler in one
  database transaction (`DoctrineTransactionMiddleware`). The handler loads
  the aggregate, calls its business methods, saves it, and fires an event on
  the `EventBus`. Event handlers react (activity log, other aggregates). An
  event implementing `DeliveredAfterCommitInterface` reaches its handlers after
  the commit.
- **Read**: an API Platform provider reads through repositories.

Bus configuration: `config.new/packages/messenger.yaml`.

## Tests

`tests/php/App/` mirrors `src/App/`. `composer test:new` runs the unit tests and
skips the `*/Infrastructure` folders, which need a database: run them with
`composer test:new:integration`.

## Known gaps

- Some contexts still have a `Domain/Aggregate` folder that predates ADR-0002.
  New code goes in `Domain/Model`.
- Do not bring `src/Core` patterns into `src/App`: no use case and presenter
  classes, no `Read*`/`Write*` repository interfaces in `Application/`.
