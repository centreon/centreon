# `src/App` architecture

How the target architecture fits together. This is a guide, not a rule book:
the rules are the ADRs. If this guide and an ADR disagree, the ADR wins and this
guide is fixed.

## Two kernels

`centreon/` runs two Symfony kernels side by side:

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

- **Domain**: aggregates, entities and value objects in `Domain/Model`,
  repository interfaces in `Domain/Repository`, events in `Domain/Event`.
- **Application**: commands and their handlers, event handlers.
- **Infrastructure**: API Platform resources, state providers and processors in
  `Infrastructure/ApiPlatform`, DBAL repositories and transformers in
  `Infrastructure/Dbal`, adapters to legacy code in `Infrastructure/Legacy`,
  voters.

## Aggregates and repositories

- An aggregate root's id is `null` until it is persisted: the DBAL repository
  sets it after the insert (`DbalRepository::setId()`), and `id()` throws a
  `MissingIdException` before that.
- A repository is an interface in `Domain/Repository/<Aggregate>/`, a
  `Dbal*Repository` implementation and its transformers in
  `Infrastructure/Dbal/<Aggregate>/`. Generic behaviour every aggregate needs
  goes in `App\Shared`, not in one context.

## API Platform

- Input and Output DTOs sit next to their resource in
  `Infrastructure/ApiPlatform/Resource/<Aggregate>/` (`CreateHostInput`,
  `…ChoicesOutput`).
- Payload and response properties are snake_case: API Platform uses the
  `camel_case_to_snake_case` name converter
  (`config.new/packages/api_platform.yaml`).

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

## Static analysis

Custom PHPStan rules in `phpstan.new.neon`, run by `composer stan:new`:

- command and event handlers, API Platform resources, processors,
  transformers and voters are `final`;
- command and event handlers expose only `__construct()` and `__invoke()`, and
  a command handler never uses the command bus;
- every command or event handler, processor and `DbalRepository` has a test;
- enums are suffixed `Enum`.

## Tests

`tests/php/App/` mirrors `src/App/`. `composer test:new` runs the unit tests and
skips the `*/Infrastructure` folders, which need a database: run them with
`composer test:new:integration`.

## Known gaps

- Some contexts still have a `Domain/Aggregate` folder that predates the ADR
  naming it `Model`.
  New code goes in `Domain/Model`.
- Do not bring `src/Core` patterns into `src/App`: no use case and presenter
  classes, no `Read*`/`Write*` repository interfaces in `Application/`.
