# Logging pipeline — Messenger bus, Monolog and MON-151077

This document describes the platform pipeline that captures logs emitted by the Symfony Messenger buses (`command.bus`, `query.bus`), enriches each record with HTTP / security context and routes it to `prod.web.log`. References: [MON-199096] (platform-side middleware migration), [MON-151077] (file layout, channel exclusions, RFC3339 format).

[MON-199096]: https://centreon.atlassian.net/browse/MON-199096
[MON-151077]: https://centreon.atlassian.net/browse/MON-151077

---

## Table of contents

1. [Overview](#1-overview)
2. [Middleware chain on `command.bus`](#2-middleware-chain-on-commandbus)
3. [`DoctrineTransactionMiddleware`](#3-doctrinetransactionmiddleware)
4. [`LoggingMiddleware`](#4-loggingmiddleware)
5. [`ExceptionFormatter` and `ExceptionFormatterProcessor`](#5-exceptionformatter-and-exceptionformatterprocessor)
6. [HTTP / security processors (Web, Route, Token)](#6-http--security-processors-web-route-token)
7. [Log line example](#7-log-line-example)
8. [Processor scope](#8-processor-scope)
9. [Routing and output file](#9-routing-and-output-file)
10. [Masking sensitive fields with `#[Sensitive]`](#10-masking-sensitive-fields-with-sensitive)
11. [Legacy bridge — `Adaptation\Log\Logger`](#11-legacy-bridge--adaptationloglogger)
12. [Writing authentication events](#12-writing-authentication-events)
13. [Writing upgrade scripts (`Update-*.php`)](#13-writing-upgrade-scripts-update-php)

---

## 1. Overview

Every dispatch on the platform Messenger buses produces three records on the Monolog `app` channel (Symfony default):

| Stage | Level | Emitter |
|---|---|---|
| Before the handler runs | `info` (`Dispatching …`) | `LoggingMiddleware` |
| Handler clean return | `info` (`Handled …`) | `LoggingMiddleware` |
| Handler throw | `warning` (domain rejection → 4xx) / `critical` (unexpected → 5xx) (`Failed to handle …`) | `LoggingMiddleware` |

Exceptions thrown **outside** the bus (controllers, ApiPlatform state providers, event listeners, legacy code) take a different path (typically the `request` or `app` channel) but converge on the same shape thanks to the **platform processors** (registered globally, see [§7](#7-cross-channel-correlation-uidprocessor)):

```mermaid
flowchart LR
    Bus["command.bus / query.bus"] --> LM["LoggingMiddleware"]
    LM --> EFP["ExceptionFormatterProcessor (app channel)"]
    EFP --> H["HTTP / security processors (Web, Route, Token)"]
    H --> Fmt["LineFormatter (RFC3339)"]
    Fmt --> File["prod.web.log"]

    Req["HttpKernel ErrorListener"] --> ChR["channel request"] --> EFP
    App["$logger->error(..., ['exception' => $e])"] --> ChA["channel app"] --> EFP

    classDef src fill:#f5f5f5,stroke:#9e9e9e,color:#212121
    classDef proc fill:#e0e0e0,stroke:#616161,color:#212121
    classDef out fill:#bdbdbd,stroke:#424242,stroke-width:2px,color:#000
    class Bus,Req,App,ChR,ChA src
    class LM,EFP,H,Fmt proc
    class File out
```

Every component lives under `App\Shared\Infrastructure\…` and is wired declaratively in `config.new/packages/messenger.yaml` (middleware) and `config.new/services/shared.php` (processors + formatter).

---

## 2. Middleware chain on `command.bus`

```
validation → LoggingMiddleware → DoctrineTransactionMiddleware → handler
```

- `validation` (Symfony vendor) — runs the Symfony Validator constraints carried by the commands.
- `App\Shared\Infrastructure\Messenger\LoggingMiddleware` — see [§4](#4-loggingmiddleware).
- `App\Shared\Infrastructure\Messenger\DoctrineTransactionMiddleware` — see [§3](#3-doctrinetransactionmiddleware).
- handler — `#[AsCommandHandler]`.

The insertion of `LoggingMiddleware` **before** the transactional middleware is carried by the YAML wiring in `config.new/packages/messenger.yaml`:

```yaml
command.bus:
    middleware:
        - validation
        - App\Shared\Infrastructure\Messenger\LoggingMiddleware
        - App\Shared\Infrastructure\Messenger\DoctrineTransactionMiddleware
```

Important consequence: a handler-side failure is logged **after** the rollback has already happened — the failure log reflects the final persistent state.

```mermaid
flowchart LR
    HTTP[HTTP / CLI] --> V[validation<br/>middleware]
    V --> LB["LoggingMiddleware<br/>before: log <i>info</i>"]
    LB --> DTB["DoctrineTransactionMiddleware<br/>beginTransaction"]
    DTB --> H[Command Handler]
    H -- ok --> DTC["DoctrineTransactionMiddleware<br/>commit"]
    DTC --> LAok["LoggingMiddleware<br/>after: log <i>info</i>"]
    LAok --> OK[2xx]
    H -- throw --> DTR["DoctrineTransactionMiddleware<br/>rollBack"]
    DTR --> LAerr["LoggingMiddleware<br/>log <i>error</i>"]
    LAerr --> ERR[4xx / 5xx]

    classDef ok fill:#bdbdbd,stroke:#424242,stroke-width:2px,color:#000
    classDef err fill:#f5f5f5,stroke:#424242,stroke-dasharray:5 5,color:#212121
    class OK ok
    class ERR err
```

`query.bus` carries the same chain **without** the `DoctrineTransactionMiddleware` — reads must not open a transaction.

Domain events are dispatched by the handler **after** `persist()` returns but **before** the middleware commit. The `EventBus` is synchronous in-process, so a subscriber that reads the repository sees the persisted state, and a subscriber that throws causes the middleware transaction to be rolled back. If a subscriber must wait for the parent bus commit (async dispatch, external call), wrap its message with `DispatchAfterCurrentBusStamp` rather than reintroducing a custom transactional runner.

---

## 3. `DoctrineTransactionMiddleware`

`App\Shared\Infrastructure\Messenger\DoctrineTransactionMiddleware` is the component that makes a command **atomic at the DB level**. Effective implementation:

```php
public function handle(Envelope $envelope, StackInterface $stack): Envelope
{
    $this->connection->beginTransaction();

    try {
        $envelope = $stack->next()->handle($envelope, $stack);
        $this->connection->commit();

        return $envelope;
    } catch (\Throwable $th) {
        $this->connection->rollBack();

        throw $th;
    }
}
```

Key points:

- **Wired on `command.bus` only.** Queries do not benefit from it (no transaction on pure reads), and this is by design: `query.bus` handlers must not write anything.
- **DBAL connection.** The middleware is autowired on `doctrine.dbal.default_connection`. Legacy repositories that go through direct PDO (cf. the dual Centreon connection — `ContactRepositoryRDB`, `DbReadAccessGroupRepository`) are **not covered** by this transaction. Any transactional write must therefore go through DBAL to commit alongside the rest of the command.
- **Granularity = 1 command = 1 transaction.** All mutations emitted by a handler (through several aggregate repositories or several calls on the same one) commit or roll back as a block. This is the guarantee that frees handlers from any `beginTransaction` / `commit` boilerplate.
- **On throw.** The rollback is complete then the exception is propagated to the next middleware — concretely the `LoggingMiddleware`, which logs the error **after** the rollback. The log therefore reflects the final persistent state, not the "transaction in progress" state.
- **No nesting.** Wiring a second transactional middleware or reopening a transaction from the handler does **not** create a nested transaction — DBAL handles this with a counter, but the inner `commit` is a no-op. If a write path must live outside the bus (CLI, batch), introduce a `TransactionalRunnerInterface` port at that boundary rather than reinventing the transaction on the handler side.

---

## 4. `LoggingMiddleware`

> [!WARNING]
> **Middleware scope = Messenger bus only.** `LoggingMiddleware` only logs `command.bus` / `query.bus` dispatches. Exceptions raised **outside** a dispatch (controllers, ApiPlatform state providers, event listeners, legacy code) do **not** go through this middleware. They are handled by other paths:
>
> - **`request` channel** — Symfony's `HttpKernel\EventListener\ErrorListener` automatically captures and logs any unhandled exception bubbling up to the HTTP kernel.
> - **`app` channel** — any application service that calls `$logger->error('…', ['exception' => $e])` directly.
>
> Uniform coverage is guaranteed not by this middleware but by the **processors registered globally on every channel** — see [§6](#6-http--security-processors-web-route-token). Any error, regardless of its entry point into Monolog, traverses `ExceptionFormatterProcessor` (shape `{exceptions: [{type, message, code, file, line, trace}, …]}`) and `WebProcessor` / `RouteProcessor` / `TokenProcessor` (HTTP / security enrichment).
>
> Assumed blind spots: dedicated channels in the `!exclude` list of `web_finger_crossed` (`event`, `doctrine`, `console`, `deprecation`, `authentication`, `token`, `password`, `plugin-pack-manager`, `upgrade`), the `console` channel in CLI, and PHP fatals before kernel boot (parse error, OOM).

The middleware emits a record on the Monolog `app` channel (Symfony default) for every dispatch:

| Event | Level | Message | Context |
|-------|-------|---------|---------|
| Before `next()` | `info`  | `Dispatching {bus_type} {handler_message}` | `dispatch_id`, `bus_type`, `handler_message`, `payload` |
| Clean return  | `info`  | `Handled {bus_type} {handler_message}`     | `dispatch_id`, `bus_type`, `handler_message`, `handlers`, `duration_ms` |
| Throw          | `warning` / `critical` | `Failed to handle {bus_type} {handler_message}` | `dispatch_id`, `bus_type`, `handler_message`, `duration_ms`, `payload`, `exception` |

- **`dispatch_id`**: a non-cryptographic, time-based correlation id generated per dispatch (`uniqid('', true)`) — a log correlator, not a secret, so no CSPRNG is needed. Identical on the three emits of a single `handle()`, different from one dispatch to the next. Lets you pair-match Dispatching ↔ Handled / Failed in log search when the `app` channel is saturated with traffic from other interleaved dispatches.
- **`bus_type`**: raw bus name read from the envelope's `BusNameStamp` (`command.bus`, `query.bus`, `event.bus`, …), or `unknown` if no stamp is attached.
- **`handler_message`**: FQCN of the **message** dispatched (the Command / Query / Event class). The name avoids collision with Monolog's `%message%` (the human-readable log message) and with `context.exception.message` on error — a single ambiguous `message` in the record carried a risk of reader and parser confusion.
- **`handlers`** (Handled only): list of `HandledStamp::getHandlerName()` produced by the handlers that ran. On a single-handler command/query bus, the list has one element; on an event bus with several subscribers, it has several. Empty list if no handler ran (short-circuit). Distinct from the `handler_message` field, which names the **message** class dispatched.
- **`duration_ms`** (Handled and Failed): dispatch duration in milliseconds, measured with `hrtime(true)` (monotonic clock, immune to NTP / DST jumps). Rounded to 3 decimals. Not present on the Dispatching log, which serves as the t0 reference.
- **`payload`**: the message turned into a log-safe array by `App\Shared\Infrastructure\Logging\LogPayloadNormalizer` — a dedicated service that wraps the platform `NormalizerInterface` and applies the constraints needed for a log line: keys containing `password`, `token`, `secret`, `api_key`, `authorization`, `credential` masked as `***`, string values truncated at 1024 characters. No runtime depth cap is enforced: the input is always a strongly-typed Messenger message whose class graph already bounds the depth statically. If normalisation fails or does not produce an array, the payload falls back to `['__class' => $message::class]` and a `warning` is emitted on the `app` channel. Defense-in-depth for values that remain an object after normalisation (no dedicated normaliser for that type): `\BackedEnum` rendered via `->value`, `\UnitEnum` via `->name`, `\DateTimeInterface` via `format(ATOM)`, `\Stringable` via string cast + truncation, and any other plain object rendered as a placeholder `{ClassName}` — no `(array)` cast on objects, to avoid exposing their private properties.
- **Failure level**: a `\InvalidArgumentException` (Centreon's `AssertionException` extends it — a value-object / domain rejection that maps to a 4xx) is logged at `warning`; any other throwable is an unexpected server-side failure (DB down, OOM, bug → 5xx) and is logged at `critical`. This mirrors the `CRITICAL`-vs-`WARNING` split of `LegacyHttpExceptionListener`, so alerting can ignore expected client errors without muting real incidents.

  **Intentionally permissive masking.** Sensitive keywords are matched with `str_contains` after `mb_strtolower`, not exact match. Consequence: any field name that *contains* a keyword is masked, including when the field itself is not sensitive. False-positive examples:
  - `password_changed_at` (timestamp) → masked (contains `password`)
  - `oauth_authorization_url` (public URL) → masked (contains `authorization`)
  - `tokenize_input` (bool flag) → masked (contains `token`)
  - `credential_check_id` (reference id) → masked (contains `credential`)

  This *"over-mask rather than under-mask"* default is intentional: we'd rather lose a bit of debug info than miss a real secret carried by an unlisted variant (e.g. `passwords_v2`, `customer_token_id`). For the opposite cases — a real secret carrying an unlisted name, or keyword noise on a given Command — the explicit, type-safe complement is the `#[Sensitive]` attribute (implemented under [MON-199097](https://centreon.atlassian.net/browse/MON-199097), see [§10](#10-masking-sensitive-fields-with-sensitive)). It is preferred over broadening the keyword list or switching to exact match, which would open the door to forgotten variants.
- **`exception`**: produced by `ExceptionFormatter::format()` (see [§5](#5-exceptionformatter-and-exceptionformatterprocessor)).

---

## 5. `ExceptionFormatter` and `ExceptionFormatterProcessor`

### `ExceptionFormatter`

`App\Shared\Infrastructure\Logging\ExceptionFormatter` is an `abstract readonly` utility with no dependency that turns a `\Throwable` into a loggable `array<string, mixed>`.

Returned shape:

```php
[
    'exceptions' => [
        [
            'type'    => DomainException::class,
            'message' => 'top',
            'code'    => 0,
            'file'    => '/.../Foo.php',
            'line'    => 42,
            'trace'   => ['Foo::bar() at /.../Foo.php:42', /* ... */, '… 7 frames omitted'], // 15 frames max + omission marker
        ],
        [
            'type'    => RuntimeException::class,
            'message' => 'mid',
            'code'    => 0, 'file' => '...', 'line' => 12,
            'trace'   => [/* ... */],
        ],
        [
            'type'    => LogicException::class,
            'message' => 'root cause',
            'code'    => 0, 'file' => '...', 'line' => 5,
            'trace'   => [/* ... */],
        ],
    ],
]
```

- The root exception is the first entry; every `previous` cause that follows is appended in order. The chain is **flat** — no nesting, no recursion.
- `message` is truncated at 1024 characters with the `…[truncated]` suffix beyond that — same cap as `MAX_VALUE_LENGTH` on the payload sanitisation side. Prevents a `PDOException` carrying a long SQL fragment with its parameters from blowing up the width of a log line.
- `trace` is truncated at 15 frames, each frame formatted as `Class::method() at file:line`. If the original stack exceeds this limit, a final `… N frames omitted` entry indicates how many frames were cut (useful in debug to know whether the application call site was lost under Symfony / vendor layers).
- **Uniform shape**: every entry — root, intermediates and the truncation marker — carries the **same six keys** (`type, message, code, file, line, trace`). A consumer (Kibana, custom parser) can iterate `exceptions` with a single shape and no schema branching.
- The chain is capped at 20 entries; beyond that a trailing entry of type `@truncated` (with `code: 0`, empty `trace`, a message stating the cap) signals the cut without re-walking the chain.

Reusable from any error entry point (event listener, API handler, Symfony exception listener…) without going back through the middleware.

### `ExceptionFormatterProcessor`

`App\Shared\Infrastructure\Logging\ExceptionFormatterProcessor` is a Monolog processor registered **globally** (`#[AsMonologProcessor]` without `channel:`) that **defensively** detects a `Throwable` in the `context.exception` slot and applies `ExceptionFormatter::format()` to it. Idempotent on records where the `exception` key is already an array (the `LoggingMiddleware` pre-format case) or absent.

Being global, it guarantees a **uniform exception shape** on every channel (catch-all and dedicated alike), regardless of the log emitter:

- `app` channel — bus dispatch failures already pre-formatted by `LoggingMiddleware`, processor no-op.
- `request` channel — records emitted by Symfony's `ErrorListener` with a raw `Throwable` in `exception`. The processor formats them.
- Any other channel — ad-hoc `$logger->error('…', ['exception' => $e])` calls anywhere in the codebase. Same deal.

---

## 6. HTTP / security processors (Web, Route, Token)

`Symfony\Bridge\Monolog\Processor\WebProcessor`, `RouteProcessor` and `TokenProcessor` are registered in `config.new/services/shared.php` and tagged `monolog.processor` **globally** — same scope as `ExceptionFormatterProcessor` and `UidProcessor`, so every channel carries the same enriched shape.

| Processor | Adds to `extra` |
|-----------|-----------------|
| `WebProcessor` | `url`, `ip`, `http_method`, `server`, `referrer` |
| `RouteProcessor` | `controller`, `route`, `route_params` |
| `TokenProcessor` | `token` (`authenticated`, `roles`, `user_identifier`) |

> [!NOTE]
> **Context is now passed flat.** The legacy logging helpers (`Centreon\Domain\Log\LoggerTrait`, `CentreonLog`) used to wrap every record's context into `{custom, exception, default: {request_infos: {uri, http_method, server}}}` via a per-call `normalizeContext()`. That wrapper is gone: callers' context is forwarded as-is to the logger. The request metadata it used to inject by hand is now provided globally by `WebProcessor` under `extra.url` / `extra.http_method` / `extra.server` (plus `ip`, `referrer`), so it is no longer duplicated per call. Any external consumer that parsed the old nested `context.default.request_infos.*` shape must read `extra.*` instead.

### `UidProcessor` — cross-channel correlation

`Monolog\Processor\UidProcessor` is registered in `config.new/services/shared.php` **with no channel tag** — it therefore applies to **every logger** (request, app, deprecation, authentication, token, password, upgrade, plugin-pack-manager). It generates **a single 7-character hex id per process** and stamps it under `extra.uid` on every record:

```php
$services->set('monolog.processor.uid', UidProcessor::class)
    ->tag('monolog.processor');   // no channel ⇒ every logger
```

**Why a platform-wide UID when we already have `dispatch_id`?**

| Field | Scope | Emitter | Use case |
|---|---|---|---|
| `extra.uid` | whole HTTP / CLI request | `UidProcessor` (Monolog) | Reconstruct every record produced by a request, **across all channels** (catch-all + dedicated files) |
| `context.dispatch_id` | a single bus dispatch | `LoggingMiddleware` | Pair-match Dispatching ↔ Handled / Failed for a single `handle()`, on the records emitted by the middleware |

Concretely, on a single HTTP request that dispatches two commands and also lands in `prod.token.log`:

```
prod.web.log
[…] app.INFO  Dispatching cmd-A {"dispatch_id":"3f8a2c1b9e5d4670",…} {"uid":"89796c2",…}
[…] app.INFO  Handled cmd-A    {"dispatch_id":"3f8a2c1b9e5d4670",…} {"uid":"89796c2",…}
[…] app.INFO  Dispatching cmd-B {"dispatch_id":"a2bf91c45ad7e003",…} {"uid":"89796c2",…}
[…] app.INFO  Handled cmd-B    {"dispatch_id":"a2bf91c45ad7e003",…} {"uid":"89796c2",…}
[…] request.INFO Matched route …                                    {"uid":"89796c2",…}
prod.token.log
[…] token.INFO Token refreshed for user 42                          {"uid":"89796c2",…}
```

An operator runs `grep "uid\":\"89796c2\"" /var/log/centreon/*.log` and gets the full chronology of the request, **including what landed in the MON-151077 dedicated files**.

---

## 7. Log line example

Real output produced by the full chain on the `app` channel (`LoggingMiddleware` + `ExceptionFormatterProcessor` + `WebProcessor` + `RouteProcessor` + `TokenProcessor`, serialised by the `LineFormatter` with an RFC3339 timestamp), for the dispatch of an `UpdateBusinessActivityTreeCommand` (the BAM module serves here as a concrete example of the flow).

`LineFormatter` output format: `[%datetime%] %channel%.%level_name%: %message% %context% %extra%` (one record = one line in `prod.web.log`). Context and extra are serialised inline as JSON; below they're split across multiple lines for readability.

### Nominal case (`info`)

Raw line:

```
[2026-05-06T09:44:19+00:00] app.INFO: Dispatching command.bus App\Module\Bam\MonitoringConfiguration\Application\Command\BusinessActivityTree\UpdateBusinessActivityTreeCommand {"dispatch_id":"3f8a2c1b9e5d4670","bus_type":"command","handler_message":"App\\Module\\Bam\\…\\UpdateBusinessActivityTreeCommand","payload":{"rootBaId":42,"businessActivities":[{"id":100,"name":"Web Frontend","parentId":42,"warningThreshold":75.0}],"indicatorsToAdd":[{"hostId":12,"serviceId":34,"baId":100}],"token":"***","authorization_header":"***"}} {"token":{"authenticated":true,"roles":["ROLE_ADMIN","ROLE_USER"],"user_identifier":"admin"},"requests":[{"controller":"App\\Module\\Bam\\…\\UpdateBusinessActivityTreeProcessor::__invoke","route":"bam_business_activity_tree_patch","route_params":{"rootId":"42"}}],"url":"/api/latest/configuration/business-activities/42/tree","ip":"10.10.0.42","http_method":"PATCH","server":"centreon.example.com","referrer":null}
```

`context` (produced by `LoggingMiddleware`):

```json
{
  "dispatch_id": "3f8a2c1b9e5d4670",
  "bus_type": "command",
  "handler_message": "App\\Module\\Bam\\…\\UpdateBusinessActivityTreeCommand",
  "payload": {
    "rootBaId": 42,
    "businessActivities": [
      {"id": 100, "name": "Web Frontend", "parentId": 42, "warningThreshold": 75.0}
    ],
    "indicatorsToAdd": [
      {"hostId": 12, "serviceId": 34, "baId": 100}
    ],
    "token": "***",
    "authorization_header": "***"
  }
}
```

On the corresponding `Handled` log (success), the context additionally carries `handlers: ["App\\Module\\Bam\\…\\UpdateBusinessActivityTreeCommandHandler::__invoke"]` (single-handler on the command bus), `duration_ms: 42.187` (3 decimals), and reuses exactly the same `dispatch_id`.

Note that `token` and `authorization_header` are already masked by `LogPayloadNormalizer` — not by the processors.

`extra` (produced by the 3 HTTP / security processors):

```json
{
  "token": {
    "authenticated": true,
    "roles": ["ROLE_ADMIN", "ROLE_USER"],
    "user_identifier": "admin"
  },
  "requests": [
    {
      "controller":   "App\\Module\\Bam\\…\\UpdateBusinessActivityTreeProcessor::__invoke",
      "route":        "bam_business_activity_tree_patch",
      "route_params": {"rootId": "42"}
    }
  ],
  "url":         "/api/latest/configuration/business-activities/42/tree",
  "ip":          "10.10.0.42",
  "http_method": "PATCH",
  "server":      "centreon.example.com",
  "referrer":    null
}
```

- `token` ← `TokenProcessor`
- `requests[0]` ← `RouteProcessor` (this key is an array because the processor stacks one entry per HttpKernel sub-request; in the nominal case there is exactly one)
- `url` / `ip` / `http_method` / `server` / `referrer` ← `WebProcessor`

### Error case (`error`)

Here `context.exception` is produced by `ExceptionFormatter::format()` directly inside `LoggingMiddleware`. `ExceptionFormatterProcessor` is a no-op on this record (key already an array) — it would have stepped in if the exception had arrived raw on `request` or `app` (Symfony `ErrorListener`, or an ad-hoc `$logger->error('…', ['exception' => $e])`).

```json
{
  "dispatch_id": "3f8a2c1b9e5d4670",
  "bus_type": "command",
  "handler_message": "App\\Module\\Bam\\…\\UpdateBusinessActivityTreeCommand",
  "duration_ms": 42.187,
  "payload": { /* same as nominal case */ },
  "exception": {
    "exceptions": [
      {
        "type":    "DomainException",
        "message": "UpdateBusinessActivityTree aggregate refused mutation",
        "code":    1001,
        "file":    "/.../UpdateBusinessActivityTreeCommandHandler.php",
        "line":    87,
        "trace": [
          "App\\Module\\Bam\\…\\UpdateBusinessActivityTreeCommandHandler->__invoke() at /.../UpdateBusinessActivityTreeCommandHandler.php:87",
          "Symfony\\Component\\Messenger\\Handler\\HandlersLocator->{closure}() at /.../HandleMessageMiddleware.php:152"
        ]
      },
      {
        "type":    "RuntimeException",
        "message": "failed to load BA #100 children",
        "code":    0,
        "file":    "/.../DbReadBusinessActivityTreeRepository.php",
        "line":    214,
        "trace":   [ "…" ]
      }
    ]
  }
}
```

Key points:

- `exceptions` is a **flat list** (root first, then every `previous` cause in order) — no recursion, iterable with a single shape.
- Every entry carries the same six keys (`type, message, code, file, line, trace`). When the chain exceeds 20 causes, a trailing `{"type":"@truncated", ...}` entry signals the cut.
- The failure record lands on `app.WARNING` or `app.CRITICAL` **after** the `DoctrineTransactionMiddleware` rollback — the persistent state is final at the time the log is emitted.

---

## 8. Processor scope

The four processors (`ExceptionFormatterProcessor`, `WebProcessor`, `RouteProcessor`, `TokenProcessor`) are wired **globally** (no channel tag), like `UidProcessor`. Every channel — catch-all (`app`, `request`, `main`, `security`, …) **and** dedicated (`authentication`, `token`, `password`, `upgrade`, `plugin-pack-manager`, `deprecation`) — therefore carries the same enriched record shape.

In a CLI context (cron, console command, batch), the HTTP-bound processors are still attached but produce empty values on the request-scoped keys — expected behaviour, never problematic noise.

---

## 9. Routing and output file

The whole pipeline (middleware + processors + HTTP processors) converges on **a single Monolog handler** writing to **a single file**. `web_finger_crossed` + `web_file` live at the **top level** of `config.new/packages/monolog.yaml` so the service is resolvable in every env (prod, dev, test):

```yaml
monolog:
    handlers:
        web_finger_crossed:
            type: fingers_crossed
            action_level: error
            excluded_http_codes: [404, 405]   # filters bot scans (/.env, /wp-admin…)
            buffer_size: 50
            stop_buffering: true
            handler: web_file
            channels:
                - "!event"
                - "!doctrine"
                - "!console"
                - "!deprecation"
                - "!authentication"
                - "!token"
                - "!password"
                - "!plugin-pack-manager"
                - "!upgrade"
            bubble: false
            priority: 255
        web_file:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.web.log"
            level: debug
            formatter: monolog.formatter.line
            date_format: !php/const DateTimeInterface::RFC3339
```

**`when@dev` override.** In dev we want every debug+ record immediately (no buffer-and-flush), and a rolling file locally so we don't pile up gigabytes of logs:

```yaml
when@dev:
    monolog:
        handlers:
            web_finger_crossed:
                type: fingers_crossed
                action_level: debug   # ← every record flushes → pass-through
                # ... (same excluded_http_codes, buffer_size, channels)
            web_file:
                type: rotating_file
                max_files: 14
                level: debug
                # ... (same path, formatter, date_format)
```

**RFC3339 driven at the service level.** `config.new/services/shared.php` overrides the `monolog.formatter.line` service with `dateFormat: RFC3339`:

```php
$services->set('monolog.formatter.line', LineFormatter::class)
    ->arg('$dateFormat', DateTimeInterface::RFC3339);
```

Consequence: every handler using `monolog.formatter.line` (centreon-web + any module installed on the new kernel) emits its timestamp in RFC3339 without having to set `date_format:` on each handler.

**Why not `date_format:` at the handler level on `rotating_file`?** On a `rotating_file` handler, the Symfony Monolog Bundle's `date_format:` key is passed to the **`RotatingFileHandler` constructor** where it configures the **filename** date suffix (`Y-m-d` by default). Setting RFC3339 there throws `InvalidArgumentException` at boot. For other types (`stream`, `console`…) the key applies to the formatter — but we choose the single-service approach to stay DRY.

**Exclusive filter (MON-151077 alignment).** Rather than a whitelist `[request, app]`, we use a blacklist of channels that have their own file or that are noise. Everything else — `request`, `app` (Symfony default), `main`, `security`, `http_client`, etc. — lands in `prod.web.log`.

| Excluded channel | Reason |
|------------------|--------|
| `event`, `doctrine`, `console` | Internal Symfony / DBAL noise — not desired in `prod.web.log`. |
| `deprecation` | MON-151077 → dedicated file `prod.deprecations.log`. |
| `authentication` | MON-151077 → merged into `prod.access.log` on the centreon-web side (see the backward-compatibility note below for `login.log`). |
| `token` | MON-151077 → dedicated file `prod.token.log`. |
| `password`, `plugin-pack-manager`, `upgrade` | MON-151077 → dedicated files. Not Monolog channels strictly speaking today (written directly by legacy `CentreonLog` code), but listed in anticipation of a future migration to Monolog. |

> **Backward compatibility — `login.log` is intentionally kept.** Authentication events now flow to the `authentication` channel (`prod.access.log`). To avoid breaking external consumers that parse the historical `/var/log/centreon/login.log` — most notably **fail2ban** jails matching the `Authentication failed for …` line with the client IP — login events are still mirrored to `login.log` in the original pipe-delimited format (`date|uid|page|option|message`) by **two** writers: `LoggerAuthentication::mirrorToLegacyLoginLog()` for events emitted on the new-kernel path (the OpenID/SAML/WebSSO logins routed through the `Login` use case, and the legacy local/LDAP success/failure now routed through the facade), and `CentreonUserLog::insertLog()` for `TYPE_LOGIN` events still flowing through the legacy code path (e.g. `LoginLogger`). The facade writer is try/catch protected so a mirror failure can never break a login; the legacy `CentreonUserLog` writer is not. This duplicate write is **transitional**: it is kept on purpose for client compatibility and will be removed in a future release once consumers have migrated to the Monolog access log (cleanup tracked in a dedicated ticket). `login.log` remains covered by `logrotate/centreon`.

| Property | Effect |
|----------|--------|
| `type: fingers_crossed` + `action_level: error` | On success, `INFO`/`DEBUG` records are buffered in RAM and discarded at the end of the request — zero disk I/O. On the first `ERROR`, the entire buffer plus the triggering record are flushed to the nested handler. |
| `excluded_http_codes: [404, 405]` | The `HttpCodeActivationStrategy` wraps the activation: if the current request returns a 404 or 405, an `ERROR` does not trigger the flush. Prevents bot scans (`/wp-admin`, `/.env`, `/phpinfo`…) from flooding `prod.web.log`. Aligned with the default Symfony recipe. |
| `stop_buffering: true` | After the triggering flush, the handler stops buffering — the rest of the request writes directly. |
| `buffer_size: 50` | Memory cap of the buffer. Beyond that, the `FingersCrossedHandler` **drops the oldest records**. 50 is the MON-151077 target value. |
| `bubble: false` | **The record stops after our handler.** Consequence: HTTP exceptions caught by Symfony's `ErrorListener` (`request` channel) **no longer** bubble up to the host's `main` handler (`var/log/{env}.log`). They live only in `var/log/{env}.web.log`. |
| `priority: 255` | Our handler is executed first in the channel's Monolog stack — combined with `bubble: false`, this guarantees effective isolation. |
| `path: ...{env}.web.log` | In prod, a fixed `prod.web.log` file (rotation is delegated to `logrotate` on production hosts, cf. `logrotate/centreon`). In dev, the handler is `rotating_file` with a daily suffix. |
| `formatter: monolog.formatter.line` | Standard Symfony Monolog Bundle service, redefined in `shared.php` with `dateFormat: RFC3339`. Timestamp format mandated by MON-151077 (e.g. `2025-09-08T15:38:41+02:00`). |

### File rotation

In production, the `prod.*.log` files are rotated by **logrotate** (config `centreon/logrotate/centreon`, deployed to `/etc/logrotate.d/centreon`). The MON-151077 files listed:

- `prod.web.log` (catch-all)
- `prod.deprecations.log`
- `prod.access.log` (authentication)
- `prod.token.log`
- `prod.password.log`
- `prod.upgrade.log`
- `prod.plugin-pack-manager.log`

Default retention: `weekly` × `rotate 52` (1 year), with `compress` + `delaycompress` + `copytruncate`.

In development (`when@dev` in `monolog.yaml`), the handlers use `rotating_file` directly (daily `Y-m-d` suffix, `max_files: 14` retention) — no need for logrotate.

### `prod.web.log` is a two-format file

`prod.web.log` aggregates records from **two writers** sharing the same file, and they don't share the same line shape — by construction, not by choice:

1. **Monolog** (the application framework, `LoggingMiddleware` + processors + every DI-resolved `LoggerInterface`) writes with the `LineFormatter`:

   ```
   [2026-05-20T14:30:00+02:00] app.INFO: Dispatching command.bus App\…\Foo {"dispatch_id":"…"} {"uid":"…"}
   ```

   - RFC3339 timestamp, `channel.LEVEL` prefix, JSON `context` + JSON `extra`.

2. **PHP-FPM** native `error_log` (`packaging/src/php-fpm.{rpm,deb}.conf`, `php_admin_value[error_log] = /var/log/centreon/prod.web.log`) writes whatever PHP cannot route through Monolog — parse errors, fatal pre-boot errors, OOM, `trigger_error(..., E_USER_ERROR)`:

   ```
   [20-May-2026 14:26:47 Europe/Paris] PHP Fatal error:  forced PHP test error in /tmp/foo.php on line 2
   ```

   - Legacy date format (`d-M-Y H:i:s T`), `PHP <Type> error:` prefix, no JSON.

The two formats **cannot be unified at the source**: PHP-FPM does not expose any `error_log_format` directive (the format is hardcoded in the PHP engine, `main/main.c`). What the framework can catch, however, is caught: `framework.php_errors.log: true` routes every runtime PHP `Warning` / `Notice` / `Deprecation` / `ErrorException` through Monolog on the `php` channel, so they land in `prod.web.log` **at the Monolog format**. Only the native pre-Monolog failures (parse error, fatal pre-boot, OOM) keep the PHP-FPM format.

Practical consequence for downstream consumers (log shippers, fail2ban filters, manual `tail`): match the line by **its first character pattern**, not by date:

- `^\[\d{4}-\d{2}-\d{2}T` → Monolog record (RFC3339)
- `^\[\d{2}-[A-Z][a-z]{2}-\d{4}` → PHP-FPM native error

---

## 10. Masking sensitive fields with `#[Sensitive]`

The keyword heuristic of [§4](#4-loggingmiddleware) is permissive but **name-based**: it masks any key *containing* `password`, `token`, … and therefore misses a secret carried by an unlisted name. The `#[Sensitive]` attribute is the **explicit, type-safe** complement — it masks a field by *declaration*, regardless of its name.

### Attribute

`App\Shared\Domain\Logging\Attribute\Sensitive` can target properties, accessor methods, and classes:

```php
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class Sensitive {}
```

- on a **property** — its value is masked;
- on a **method** (typically a getter) — the accessor key it exposes is masked (`getX`/`isX`/`hasX` → `x`, otherwise the raw method name);
- on a **class** — every value typed as that class is masked wholesale, so the sanitizer never descends into it.

Annotate any property whose value must never reach a log — plain properties or promoted constructor parameters:

```php
final readonly class SecretsCommand
{
    public function __construct(
        #[Sensitive] public string $passcode,
        #[Sensitive] public string $ssoTicket,
        public int $userId,
    ) {}
}
```

When such an object flows through the logging pipeline, the annotated values are rendered as `***`, while `userId` stays in clear.

### Pipeline

| Component | Role |
| --- | --- |
| `…\Domain\Logging\Attribute\Sensitive` | the marker placed on properties, accessor methods, and classes |
| `…\Infrastructure\Logging\Attribute\SensitivityScanner` | reflects a class, collects its `#[Sensitive]` properties and accessor keys, honours class-level sensitive types, and **recurses into nested class-typed properties**, so a secret nested in a sub-object is found too |
| `…\Infrastructure\Logging\PayloadSanitizer` | stateless walker that masks the `#[Sensitive]` values of a payload given its owning class (`contextClass`), falls back to the keyword denylist for raw array keys, and truncates string values at `MAX_VALUE_LENGTH` (1024) |
| `…\Infrastructure\Logging\SensitiveKeywordDenylist` | single source of truth for the keyword net (`password`, `token`, …) shared by `LogPayloadNormalizer` and `PayloadSanitizer`, so both nets mask the same field names |
| `…\Infrastructure\Logging\SanitizingProcessor` | Monolog processor that applies the sanitizer to every record |

The owning class is the **context** the sanitizer needs in order to know which keys are sensitive. On the command/query bus, `LoggingMiddleware` provides the dispatched message class, so `#[Sensitive]` on a Command / Query / DTO is honoured automatically. A **raw array** (`['secret' => $x]`) carries no class context, so the attribute layer cannot reflect it — but as the cross-channel net, `PayloadSanitizer` still applies the shared keyword denylist to its keys, so a `['password' => $x]` is masked everywhere it is logged. To get the explicit, type-safe attribute masking outside the bus, pass a typed object rather than a raw array.

### Why not the native `#[\SensitiveParameter]`?

PHP's built-in [`#[\SensitiveParameter]`](https://www.php.net/manual/en/class.sensitiveparameter.php) is **not** a substitute here:

- **Target** — it is `Attribute::TARGET_PARAMETER` only, so it cannot annotate the many plain (non-promoted) **properties** we mask (`Contact::$token`, `Response::$message`, public DTO props such as `FindOpenIdConfigurationResponse::$clientId`, the legacy `Security/Domain/Authentication/Model/*`, …).
- **Mechanism** — it only redacts a value in **exception stack traces** (handled by the PHP engine); it does not mask log payloads. The scanner reflects *properties*, so it would not even see a `#[\SensitiveParameter]` placed on a promoted parameter.

A property-level attribute is therefore the right tool for reflection-based **payload** masking. A future enhancement could make `SensitivityScanner` *additionally* honour `#[\SensitiveParameter]` on promoted constructor parameters (gaining stack-trace redaction for free) while keeping `#[Sensitive]` for plain properties.

---

## 11. Legacy bridge — `Adaptation\Log\Logger`

The platform pipeline described above runs under `App\Shared\Infrastructure\Symfony\Kernel`. Legacy entry points (procedural `www/` code, classes wired through `App\Kernel`) cannot autowire Monolog services directly; they go through a thin façade so every record still lands on the MON-151077 layout.

```mermaid
flowchart LR
    Legacy["Legacy code (www/, CentreonLog, CentreonUserLog)"] --> Facade["Adaptation\\Log\\Logger::create(LogChannelEnum)"]
    Facade --> Adapter["Adaptation\\Log\\Adapter\\MonologAdapter"]
    Adapter --> Stream["StreamHandler → /var/log/centreon/&lt;env&gt;.&lt;slug&gt;.log"]
    Stream --> Fmt["LineFormatter (RFC3339)"]

    classDef src fill:#f5f5f5,stroke:#9e9e9e,color:#212121
    classDef proc fill:#e0e0e0,stroke:#616161,color:#212121
    classDef out fill:#bdbdbd,stroke:#424242,stroke-width:2px,color:#000
    class Legacy src
    class Facade,Adapter proc
    class Stream,Fmt out
```

### `LogChannelEnum`

`Adaptation\Log\Enum\LogChannelEnum` enumerates the Monolog channels the legacy code is allowed to write to. The case value is the **channel name** (matches `config.new/packages/monolog.yaml`); `getLogFileSlug()` returns the **file-name slug** appended to `<env>.<slug>.log`.

| Case | Channel | File slug | Production file |
|---|---|---|---|
| `AUTHENTICATION` | `authentication` | `access` | `prod.access.log` |
| `PASSWORD` | `password` | `password` | `prod.password.log` |
| `PLUGIN_PACK_MANAGER` | `plugin-pack-manager` | `plugin-pack-manager` | `prod.plugin-pack-manager.log` |
| `TOKEN` | `token` | `token` | `prod.token.log` |
| `UPGRADE` | `upgrade` | `upgrade` | `prod.upgrade.log` |
| `WEB` | `web` | `web` | `prod.web.log` |

Only `AUTHENTICATION` carries a different slug — login/ldap/openid/saml records all converge in the shared `access.log` file (cf. MON-151077).

### `Adaptation\Log\Logger`

```php
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;

Logger::create(LogChannelEnum::WEB)->error(
    'Could not generate the host configuration',
    ['host_id' => 42, 'exception' => $e],
);
```

`Logger::create()` returns a PSR-3 `LoggerInterface`. Internally it instantiates a `MonologAdapter` carrying a single `StreamHandler` configured with `LineFormatter` + `RFC3339`. Rotation is **not** wired at this layer — production hosts delegate to `logrotate` (cf. `logrotate/centreon`).

If `MonologAdapter::create()` cannot build the handler (e.g. permission error on the log directory), `Logger::create()` falls back to a `NullLogger` and writes the failure reason to `error_log()` — log emission must never break a request.

#### Processors attached by `MonologAdapter`

To mirror the platform pipeline as closely as possible without booting the Symfony container, `MonologAdapter::pushPlatformProcessors()` attaches three processors to every channel logger it creates:

| Processor | Source | Adds |
|---|---|---|
| `Monolog\Processor\UidProcessor` | Monolog vendor | `extra.uid` — 7-char hex id, **shared across every channel logger built in the current process** via a `private static` cache. Enables cross-file correlation (`grep "uid\":\"…\"" /var/log/centreon/*.log`) just like the new-kernel pipeline. |
| `Monolog\Processor\WebProcessor` | Monolog vendor | `extra.url`, `extra.ip`, `extra.http_method`, `extra.server`, `extra.referrer` — sourced from `$_SERVER` directly (the Symfony bridge variant is bypassed because its data is populated by a kernel-request listener that does not run from legacy code paths). |
| `App\Shared\Infrastructure\Logging\ExceptionFormatterProcessor` | platform | Unwraps `context.exception` through `ExceptionFormatter::format()`, producing the same nested-exception layout used on `prod.web.log` (cf. §5). |

`RouteProcessor` and `TokenProcessor` are **not** wired here: they depend on the Symfony `RequestStack` / `TokenStorage` services and would be empty in the legacy stack. Records emitted via `Adaptation\Log\Logger` therefore do **not** carry `extra.controller`, `extra.route` or `extra.token`. Call sites that need those fields should write through the new-kernel pipeline (the catch-all `app` / `request` channels and every dedicated channel) where the full processor stack is wired globally by `config.new/services/shared.php`.

#### Format alignment — no more `{custom, exception, default}` wrap

Before MON-151077, both `CentreonLog::log()` and `Centreon\Domain\Log\LoggerTrait::executeLog()` reshaped the `context` array before handing it to Monolog:

- `CentreonLog::buildContext()` pre-formatted the `Throwable` with `ExceptionLogFormatter` (legacy) and added `context.request_infos`.
- `LoggerTrait::normalizeContext()` rewrapped everything as `{custom, exception, default: {request_infos}}` — and **moved** the `Throwable` out of `context.exception` so `ExceptionFormatterProcessor` never saw it.

Both helpers are gone. The legacy façades now hand the caller-supplied `$context` directly to the underlying logger, only normalizing one thing: a `?Throwable $exception` argument is moved into `$context['exception']` so the platform processor can unwrap the chain on the way out.

Concrete consequences:

- Exceptions logged via `CentreonLog::create()->error(TYPE_*, 'msg', $ctx, $throwable)` end up with the **same** structured shape as the new-kernel records (handled by `App\Shared\Infrastructure\Logging\ExceptionFormatter`).
- The HTTP context (`url`, `ip`, `http_method`, `server`, `referrer`) lives under `extra`, not `context.request_infos` — that is where the rest of the platform reads it.
- `Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger::log()` still pre-formats through `ExceptionLogFormatter` (legacy) for the `BusinessLogicException` `from_exception` / `traces` fields its callers rely on. Its records therefore keep the legacy `context.exception` shape — that is a property of `ExceptionLogger` callers, not of the underlying logger.

### Domain-specific facades

Four siblings of `Adaptation\Log\Logger` expose a **constrained, semantic API** instead of the raw PSR-3 surface:

| Facade | Channel | File | Methods |
|---|---|---|---|
| `Adaptation\Log\LoggerPassword` | `password` | `prod.password.log` | `success`, `warning` |
| `Adaptation\Log\LoggerToken` | `token` | `prod.token.log` | `success`, `warning` |
| `Adaptation\Log\LoggerAuthentication` | `authentication` | `prod.access.log` | `loginSuccess`, `loginFailure`, `logout`, `tokenRefreshSuccess`, `tokenRefreshFailure`, `unauthorized`, `forbidden` |
| `Adaptation\Log\LoggerUpgrade` | `upgrade` | `prod.upgrade.log` | `start`, `success`, `failure`, `step`, `stepCompleted`, `stepFailure`, `info`, `error` |

They exist for two reasons:

1. **Downstream consumers expect a stable JSON schema** — fail2ban filters (auth), SIEM correlation (password, auth, token), upgrade observability dashboards (upgrade). Hard-coding the schema in the helper guarantees every caller emits the same shape (`event`, `status`, `user_id`, `provider`, `ip_address`, `from_version`, …).
2. **OWASP-aligned semantics** — distinguishing a security event (`WARNING` for an attempted-but-refused login) from a technical crash (`ERROR` for an unhandled exception) is a domain concern; the helper enforces the right Monolog level for each method.

For channels that carry **free-form messages with arbitrary context** (`web`, `plugin-pack-manager`), call sites use `Adaptation\Log\Logger::create(LogChannelEnum::*)` directly — wrapping them in a helper adds no value over the PSR-3 facade.

Decision rule for adding a new `Logger<Channel>` class: when the channel either feeds an automated consumer that requires a stable schema, **or** carries a well-defined lifecycle (`loginSuccess` / `loginFailure` / `logout`, or `start` / `step` / `success` / `failure`) that benefits from semantic method names. Otherwise, route through `Adaptation\Log\Logger::create(LogChannelEnum::*)` directly.

### `CentreonLog` and `CentreonUserLog` façades

Both classes (`www/class/centreonLog.class.php`) keep their public surface but reroute every write through `Adaptation\Log\Logger`. The legacy `TYPE_*` identifiers map onto channels as follows:

| Legacy constant | Channel | Output file |
|---|---|---|
| `TYPE_LOGIN`, `TYPE_LDAP` | `AUTHENTICATION` | `prod.access.log` |
| `TYPE_SQL`, `TYPE_BUSINESS_LOG` | `WEB` | `prod.web.log` |
| `TYPE_UPGRADE` | `UPGRADE` | `prod.upgrade.log` |
| `TYPE_PLUGIN_PACK_MANAGER` | `PLUGIN_PACK_MANAGER` | `prod.plugin-pack-manager.log` |

`CentreonLog::pushLogFileHandler()` and `setPathLogFile()` are kept as deprecated no-ops for backward compatibility with extension modules — file routing is now driven exclusively by `LogChannelEnum`.

Both classes are tagged `@deprecated`. The DI/Application code that still autowires them keeps working, but **new code should call `Adaptation\Log\Logger` directly** with an explicit `LogChannelEnum`.

### `Centreon\Domain\Log\LoggerTrait`

`LoggerTrait` (~389 callers) keeps providing the PSR-3 helper shape used by Domain services that autowire a `LoggerInterface` via `#[Required]`. The pre-MON-151077 `ContactForDebug` gate is gone — `canBeLogged()` now only checks that a logger has been injected. The trait carries a descriptive note but no formal `@deprecated` tag, because `Symfony\Component\ErrorHandler\DebugClassLoader` would otherwise cascade the deprecation onto every class still using the trait during the transition.

### Symfony-side configuration

`config/packages/monolog.yaml` (legacy kernel) is a single-line `imports:` pointing at `config.new/packages/monolog.yaml`. There is one source of truth for channels, handlers and processors — adding or renaming a channel for the platform pipeline automatically reaches the legacy kernel too.

Both kernels resolve `kernel.logs_dir` to `/var/log/centreon` (`App\Kernel::getLogDir()` and `App\Shared\Infrastructure\Symfony\Kernel::getLogDir()`), so the path templates in `monolog.yaml` produce identical filenames regardless of which kernel boots a request.

---

## 12. Writing authentication events

Authentication is the only flow where the platform splits two destinations on purpose, following the [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html) and [ASVS V7](https://owasp.org/www-project-application-security-verification-standard/) recommendations:

- **Application Security Events** → `prod.access.log` (channel `authentication`) — login success/failure, logout, token refresh, unauthorized, forbidden. Consumed by SOC / SIEM / fail2ban.
- **Application Error Logs** → `prod.web.log` (channel `web`) — exceptions, protocol diagnostics, dependency errors.

Every auth provider routes through this split. The new kernel uses Symfony DI (`#[Autowire(service: 'monolog.logger.authentication')]`), the legacy side uses the `LoggerAuthentication` facade.

### Facade API

```php
use Adaptation\Log\Enum\AuthProviderEnum;
use Adaptation\Log\LoggerAuthentication;

LoggerAuthentication::create()->loginSuccess($message, $userId, $provider);
LoggerAuthentication::create()->loginFailure($message, $userId, $provider, $exception);
LoggerAuthentication::create()->logout($message, $userId, $provider);
LoggerAuthentication::create()->tokenRefreshSuccess($message, $userId, $provider);
LoggerAuthentication::create()->tokenRefreshFailure($message, $userId, $provider, $exception);
LoggerAuthentication::create()->unauthorized($message, $userId);
LoggerAuthentication::create()->forbidden($message, $userId, $resource);
```

| Method | Monolog level | When to use |
|---|---|---|
| `loginSuccess($message, $userId, $provider)` | `INFO` | Credentials accepted, session opened. |
| `loginFailure($message, $userId, $provider, $e)` | `WARNING` | Refused login: bad credentials, IP blacklisted, claim missing, IdP error. **WARNING, not ERROR** — this is an expected security event, not a crash. |
| `logout($message, $userId, $provider)` | `INFO` | Session ended. |
| `tokenRefreshSuccess($message, $userId, $provider)` | `INFO` | OIDC refresh token exchanged. |
| `tokenRefreshFailure($message, $userId, $provider, $e)` | `WARNING` | Refresh refused or token endpoint error during refresh. |
| `unauthorized($message, $userId)` | `WARNING` | Authenticated user not allowed to reach a resource (HTTP 401 outside of login). |
| `forbidden($message, $userId, $resource)` | `WARNING` | Authenticated user denied on a specific resource (HTTP 403). |

`$userId` is the Centreon `contact_id` (int). Pass `null` when the user is not yet resolved (early OpenID failure before the `userinfo` exchange, IP blacklist hit before authentication, …). `$provider` is an `AuthProviderEnum` case: `LOCAL`, `LDAP`, `OPENID`, `SAML`, `WEB_SSO`, `API_TOKEN`, `AUTOLOGIN`, `CLAPI`.

### Context structure

Every method emits the same JSON context shape:

```json
{
  "event": "login.success" | "login.failure" | "logout" | "token.refresh.success" | "token.refresh.failure" | "unauthorized" | "forbidden",
  "status": "success" | "failure",
  "user_id": 42,
  "ip_address": "10.0.0.42",
  "provider": "openid",
  "exception": { ... },
  "resource": "host:42"
}
```

- **Always present:** `event`, `status`, `user_id` (may be `null` before the user is resolved), and `ip_address` — which is the literal string `"unknown"` when `$_SERVER['REMOTE_ADDR']` is unset, notably for CLI / CLAPI events.
- **Conditional:** `provider` is omitted for `unauthorized` / `forbidden` (these carry no provider); `exception` appears only on the failure methods when a `Throwable` is passed; `resource` appears only on `forbidden` when a resource is supplied.

This shape is the contract — downstream filters and dashboards pin on `event`, `status`, `ip_address` and (where present) `provider`. Do not bypass the facade to add ad-hoc top-level keys.

### Recommended pattern in a provider

Inside the auth providers (`src/Core/Security/Authentication/...`), the rule is:

- **Login failures** → `LoggerAuthentication::create()->loginFailure(...)` at the exact point the decision is taken (just before throwing the SSO exception), where the user is usually not yet resolved (so `user_id` is `null`).
- **Login success** → emitted once by the `Login` use case after `findUserOrFail()` resolves the contact, so the event carries the real `user_id`. It is emitted only for the external providers (OpenID / SAML / WebSSO); local and LDAP success is already logged through the legacy `centreonAuth` path, which is excluded there to avoid a duplicate access-log entry.
- **Technical diagnostics** → `Adaptation\Log\Logger::create(LogChannelEnum::WEB)->info/error(...)` for the surrounding traces (request sent to IdP, JSON decode error, HTTP 5xx). These are not security events; they belong in `prod.web.log`.

```php
foreach ($conditions->getBlacklistClientAddresses() as $blackListedAddress) {
    if (preg_match('/' . $blackListedAddress . '/', $clientIp)) {
        // Security event — SOC needs to see this on prod.access.log.
        LoggerAuthentication::create()->loginFailure(
            'Client IP is blacklisted',
            null,
            AuthProviderEnum::OPENID
        );

        throw SSOAuthenticationException::blackListedClient();
    }
}

try {
    $response = $this->client->request('POST', $tokenEndpoint, ['body' => $data]);
} catch (Exception $exception) {
    // Security event — login refused, SOC needs to see it.
    LoggerAuthentication::create()->loginFailure(
        'Failed to retrieve access token from provider',
        null,
        AuthProviderEnum::OPENID,
        $exception
    );

    // Technical trace — kept on prod.web.log for ops investigation.
    Logger::create(LogChannelEnum::WEB)->error(
        sprintf('[Error] Unable to get Token Access Information:, message: %s', $exception->getMessage())
    );

    throw SSOAuthenticationException::requestForConnectionTokenFail();
}
```

### Other lifecycle events

```php
// Logout — emit when the session is closed (legacy logout button, OIDC end-session callback, SAML SLS).
LoggerAuthentication::create()->logout(
    sprintf("[%s] [%s] Logout for '%s'", $authType, $clientIp, $username),
    (int) $contactId,
    AuthProviderEnum::OPENID
);

// Token refresh — OIDC refresh_token exchanged successfully against the IdP.
LoggerAuthentication::create()->tokenRefreshSuccess(
    'OIDC refresh token exchanged successfully',
    (int) $contactId,
    AuthProviderEnum::OPENID
);
```

### Authorization events

```php
// API endpoint reached without a token, or with an expired one.
LoggerAuthentication::create()->unauthorized(
    'Missing or invalid bearer token',
    null
);

// Authenticated user denied on a specific resource by a voter or an ACL check.
LoggerAuthentication::create()->forbidden(
    'User cannot access host resource',
    (int) $user->getId(),
    'host:42'
);
```

### Application layer (DDD)

The DDD scope (`src/App/...`) calls `LoggerAuthentication` directly the same way as the legacy code — Application is allowed to depend on `Adaptation\Log\*` because that namespace is the platform-side wrapper, not a third-party I/O. The upgrade flow follows the same convention: both `UpdateCommandHandler` and the update repositories call `LoggerUpgrade` directly at each step (see [§13](#13-writing-upgrade-scripts-update-php)).

### Sample output

`prod.access.log`:

```
authentication.INFO: [local] [172.18.0.1] Authentication succeeded for 'admin'
  {"event":"login.success","status":"success","user_id":1,"provider":"local","ip_address":"172.18.0.1"}

authentication.WARNING: [local] [172.18.0.1] Authentication failed for 'admin' : invalid credentials
  {"event":"login.failure","status":"failure","user_id":null,"provider":"local","ip_address":"172.18.0.1"}

authentication.WARNING: No authorization code returned from external provider
  {"event":"login.failure","status":"failure","user_id":null,"provider":"openid","ip_address":"10.0.0.42"}

authentication.WARNING: Client IP is blacklisted
  {"event":"login.failure","status":"failure","user_id":null,"provider":"web-sso","ip_address":"10.0.0.42"}
```

> The `[local]` success/failure lines above originate from the legacy `centreonAuth` path (`CentreonUserLog` on the `authentication` channel), **not** from `LoggerAuthentication::loginSuccess()`: the `Login` use case deliberately skips local/LDAP to avoid a duplicate entry (see [§12 above](#recommended-pattern-in-a-provider)). The `openid` / `web-sso` lines are the ones emitted through the facade.

`prod.web.log` (same request, technical trace):

```
app.INFO: Start authenticating user... {"provider":"openid"}
app.ERROR: No authorization code returned from external provider {"provider":"openid"}
app.ERROR: An error occurred during authentication {"trace":"…SSOAuthenticationException…"}
```

### fail2ban compatibility

The legacy local/LDAP messages keep their historical pattern (`[local] [<ip>] Authentication succeeded for '<alias>'` / `Authentication failed for '<alias>' : <reason>`); the facade does not rewrite the message — the caller passes it verbatim as the first argument and only enriches the context.

But fail2ban jails are bound to a **file path** (`logpath = /var/log/centreon/login.log`), not to a message pattern: routing the events to `prod.access.log` alone would silently break existing jails. To avoid that, `LoggerAuthentication` also mirrors `loginSuccess` / `loginFailure` to the historical `login.log` in the original pipe-delimited format (`date|uid|page|option|message`), reproducing exactly what the legacy `centreonAuth` emitted through `CentreonUserLog::insertLog(TYPE_LOGIN)`. This duplicate write is transitional and will be removed in a future release once consumers read the Monolog access log instead. The same shim exists on the legacy path (`CentreonUserLog::insertLog`) for events still emitted through it (e.g. `LoginLogger`).

### Best practices

- **One decision point = one `loginSuccess` / `loginFailure` call.** No "summarising" loop log; emit the event where the throw / accept actually happens.
- **`WARNING`, not `ERROR`, for refused logins.** A bad password is a security event, not a crash. ERROR is reserved for true technical failures.
- **No PII, no secrets** in the message or the context. Mask tokens, redact passwords. The file is shipped to SOC pipelines.
- **`$userId` is `null` early, populated once resolved.** Don't fabricate a fake id (e.g. `0`) — `null` is the signal that the user was not authenticated at this stage.
- **Provider always set.** Use the `AuthProviderEnum` cases; do not pass a free-form string. The enum is the single source of truth of which providers exist.
- **Don't duplicate on both channels.** A security event goes to `prod.access.log` via the facade. The technical trace (stack trace, ldap_error, HTTP body) belongs to `prod.web.log` via `Logger::create(LogChannelEnum::WEB)`.

---

## 13. Writing upgrade scripts (`Update-*.php`)

Each upgrade ships under `www/install/php/Update-<version>.php` (DB DDL/DML + business migration) and runs inside `DbWriteUpdateRepository` (legacy kernel) or `DbalUpdateRepository` (DDD kernel). Both bracket the `php_script` step around the inclusion through a small `runStep()` / `executeStep()` helper that calls `LoggerUpgrade` inline (start / completed / failure). **Inside the script itself, trace each meaningful action through `LoggerUpgrade` so operators get a play-by-play view in `prod.upgrade.log`.**

The legacy web upgrade splits the flow across two steps: `process_step4.php` runs the version updates (`runUpdate`), `process_step5.php` runs the post-update (`runPostUpdate`) under a `post_update` step — grep both when tracking a step name.

### Facade API

```php
use Adaptation\Log\LoggerUpgrade;

LoggerUpgrade::create()->info($version, "Adding column X to table Y");
LoggerUpgrade::create()->error($version, "Schema check failed", $exception);

LoggerUpgrade::create()->stepFailure($message, $version, $stepName, $exception);
LoggerUpgrade::create()->step($version, $stepName, $message);
```

| Method | Monolog level | When to use |
|---|---|---|
| `start($from, $to)` | `INFO` | Begin of the global upgrade flow (emitted by `UpdateCommandHandler` / `process_step4.php`, **not** by individual scripts). |
| `success($from, $to, $durationMs)` | `INFO` | End of the global upgrade flow, with measured duration. |
| `failure($message, $from, $to, $e)` | `ERROR` | Global upgrade aborted (catch-all in the handler). |
| `step($version, $stepName, $message)` | `INFO` | Sub-step **start** / progress (`monitoring_sql`, `php_script`, …). `status: running`. Emitted by the repositories; rarely needed inside an upgrade script. |
| `stepCompleted($version, $stepName, $durationMs, $message)` | `INFO` | Sub-step **completion**. `status: completed`, carries `duration_ms` in the context (not just the message), so completed steps are queryable without parsing text. |
| `stepFailure($message, $version, $stepName, $e)` | `ERROR` | A bracketed step threw. Primarily emitted by the repositories / handler; also used inside an upgrade script's catch block with the `php_script` step name (or `php_script_rollback` when the rollback itself fails). |
| **`info($version, $message)`** | `INFO` | **Trace a meaningful action** (entering a function, finished an `ALTER TABLE`, skipped because already migrated, …). This is the workhorse inside upgrade scripts. |
| **`error($version, $message, $e)`** | `ERROR` | Free-form error inside a script that you choose not to re-throw (rare — usually you re-throw and let the surrounding `try/catch` call `stepFailure`). |

### Recommended pattern

Copy `www/install/php/Update-next.php.tpl` — it is the canonical skeleton and is kept in sync with the flow. Its shape:

- **Wrap each business action in its own callable** and set `$errorMessage` to a human description **before** running it, so the catch-all `stepFailure` reports which action failed.
- **Trace intent and outcome** with `info($version, …)`; log skip branches too — they prove the script is safely re-entrant.
- **Run DML inside a transaction** guarded by `isTransactionActive()`, and log "Rolling back…" only inside the `if` that actually rolls back.
- **On failure, call `stepFailure(...)`** with the `php_script` step name (`php_script_rollback` if the rollback itself fails), then **re-throw**: the global flow (`UpdateCommandHandler` / `process_step4.php`) catches it and writes the final `failure`. A silent return breaks that chain.

### Sample output (`prod.upgrade.log`)

```
upgrade.INFO: Upgrade started from 25.10.0 to 25.11.0
  {"event":"upgrade.start","status":"started","from_version":"25.10.0","to_version":"25.11.0"}
upgrade.INFO: Starting step 'php_script'
  {"event":"upgrade.step","status":"running","version":"25.11.0","step":"php_script"}
upgrade.INFO: Adding vmware_updated field into nagios_server table
  {"event":"upgrade.info","status":"info","version":"25.11.0"}
upgrade.INFO: Step 'php_script' completed in 124ms
  {"event":"upgrade.step_completed","status":"completed","version":"25.11.0","step":"php_script","duration_ms":124}
upgrade.INFO: Upgrade from 25.10.0 to 25.11.0 completed successfully
  {"event":"upgrade.success","status":"success","from_version":"25.10.0","to_version":"25.11.0","duration_ms":4242}
```

On failure, the failing action writes `upgrade.step_failure` (ERROR) and the global flow writes a final `upgrade.failure` carrying the from→to versions and the wrapped exception.

### Best practices

- **One business action = one `info` line** before it runs (intent) and one after (outcome). Skip branches deserve their own line so operators can read the log top-to-bottom without re-deriving control flow.
- **Carry `$version` on every call** — the field is what lets SIEM/Grafana partition the file per release.
- **No PII, no secrets** in the message or the context. The file is shipped to operators / SOC pipelines; the same redaction rules as `prod.access.log` apply.
- **Don't duplicate the surrounding `step` log** — the repository already brackets the script with `step` / `stepFailure`. Inside the script, use `info` / `error` (free-form) and let `stepFailure` be raised by the outer `try/catch`.
- **Idempotent actions still log** — even "skipped because already migrated" is signal: it confirms the migration was applied on a previous run and the script is safely re-entrant.
- **Re-throw on failure**. Returning silently after logging masks the failure from the surrounding `UpdateCommandHandler` / `process_step4` which then misses the final `upgrade.failure` record.
