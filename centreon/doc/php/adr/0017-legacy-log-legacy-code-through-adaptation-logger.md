# ADR-0017: Log from legacy code only through the `Adaptation\Log` loggers

- **Status:** Accepted
- **Date:** 2026-10-28
- **Scope:** Legacy

## Context

[ADR-0005](0005-apip-isolate-legacy-in-infrastructure-legacy.md) and [ADR-0006](0006-legacy-expose-symfony-services-to-legacy-via-service-locator.md) draw the boundary between `src/App` and legacy. Neither says how legacy code itself — the [Legacy scope](../glossary.md#scope) — should log.

Legacy code offers several entry points: `CentreonLog`, `CentreonUserLog`, `error_log()`, the `Centreon\Domain\Log\*` classes (`Logger` and `LoggerTrait`) and `ExceptionLogger`. `CentreonLog` is deprecated and already reroutes through `Adaptation\Log\Logger`, introduced in [#8211](https://github.com/centreon/centreon/pull/8211) (see [logging.md](../logging.md)). Without a rule, code keeps reaching for whichever entry point is nearest, and its lines land in different files with different shapes.

`src/App` logs through Monolog, configured in `config.new/packages/monolog.yaml`. `Adaptation\Log` builds its own Monolog logger **aligned with that configuration**, so that the logs are harmonised:

- each channel of `LogChannelEnum` is a channel declared in `monolog.yaml`;
- in production, each channel writes to the same file, `<env>.<channel>.log` (`authentication` writes to `<env>.access.log`);
- the lines have the same format and go through the same masking and exception formatting.

Two alternatives were weighed:

- **Keep `CentreonLog`, since it already reroutes through `Adaptation\Log\Logger`.** It lost: it is deprecated, maps its identifiers onto a fixed subset of channels, and offers no module channel.
- **Inject a PSR-3 logger, as `src/App` does.** It lost for legacy: much legacy code (`www/` scripts and functions) is not built by the Symfony container, and a static factory reaches every call site. Where the container does build legacy code, an autowired `LoggerInterface` is the default Monolog logger of the `config/` kernel, without the masking and exception formatting processors.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Log every line written or touched in the [Legacy scope](../glossary.md#scope) through an `Adaptation\Log` logger.** Get it from its factory where the line is written:

- `Logger::create(LogChannelEnum::…)` for a `LogChannelEnum` channel (`web`, `upgrade`, `poller-install`, …);
- a dedicated logger with its own business methods: `LoggerAuthentication::create()`, `LoggerPassword::create()`, `LoggerToken::create()` or `LoggerUpgrade::create()`;
- in a **module**, outside its `src/App` and `src/Core`, `Logger::create(new ModuleLogChannel('<module>'))`, which writes to `<module>.log`.

**Never** log through `CentreonLog`, `CentreonUserLog`, `error_log()`, `ExceptionLogger`, the `Centreon\Domain\Log\*` classes or an autowired `LoggerInterface`. The rule applies to legacy **call sites**; the `Adaptation` layer's own internals are out of scope.

Pass the business data as the log **context** (the second argument): identifiers and other non-secret scalar values, **never** a [secret](../glossary.md#secret), whatever its type, a raw form or request array, or a whole DTO. When you log a caught exception, put it under the context's **`exception`** key; `ExceptionFormatterProcessor` expands it. It does not log the exception's own context: when the caught exception is a `BusinessLogicException`, such as a `RepositoryException`, also spread its `getBusinessContext()` into the log context. **Never** put a secret in an exception message either: the message chain is logged unmasked. Do not put the caught exception's `getMessage()` in the message: the `exception` key already carries it, with its whole `previous` chain.

An example is in [logging.md](../logging.md#11-legacy-bridge--adaptationloglogger).

## Consequences

- Legacy and `src/App` lines land in the same files, with the same shape and the same masking.
- The alignment is kept by hand: a channel that code outside `src/App` logs to must also be added to `LogChannelEnum`, with its file name. No test checks that the two match.
- The alignment is not complete: the handlers and levels differ, on the `web` channel in production and on every channel in dev ([logging.md](../logging.md)).
- `src/App` and `src/Core` are out of scope.
- `#[Sensitive]` is not read for a direct log call: only the [keyword net](../glossary.md#keyword-net) masks its context. Logging identifiers rather than objects is what keeps a secret out.
- The rule is blocking on new and touched lines, but does not force a rewrite of existing code.
- The rule is enforced in review. The deprecated entry points are not removed, so a search for `CentreonLog`, `CentreonUserLog` or `error_log()` is only a heuristic: a hit is confirmed by reading the code.

## When to Amend

- No legacy code remains.
- The `Adaptation` layer's logging is replaced, or it reads `monolog.yaml` itself instead of being aligned with it.
