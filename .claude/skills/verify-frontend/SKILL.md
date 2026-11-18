---
name: verify-frontend
description: Use after changing frontend code in centreon/www/front_src or centreon/packages/ui (or the frontend configuration), before saying the change is done, committing or opening a PR. Runs Biome, the type-check and the related Jest, rstest and legacy Cypress tests on the changed files.
---

# Verify a frontend change

A frontend change is done when this ends with `RESULT: PASS`. Run it after
the change, fix what it reports, and run it again.

```bash
.claude/skills/verify-frontend/verify.sh            # changes since origin/develop
.claude/skills/verify-frontend/verify.sh --fast     # type-check the changed files only
.claude/skills/verify-frontend/verify.sh --base origin/dev-25.10.x
```

- Run it **in the background**: a legacy Cypress spec takes a few minutes.
  Keep working, read the result when it completes.
- It covers everything that changed since the base branch, committed or not,
  new and deleted files included: Biome (`--error-on-warnings`, as CI), the
  full type-check, Jest tests of the changed files, and the closest rstest
  browser tests or legacy Cypress spec of each changed file.
- The output is one line per step and the first failures. Full logs are in
  the `Logs:` directory: read a log only when the excerpt is not enough.

## Reading the result

| Last line              | Exit | Meaning                                                                                                                                                                                                                                                                   |
| ---------------------- | ---- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `RESULT: PASS`         | 0    | Everything that changed was checked: done.                                                                                                                                                                                                                                |
| `RESULT: FAIL`         | 1    | Fix the first failures shown above it, then run again.                                                                                                                                                                                                                    |
| `RESULT: PARTIAL`      | 3    | What ran passed, but the `Not checked:` list is not empty. Address it (add the missing test, rerun without `--fast`, run the skipped spec) or state each remaining item in your summary and the PR. Never call a PARTIAL result done without saying what was not checked. |
| `RESULT: NOT VERIFIED` | 2    | Nothing was checked: no frontend change, missing setup (`pnpm install`) or a wrong option.                                                                                                                                                                                |

## When it fails

- Fix the cause in the code. Do not weaken a test, add `@ts-ignore`, or
  update snapshots to make it pass.
- A visual snapshot failure: look at the `*.diff.png` of that spec. Update
  that one spec's snapshots only if the change is intended (see the commands
  in `.claude/rules/frontend.md`).
- `TIMEOUT`: a step hung (often a browser) and was killed. Run it again.
- Setup errors come with a hint (`pnpm install`, `pnpm rstest:install`,
  `pnpm cypress:install` in `centreon/`): run it once, then verify again.

## Trust

The script runs the branch's own tests and configuration. Do not run it on
a branch or PR from an untrusted contributor (a fork) without the user's
go-ahead, and do not add it to a permission allowlist.

## Visual check

For a change users see, tests alone are not enough. After the checks pass:

- if the feature has a Cypress snapshot, its diff shows the change;
- if a Centreon dev environment is running, open the page in the browser and
  check the changed screen and its empty, loading and error states;
- otherwise, say in the PR that the UI was not checked visually.
