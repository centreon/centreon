# ADR-0025: Fix PHPStan findings in new and changed legacy code, never suppress them

- **Status:** Accepted
- **Date:** 2026-11-02
- **Scope:** Legacy

## Context

PHPStan runs on most legacy code, at the level set by each configuration file (the `phpstan.legacy*.neon` files, those that cover `src/Adaptation`, each module's own), and CI fails on any finding. When a finding is inconvenient, the shortcut is to silence it: an inline `@phpstan-ignore` / `@phpstan-ignore-line` / `@phpstan-ignore-next-line`, or a new `ignoreErrors` entry in a `phpstan*.neon` file or its baseline.

The finding is usually right: a suppression hides a wrong signature, visibility or type instead of fixing it. The legacy baselines hold the findings of pre-existing code, and some configuration files carry rule-wide `ignoreErrors`. What is not settled is whether a new suppression is acceptable when it is justified in a comment. Reviews answer differently each time.

Two alternatives were weighed:

- **Allow an inline suppression with a justification comment.** It lost: every suppression comes with a reason, so the rule would filter nothing, and the justification stays buried in the code instead of being reviewed in the pull request.
- **Judge each suppression by what it hides**, blocking only when it masks a bug. It lost for the same reason: the outcome depends on the reviewer, and the hidden problem is often found only later.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Do not add a PHPStan suppression in new or changed legacy code**, anywhere in the [Legacy scope](../glossary.md#scope), and in its tests. **Never** add an inline `@phpstan-ignore`, `@phpstan-ignore-line` or `@phpstan-ignore-next-line`, and **never** add an `ignoreErrors` entry to a `phpstan*.neon` configuration file outside its baseline file (`phpstan*.baseline.neon`).

**Fix the cause instead**: correct the type, the visibility or the signature, or change the design so the finding no longer applies.

**The baseline is the only escape hatch, for a major blocker only.** When a finding cannot be fixed without a disproportionate change, add it to the configuration's baseline file. A configuration without a baseline file gets one, included from it, holding only that entry. The pull request description must justify each new baseline entry: the finding, why it cannot be fixed now, and what would remove it. A new baseline entry without that justification is a blocking finding. Otherwise, baselines only shrink.

## Consequences

- The rule is enforced in review.
- The existing inline ignores and baseline entries stay until their code changes. A change that modifies a line carrying an inline ignore or covered by a baseline entry fixes the finding rather than keeping the suppression; when the fix is disproportionate, the baseline entry stays only with the same pull-request justification as a new one.
- The existing `ignoreErrors` patterns of the configuration files are tool configuration and stay, such as the Pest messages in the test configurations, unresolvable include paths, functions loaded at runtime, third-party code copied into a module and the custom-rule exceptions.
- A finding that cannot be fixed in the code, such as a PHPStan false positive or a framework limitation, goes to the baseline with its justification in the pull request. A recurring one leads to a PHPStan extension or stub, or an amendment of this ADR.
- This ADR does not cover other tools: `@phpcs:ignore` and Rector skips are out of scope.

## When to Amend

- PHPStan is dropped, or its level on a legacy configuration is lowered.
- A recurring, legitimate case appears that no type, stub, extension or baseline entry can handle.
- No legacy code remains.
