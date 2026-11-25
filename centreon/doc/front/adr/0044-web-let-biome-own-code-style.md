# ADR-0044: Let Biome own `www/front_src` code style; no formatting rule in prose

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

Code style in `www/front_src` is checked and fixed by Biome: `centreon/biome.json` extends `packages/js-config/biome/biome.json` and `tests.json`, `pnpm lint` runs `biome check ./www/front_src --error-on-warnings`, the `frontend-web-lint` job of `web.yml` runs Biome on the same path with warnings reported, and the repository hook `.claude/hooks/biome-on-edit.sh` formats every file an assistant edits. The older `doc/coding-style/js`, `css` and `html` guides predate React and Biome (`var`, four-space indentation, OOCSS) and contradict the configuration.

A style rule written in prose is either what Biome enforces, and then a copy that drifts, or what Biome does not enforce, and then a debate at every review.

One alternative was weighed: **keep a short style guide for what Biome cannot check.** It lost: what Biome cannot check is a decision, and a decision is an ADR, not a style guide.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

**The Biome configuration is the code style.** No document restates a formatting or lint rule, and no code works around one: a disagreement with a rule is a **change to `packages/js-config/biome/biome.json`**, reviewed as such. The legacy coding-style guides are retired.

## Consequences

- A style comment in review points at the Biome rule or proposes a configuration change; it is not a matter of taste.
- The shared configuration applies to the module frontends too, whose `biome.json` extends it: a change here changes their style.
- The rule is enforced by Biome in CI and in the editor hook.

## When to Amend

- Biome is replaced.
