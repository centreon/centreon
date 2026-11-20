# ADR-0046: Assert behaviour with PHPUnit in `src/App` tests, and use `Assert::*` only to narrow a type

- **Status:** Accepted
- **Date:** 2026-11-12
- **Scope:** APIP

## Context

[ADR-0021](0021-apip-enforce-invariants-with-webmozart-assert.md) makes `Webmozart\Assert\Assert` the vocabulary of invariants in `src/App`. The `src/App` tests use it too, mostly `Assert::scalar` and `Assert::notFalse` (`ListPollersChoicesProviderTest`). Almost all of them narrow a type for PHPStan, such as a decoded response or a fetched column, before a PHPUnit assertion checks the value. Nothing says whether a test may also check behaviour with `Assert::*`.

An `Assert::*` failure throws an `InvalidArgumentException`: PHPUnit reports it as an error, not as a failed assertion, with no expected and actual values, and does not count it as an assertion.

Two alternatives were weighed:

- **Forbid `Assert::*` in tests.** It lost: `phpstan-phpunit` narrows after `self::assertIsX()` too, but `Assert::*` covers checks PHPUnit lacks (`integerish`, `stringNotEmpty`), and the existing calls would break the rule.
- **Allow `Assert::*` for any check in a test.** It lost: a failing check would read as a crash rather than as a failed expectation.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302#discussion_r4551239077).

## Decision

**In a `src/App` test, assert behaviour with PHPUnit** (`self::assertSame()`, `self::assertCount()`, `self::expectException()`, …), **never with `Assert::*`.**

**`Assert::*` may still narrow a type for PHPStan**, before the PHPUnit assertion that checks the value.

## Consequences

- A failing behaviour check reports its expected and actual values.
- The existing `Assert::*` calls that narrow a type stay as they are.
- The rule is enforced in review.

## When to Amend

- PHPUnit gains an assertion for every check the tests narrow with `Assert::*`, which would make the latter useless in tests.
