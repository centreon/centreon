---
paths:
  - "centreon/src/**"
  - "centreon/www/**/*.php"
  - "centreon/tests/php/**"
  - "centreon/config/**"
  - "centreon/doc/php/**"
  - "centreon/composer.json"
  - "centreon/phpstan*.neon"
  - "centreon/rector*.php"
  - "centreon/.php-cs-fixer*.php"
  - "centreon/deptrac*.yaml"
  - "centreon/phpunit*.xml"
---

# Backend (PHP) in centreon/centreon

For backend and fullstack developers. Loaded only when working on backend
files. Team-owned and reviewed like code: update it in the same PR as any
convention change. Personal notes go in `CLAUDE.local.md` (git-ignored).
`.claude/settings.json` is the team baseline (read-only allowlist, deny rules
for risky git flags); personal settings go in `.claude/settings.local.json`.

## Architecture decisions

The PHP team's decisions are recorded as ADRs in `centreon/doc/php/adr/`. Read
the [ADR index](../../centreon/doc/php/adr/index.md) before designing or
reviewing backend code, and follow the ADRs that apply. The terms they use
(aggregate, command, event, transformer…) are defined in the
[glossary](../../centreon/doc/php/glossary.md).

- Existing code does not always follow the ADRs yet. For new code, the ADR
  wins over the neighbouring code.
- To record a new decision, follow the
  [ADR README](../../centreon/doc/php/adr/README.md).

## Layout

Three generations of code live side by side in `centreon/src/`:

- `src/App/`: the target architecture (DDD, Symfony Messenger commands and
  events, API Platform), one folder per bounded context
  (`MonitoringConfiguration`, `Security`, `ActivityLogging`, `Upgrade`,
  `Shared`), each split into `Domain/`, `Application/`, `Infrastructure/`. Its
  kernel is `src/App/Kernel.php`, its console `bin/console.new`, its config
  `config.new/`.
- `src/Core/`: the previous hexagonal architecture (use case, presenter,
  controller).
- `src/Adaptation/`: the database and log adapters for legacy code.
- Legacy: `centreon/www/` and the other folders of `src/` (`Centreon`,
  `CentreonLegacy`…).

New features go in `src/App`. Do not extend `src/Core` or legacy unless the
change is a fix there.

## Commands

The quality tools are installed in `php-tools/` at the repository root: run
`composer install` there once. Then run from `centreon/`:

| Command                                          | Does                                                    |
| ------------------------------------------------ | ------------------------------------------------------- |
| `composer rector:diff` / `composer rector:diff:fix` | Rector on the changed PHP files                      |
| `composer cs:diff` / `composer cs:diff:fix`      | PHP CS Fixer on the changed PHP files                   |
| `composer stan:new`                              | PHPStan on `src/App` and its tests                      |
| `composer stan:core` / `composer stan:legacy`    | PHPStan on `src/Core` / legacy                          |
| `composer dep:new`                               | Deptrac: bounded contexts and hexagonal layers          |
| `composer test:new`                              | `src/App` unit tests (`phpunit.new.xml`)                |
| `composer test:new:integration`                  | `src/App` integration tests, need a database            |
| `composer test`                                  | legacy and `src/Core` tests                             |
| `composer symfony:new:check`                     | lint the `src/App` YAML config and container            |

`rector:diff` and `cs:diff` take the files from `git diff`; set
`PHP_DIFF_FILES` to check other files.

## Conventions

- `declare(strict_types=1);` in every file; classes `final` (and `readonly`
  when they can be).
- Code style: PHP CS Fixer and Rector own it. Do not restate or fight them.
- Canonical aggregate to copy in `src/App`:
  `MonitoringConfiguration` `Host` (domain, command handlers, Dbal repository,
  API Platform resource and state).
- Do not add an inline `@phpstan-ignore` in `src/App`. If an error must stay,
  it goes in `phpstan.new.baseline.neon`, justified in the PR.
- Prefer built-in Symfony and API Platform features before writing a custom
  class.
- SQL: `QueryBuilder` and bound parameters only, never concatenated request
  data ([ADR-0010](../../centreon/doc/php/adr/0010-parameterize-repository-queries.md)).

## Tests

- `src/App` tests are PHPUnit, in `centreon/tests/php/App/`, mirroring the
  `src/App` tree.
- Unit tests by default. Integration tests (`tests/php/App/*/Infrastructure`)
  only for what a unit test cannot reach: SQL, the container, the HTTP layer.
- A new endpoint updates the OpenAPI documentation in `centreon/doc/API/`.

## Definition of done

A backend change is done when, on the changed files, `composer rector:diff`,
`composer cs:diff`, the PHPStan run for its layer (`stan:new`, `stan:core` or
`stan:legacy`), `composer dep:new` (for `src/App`) and the related tests pass.
State in the PR what ran and what did not (integration tests needing a
database, CI-only jobs).

## Git and PRs

- Branch `MON-XXXXXX-short-description` (CI requires the Jira key), targeting `develop` unless based on a `dev-XX.XX.x` series; conventional commits (`fix(web): ...`), one Jira key per PR.
- Enable the gitleaks hooks once with `git config core.hooksPath .githooks`; never bypass them with `--no-verify`.

## Verify, don't assume

For developers and AI agents alike: do not state a fact, or justify a choice,
that you have not checked in the current session.

- Check before saying: read the file, `git grep`, run the command or the test.
  A memory, an earlier read or "it is usually so" is not a check.
- A "because X" is a claim too. Do not justify a decision (in code, a PR or a
  review reply) with a cause you did not test. When something behaves
  unexpectedly, find the actual error before working around it.
- Re-read the rule or ADR that applies, this file included, before acting. If
  you must deviate from it, say so and why before doing it, not after a review.

## Do not

- Regenerate a PHPStan baseline wholesale to make the analysis pass. Fix the
  error, or add only the intended entry.
- Hand-edit `composer.lock` or `symfony.lock`: change dependencies with
  `composer require` / `composer remove`.
- Commit secrets, or mention security vulnerabilities in branch names, commit
  messages or PR titles and descriptions.
- Put module- or feature-specific detail here. It belongs in a `CLAUDE.md`
  inside that scope, so it loads only when working there.
