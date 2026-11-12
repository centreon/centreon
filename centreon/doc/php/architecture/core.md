# `src/Core` architecture

The previous generation, hexagonal, served by the legacy kernel under
`/api/latest` (see [app.md](app.md#two-kernels)). It takes fixes and small
changes only: new features go in `src/App`. An architectural gap found here is
reported in a ticket, not rewritten in an unrelated PR.

## Layout

One folder per domain concept (`src/Core/Host`, `src/Core/Service`…), each
split into `Domain/`, `Application/` and `Infrastructure/`. Example:
`src/Core/Host`.

- **Application**: one folder per use case in `Application/UseCase/<Name>/`:
  the use case class, its request and response, a presenter interface, and
  factories or validation when needed. Repository interfaces sit in
  `Application/Repository/`, split into `Read*RepositoryInterface` and
  `Write*RepositoryInterface`.
- **Infrastructure**: one folder per endpoint in `Infrastructure/API/<Name>/`:
  the controller, its presenters, its route and its JSON schema. Repository
  implementations in `Infrastructure/Repository/`, prefixed by their storage
  (`Db*`, `Api*`…).

These patterns stay in `src/Core`: do not copy them into `src/App`.

## Shared code

New `src/Core` code may use the `src/App` shared kernel (`App\Shared`) but no
other `src/App` context; a few existing imports of `App\MonitoringConfiguration`
predate this rule. Database access goes through `src/Adaptation` (see
[legacy.md](legacy.md)).

## Quality

`composer core:check` runs Rector, PHP CS Fixer and PHPStan on `src/Core`.
Tests: `composer test` (unit) and `composer test:core` (integration).

TODO: list what the CI enforces only on `src/Core` (custom PHPStan rules).
