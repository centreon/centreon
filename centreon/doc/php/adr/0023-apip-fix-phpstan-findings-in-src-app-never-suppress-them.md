# ADR-0023: Fix PHPStan findings in new and changed `src/App` code, never suppress them

- **Status:** Accepted
- **Date:** 2026-11-02
- **Scope:** APIP

## Context

PHPStan runs at level `max` on `src/App` (`phpstan.new.neon`) and on its tests (`phpstan.new.test.neon`), and on a module's `src/App` under the module's own configuration, and CI fails on any finding. When a finding is inconvenient, the shortcut is to silence it: an inline `@phpstan-ignore` / `@phpstan-ignore-line` / `@phpstan-ignore-next-line`, or a new `ignoreErrors` entry in one of these configuration files or in a baseline.

The finding is usually right. A suppression hides a wrong signature, visibility or type instead of fixing it. For example, `App\Shared\Infrastructure\Symfony\Kernel::configureContainer()` is `private` with `@phpstan-ignore method.unused`, because the framework calls it and PHPStan cannot see that call. Making it `protected` removes both the warning and the need to suppress it.

The practice is nearly settled: inline ignores are rare and the `src/App` baseline is nearly empty. What is not settled is whether a new suppression is acceptable when it is justified in a comment. Reviews answer differently each time.

Two alternatives were weighed:

- **Allow an inline suppression with a justification comment.** It lost: every suppression comes with a reason, so the rule would filter nothing, and the justification stays buried in the code instead of being reviewed in the pull request.
- **Judge each suppression by what it hides**, blocking only when it masks a bug. It lost for the same reason: the outcome depends on the reviewer, and the hidden problem is often found only later.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Do not add a PHPStan suppression in new or changed APIP code** (`src/App`, a module's `src/App` included, and `config.new`) or in its tests. **Never** add an inline `@phpstan-ignore`, `@phpstan-ignore-line` or `@phpstan-ignore-next-line`, and **never** add an `ignoreErrors` entry outside its baseline file to a `phpstan*.neon` configuration that analyses APIP code.

**Fix the cause instead**: correct the type, the visibility or the signature, or change the design so the finding no longer applies.

**The baseline is the only escape hatch, for a major blocker only.** When a finding cannot be fixed without a disproportionate change, add it to the configuration's baseline file (`phpstan*.baseline.neon`). A configuration without a baseline file gets one, included from it, holding only that entry. The pull request description must justify each new baseline entry: the finding, why it cannot be fixed now, and what would remove it. A new baseline entry without that justification is a blocking finding. Otherwise, baselines only shrink.

## Consequences

- The rule is enforced in review.
- The existing inline ignores and baseline entries stay until their code changes. A change that modifies a line carrying an inline ignore or covered by a baseline entry fixes the finding rather than keeping the suppression; when the fix is disproportionate, the baseline entry stays only with the same pull-request justification as a new one.
- The existing `ignoreErrors` patterns of the configuration files are tool configuration and stay.
- A finding that cannot be fixed in the code, such as a PHPStan false positive, a framework limitation or a test that deliberately violates a type contract (`InvalidReferenceExceptionListenerTest`), goes to the baseline with its justification in the pull request. A recurring one leads to a PHPStan extension or stub, or an amendment of this ADR.
- `phpstan.new.neon` also analyses `src/Adaptation` and `src/App/Kernel.php`, which are legacy code: they are out of this ADR's scope.
- This ADR does not cover other tools: `@phpcs:ignore` and Rector skips are out of scope.

## When to Amend

- PHPStan is dropped, or its level on `src/App` is lowered.
- A recurring, legitimate case appears that no type, stub, extension or baseline entry can handle.
