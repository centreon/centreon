---
name: verify-frontend
description: Use after changing frontend code in centreon/www/front_src or centreon/packages/ui, before saying the change is done, committing or opening a PR. Runs Biome, the type-check and the related Jest, rstest and legacy Cypress tests on the changed files.
---

# Verify a frontend change

A frontend change is done when this passes. Run it after the change, fix
what it reports, and run it again until `RESULT: PASS`.

```bash
.claude/skills/verify-frontend/verify.sh            # changed files since origin/develop
.claude/skills/verify-frontend/verify.sh --fast     # type-check the changed files only
.claude/skills/verify-frontend/verify.sh --base origin/dev-25.10.x
```

- Run it **in the background**: a legacy Cypress spec takes a few minutes.
  Keep working, read the result when it completes.
- It covers what changed since the base branch, committed or not: Biome
  (`--error-on-warnings`, as CI), the type-check, Jest tests of the changed
  files, and the feature's rstest browser tests, or its legacy Cypress spec
  when it has no rstest test yet.
- The output is one line per step and the first failures. Full logs are in
  the `Logs:` directory: read a log only when the excerpt is not enough.
- The type-check is the complete `pnpm type-check`, so it
  also catches errors in files that import the changed ones. `--fast` checks
  the changed files only, for quick iterations; run without it before done.

## When it fails

- Fix the cause in the code. Do not weaken a test, add `@ts-ignore`, or
  update snapshots to make it pass.
- A visual snapshot failure: look at the `*.diff.png` of that spec. Update
  that one spec's snapshots only if the change is intended (see the commands
  in `.claude/rules/frontend.md`).
- `NOTE no related test found`: the changed code has no test. Add an rstest
  browser test following `.claude/rules/frontend.md`, or say so in the PR.
- Setup errors come with a hint (`pnpm install`, `pnpm rstest:install`,
  `pnpm cypress:install` in `centreon/`): run it once, then verify again.

## Visual check

For a change users see, tests alone are not enough. After `RESULT: PASS`:

- if the feature has a Cypress snapshot, its diff shows the change;
- if a Centreon dev environment is running, open the page in the browser,
  check the changed screen and its empty, loading and error states;
- otherwise, say in the PR that the UI was not checked visually.
