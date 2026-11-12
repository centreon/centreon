# centreon/centreon

Entry point for AI coding agents and contributors. This file only routes to
where the knowledge lives; follow the links for the detail.

## Repository

| Path | Content |
| --- | --- |
| `centreon/` | Centreon Web: PHP back end and React front end. Most work happens here. |
| `centreon-awie/`, `centreon-dsm/`, `centreon-ha/`, `centreon-open-tickets/` | Open-source modules shipped with Centreon Web |
| `php-tools/` | PHP quality tools (Rector, PHP CS Fixer, PHPStan, Deptrac) called by the `centreon/composer.json` scripts |
| `.github/` | CI workflows |

## Back end (PHP)

Three generations of code live side by side in `centreon/`. Read the guide of
the zone you work in:

| Zone | Paths | Status | Guide |
| --- | --- | --- | --- |
| `src/App` | `centreon/src/App`, `centreon/config.new` | Target architecture: new features go here | [app.md](centreon/doc/php/architecture/app.md) |
| `src/Core` | `centreon/src/Core` | Previous architecture: fixes only | [core.md](centreon/doc/php/architecture/core.md) |
| Legacy | PHP in `centreon/www`, other `centreon/src` folders | Fixes only | [legacy.md](centreon/doc/php/architecture/legacy.md) |

- Architecture decisions: the [ADR index](centreon/doc/php/adr/index.md). For
  new code, an ADR wins over the neighbouring code.
- Vocabulary: the [glossary](centreon/doc/php/glossary.md).
- Subsystem notes (authentication providers, logging, permissions…):
  `centreon/doc/php/`.
- Before planning, coding or running commands for backend work, read
  [`.claude/rules/backend.md`](.claude/rules/backend.md): commands, pitfalls
  and definition of done.

## Front end (React)

- Application: `centreon/www/front_src`. Shared packages: `centreon/packages`
  (`@centreon/ui` is published to npm and used by other repositories).
- Before frontend work, read [`.claude/rules/frontend.md`](.claude/rules/frontend.md).

## Contributing

- Branch from `develop`, or from a `dev-YY.MM.x` branch for a fix to a
  maintained version. Commits follow conventional commits (`fix(web): …`).
- Every PR is linked to a Jira ticket by a maintainer (key in the branch name
  or the PR description).
- Enable the repository git hooks once with `git config core.hooksPath .githooks`
  (secret scanning). Never bypass them.
- Personal notes go in `CLAUDE.local.md`, personal Claude Code settings in
  `.claude/settings.local.json`; both are git-ignored. `.claude/settings.json`
  is the shared baseline.

## Working rules for agents

- Check before stating: read the file, search, run the command or the test. A
  memory or "it is usually so" is not a check, and neither is a cause you did
  not test.
- Re-read the guide or ADR that applies before acting. If you must deviate from
  it, say so and why before doing it.
- A fact that is missing here or in a linked guide belongs in that guide or in
  a new ADR, not in this file.
