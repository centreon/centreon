# ADR-0042: Keep `@centreon/ui` backward compatible for the module frontends

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`@centreon/ui` is published to npm by the `npm-ui` workflow through `npm-publish-package.yml`, on every push to `develop` that touches `packages/ui`, with a version derived from `.version.centreon-web`. The eight frontends of the `centreon-modules` repository pin it (`"@centreon/ui": "26.11.4"` at the time of writing) and bump it with their own script. Centreon Web consumes the package from the workspace, so a breaking change is invisible in this repository: it surfaces in a module at its next bump, as a build error at best and as a changed screen at worst.

Nothing records today which changes break a consumer, and the package has no changelog.

Two alternatives were weighed:

- **Semantic versioning with a major bump on every break.** It lost for now: the version is derived from the Centreon version by the publish workflow, and the modules pin a Centreon version, not a semver range.
- **Build the modules in this repository's CI on every `packages/ui` change.** It lost for now: the modules live in another repository with their own lockfiles; it is a candidate enforcement, not a rule.

Settled in [#12827](https://github.com/centreon/centreon/pull/12827).

## Decision

A change to `packages/ui` **keeps every published component, hook, atom, type and label constant working as before** for a consumer that does not change its code. A **removed or renamed prop, a changed default, a changed rendered structure a consumer may select on, a removed export, is a breaking change**: it keeps the old behaviour available until every module frontend is migrated, and the pull request states the break and the migration a consumer needs.

## Consequences

- A new behaviour is a new prop with a default that keeps the old one.
- The pull request of a breaking change names the consumers it affects; the module frontends are checked by searching the `centreon-modules` repository.
- A Storybook story of the component reflects its current props, so a consumer sees the change.
- The rule is enforced in review.

## When to Amend

- The package moves to semantic versioning, or the modules are built in this repository's CI; the record then names the enforcement.
