# ADR-0016: Log from `src/Core` only through `Adaptation\Log\Logger`

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Core

## Context

`src/Core` logs mostly through `Centreon\Domain\Log\LoggerTrait`, whose logger the container injects. A few classes use `Adaptation\Log\Logger`, an injected PSR-3 logger or `ExceptionLogger`. Without a rule, code keeps reaching for whichever entry point is nearest, and its lines land in different files with different shapes.

`src/App` logs through Monolog, configured in `config.new/packages/monolog.yaml`. `Adaptation\Log`, introduced in [#8211](https://github.com/centreon/centreon/pull/8211) (see [logging.md](../logging.md)), builds its own Monolog logger **aligned with that configuration**, so that the logs are harmonised:

- each channel of `LogChannelEnum` is a channel declared in `monolog.yaml`;
- each channel writes to the same file, `<env>.<channel>.log` (`authentication` writes to `<env>.access.log`);
- the lines have the same format and go through the masking (`SanitizingProcessor`), exception formatting (`ExceptionFormatterProcessor`), web-request and uid processors; `src/App` also adds the route and token processors.

Two alternatives were weighed:

- **Keep `LoggerTrait`.** It lost: it is a second entry point beside `Adaptation\Log\Logger`, configured in the container apart from it.
- **Inject a `LoggerInterface` built by the container.** It lost: an autowired `LoggerInterface` is the default Monolog logger of the `config/` kernel, without the masking and exception formatting processors, which are declared for `config.new` and the `Adaptation` adapter only, and without the module channels. Wiring an `Adaptation\Log` logger instead needs container configuration per context, and most `src/Core` contexts have no `symfony.yaml` of their own.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Log every line written or touched in `src/Core` through `Adaptation\Log\Logger`.** Call one of its factories where the line is written:

- `Logger::create(LogChannelEnum::…)` for a `LogChannelEnum` channel (`web`, `upgrade`, `poller-install`, …);
- a dedicated logger with its own business methods: `LoggerAuthentication::create()`, `LoggerPassword::create()`, `LoggerToken::create()` or `LoggerUpgrade::create()`;
- in a **module**'s `src/Core`, `Logger::create(new ModuleLogChannel('<module>'))`, which writes to `<module>.log`.

**Never** log through `LoggerTrait` or the other `Centreon\Domain\Log\*` classes, `ExceptionLogger`, `error_log()` or an autowired `LoggerInterface`.

Pass the business data as the log **context** (the second argument): identifiers and scalar values, **never** an object that carries a [secret](../glossary.md#secret), a raw form or request array, or a whole DTO. When you log a caught exception, put it under the context's **`exception`** key; `ExceptionFormatterProcessor` expands it. It does not log the exception's own context: when the caught exception is a `BusinessLogicException`, such as a `RepositoryException`, also spread its `getBusinessContext()` into the log context. Do not put the caught exception's `getMessage()` in the message: the `exception` key already carries it, with its whole `previous` chain. That chain is logged unmasked, so a driver message that echoes a bound value still reaches the log.

An example is in [logging.md](../logging.md#14-logging-from-core).

## Consequences

- `src/Core` and `src/App` lines land in the same files, with the same shape and the same masking.
- The alignment is kept by hand: a channel added to `monolog.yaml` must also be added to `LogChannelEnum`, with its file name. No test checks that the two match.
- The alignment is not complete: on the `web` channel, `src/App` buffers its lines and writes them only when an error occurs, whereas the adapter writes every line at `info` level or above.
- Existing classes that get `LoggerInterface` by autowiring log without masking until they are touched.
- No container configuration is needed: a class logs wherever it is built, as legacy code does.
- A `Logger::create()` call cannot be replaced by a fake, so a test does not assert on its lines. A dedicated logger is a singleton: a test swaps its instance by reflection around a fake `LoggerInterface`, as `LoginTest` does.
- When a class moves to `src/App`, its log calls are rewritten to an injected logger.
- `src/App` is out of scope: it uses an injected PSR-3 logger.
- `#[Sensitive]` is not read for a direct log call: only the [keyword net](../glossary.md#keyword-net) masks its context. Logging identifiers rather than objects is what keeps a secret out.
- The rule is blocking on new and touched lines, but does not force a rewrite of existing code: the existing `LoggerTrait` calls stay until they are touched.
- The rule is enforced in review.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
- The `Adaptation` layer's logging is replaced, or it reads `monolog.yaml` itself instead of being aligned with it.
