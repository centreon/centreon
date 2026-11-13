# ADR-0016: Log from `src/Core` only through the `Adaptation\Log` loggers

- **Status:** Accepted
- **Date:** 2026-11-05
- **Scope:** Core

## Context

`src/Core` logs mostly through `Centreon\Domain\Log\LoggerTrait`, whose logger the container injects. A few classes use `Adaptation\Log\Logger`, an injected PSR-3 logger, `ExceptionLogger`, `CentreonLog` or `CentreonUserLog`. Without a rule, code keeps reaching for whichever entry point is nearest, and its lines land in different files with different shapes.

`src/App` logs through Monolog, configured in `config.new/packages/monolog.yaml`. `Adaptation\Log`, introduced in [#8211](https://github.com/centreon/centreon/pull/8211) (see [logging.md](../logging.md)), builds its own Monolog logger **aligned with that configuration**, so that the logs are harmonised:

- each channel of `LogChannelEnum` is a channel declared in `monolog.yaml`;
- in production, each channel writes to the same file, `<env>.<channel>.log` (`authentication` writes to `<env>.access.log`);
- the lines have the same format and go through the same masking and exception formatting.

Two alternatives were weighed:

- **Keep `LoggerTrait`.** It lost: it is a second entry point beside `Adaptation\Log\Logger`, configured in the container apart from it.
- **Inject a `LoggerInterface` built by the container.** It lost: an autowired `LoggerInterface` is the default Monolog logger of the `config/` kernel, without the masking and exception formatting processors, which are declared for `config.new` and the `Adaptation` adapter only, and without the module channels. Wiring an `Adaptation\Log` logger instead needs container configuration per context, and most `src/Core` contexts have no `symfony.yaml` of their own.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Log every line written or touched in `src/Core` through an `Adaptation\Log` logger.** Get it from its factory where the line is written:

- `Logger::create(LogChannelEnum::…)` for a `LogChannelEnum` channel (`web`, `upgrade`, `poller-install`, …);
- a dedicated logger with its own business methods: `LoggerAuthentication::create()`, `LoggerPassword::create()`, `LoggerToken::create()` or `LoggerUpgrade::create()`;
- in a **module**'s `src/Core`, `Logger::create(new ModuleLogChannel('<module>'))`, which writes to `<module>.log`.

**Never** log through `LoggerTrait` or the other `Centreon\Domain\Log\*` classes, `CentreonLog`, `CentreonUserLog`, `ExceptionLogger`, `error_log()` or an autowired `LoggerInterface`.

Pass the business data as the log **context** (the second argument): identifiers and other non-secret scalar values, **never** a [secret](../glossary.md#secret), whatever its type, a raw form or request array, or a whole DTO. When you log a caught exception, put it under the context's **`exception`** key; `ExceptionFormatterProcessor` expands it. It does not log the exception's own context: when the caught exception is a `BusinessLogicException`, such as a `RepositoryException`, also spread its `getBusinessContext()` into the log context. **Never** put a secret in an exception message either: the message chain is logged unmasked. Do not put the caught exception's `getMessage()` in the message: the `exception` key already carries it, with its whole `previous` chain.

An example is in [logging.md](../logging.md#14-logging-from-core).

## Consequences

- `src/Core` and `src/App` lines land in the same files, with the same shape and the same masking.
- The alignment is kept by hand: a channel that code outside `src/App` logs to must also be added to `LogChannelEnum`, with its file name. No test checks that the two match.
- The alignment is not complete: the handlers and levels differ, on the `web` channel in production and on every channel in dev ([logging.md](../logging.md)).
- Existing classes that get `LoggerInterface` by autowiring log without masking until they are touched.
- No container configuration is needed: a class logs wherever it is built, as legacy code does.
- The rule holds in every layer of `src/Core`: a Domain or Application class that logs depends on the static factory, an infrastructure entry point that `DomainCallNamespacesCustomRule` does not see. It is tolerated because `src/Core` Domain classes already log, and taking logging out of a layer is left to the move to `src/App`.
- A `Logger::create()` call cannot be replaced by a fake, so a test does not assert on its lines. A dedicated logger is a singleton: a test swaps its instance by reflection around a recording spy `LoggerInterface`, as the Pest file `LoginTest` does.
- `src/App` is out of scope: it uses an injected PSR-3 logger, to which a class's log calls are rewritten when it moves there.
- `#[Sensitive]` is not read for a direct log call: only the [keyword net](../glossary.md#keyword-net) masks its context. Logging identifiers rather than objects is what keeps a secret out.
- The rule is blocking on new and touched lines, but does not force a rewrite of existing code: the existing `LoggerTrait` calls stay until they are touched.
- The rule is enforced in review.

## When to Amend

- `src/Core` is fully migrated to `src/App`.
- The `Adaptation` layer's logging is replaced, or it reads `monolog.yaml` itself instead of being aligned with it.
