# Logging — Monolog layout and exception formatting

This document describes what the logging backport brings to `dev-24.10.x`:
the Monolog file layout, the RFC3339 line formatter and the global
exception-formatting processor. Reference: [MON-199096].

> **Scope on this branch.** `dev-24.10.x` has no new kernel / Symfony
> Messenger pipeline, so the `LoggingMiddleware` (bus dispatch logging) and
> the HTTP/security processors (`Web`, `Route`, `Token`, `Uid`) are **not**
> part of this backport. Only the Monolog configuration, the
> `monolog.formatter.line` service and the `ExceptionFormatter` /
> `ExceptionFormatterProcessor` pair are present here.

[MON-199096]: https://centreon.atlassian.net/browse/MON-199096

## Table of contents

1. [Monolog configuration](#1-monolog-configuration)
2. [RFC3339 line formatter](#2-rfc3339-line-formatter)
3. [`ExceptionFormatter` and `ExceptionFormatterProcessor`](#3-exceptionformatter-and-exceptionformatterprocessor)
4. [Output files and rotation](#4-output-files-and-rotation)

## 1. Monolog configuration

The layout lives directly in `config/packages/monolog.yaml` (on `develop` /
the new kernel it is an `imports:` of `config.new/packages/monolog.yaml`;
since this branch has no `config.new/`, the content is inlined here).

A single catch-all `web_finger_crossed` → `web_file` handler captures every
channel except the excluded ones, and routes them to one file. It is declared
at the **top level** so the service resolves in every env (prod, dev, test):

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
        web_file:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.web.log"
            level: debug
            formatter: monolog.formatter.line
```

**Exclusive filter.** Rather than a whitelist, a blacklist excludes channels
that have their own dedicated file or that are internal noise. Everything else
lands in the catch-all (`%env%.web.log`).

| Excluded channel | Reason |
|------------------|--------|
| `event`, `doctrine`, `console` | Internal Symfony / DBAL noise. |
| `deprecation` | Dedicated file `%env%.deprecations.log`. |
| `authentication` | Dedicated file `%env%.access.log`. |
| `token` | Dedicated file `%env%.token.log`. |
| `password`, `plugin-pack-manager`, `upgrade` | Dedicated files. |

**`fingers_crossed` behaviour (prod).**

| Property | Effect |
|----------|--------|
| `type: fingers_crossed` + `action_level: error` | On success, `INFO`/`DEBUG` records are buffered in RAM and discarded at the end of the request — zero disk I/O. On the first `ERROR`, the whole buffer plus the triggering record are flushed. |
| `excluded_http_codes: [404, 405]` | A 404/405 response does not trigger the flush — bot scans (`/wp-admin`, `/.env`…) do not flood the file. |
| `stop_buffering: true` | After the triggering flush, the rest of the request writes directly. |
| `buffer_size: 50` | Memory cap; beyond it the oldest buffered records are dropped. |
| `bubble: false` | The record stops after this handler (no bubbling to other handlers). |

**`when@dev` override.** In dev, `action_level: debug` turns the handler into
a pass-through (every record flushed live) and `web_file` becomes a
`rotating_file` (`max_files: 14`) so local logs do not pile up. Dedicated
channel files are `rotating_file` too.

## 2. RFC3339 line formatter

Every handler uses the `monolog.formatter.line` service. On `develop` it is
declared in `config.new/services/shared.php`; on this branch it is declared in
`config/services.yaml`:

```yaml
monolog.formatter.line:
    class: Monolog\Formatter\LineFormatter
    arguments:
        $dateFormat: !php/const DateTimeInterface::RFC3339
```

Setting RFC3339 once at the service level means every handler emits its
timestamp as e.g. `2025-09-08T15:38:41+02:00` without repeating
`date_format:` on each handler. (`date_format:` is deliberately **not** set at
the handler level: on a `rotating_file` handler the Symfony Monolog Bundle
forwards it to `RotatingFileHandler` as the filename suffix, where RFC3339 is
invalid and throws at boot — cf. #10495.)

## 3. `ExceptionFormatter` and `ExceptionFormatterProcessor`

### `ExceptionFormatter`

`App\Shared\Infrastructure\Logging\ExceptionFormatter` is a dependency-free
utility that turns a `\Throwable` into a loggable `array<string, mixed>`:

```php
[
    'exceptions' => [
        ['type' => DomainException::class, 'message' => 'top',        'code' => 0, 'file' => '/.../Foo.php', 'line' => 42, 'trace' => [/* … */]],
        ['type' => RuntimeException::class, 'message' => 'mid',        'code' => 0, 'file' => '...',          'line' => 12, 'trace' => [/* … */]],
        ['type' => LogicException::class,   'message' => 'root cause', 'code' => 0, 'file' => '...',          'line' => 5,  'trace' => [/* … */]],
    ],
]
```

- The root exception is the first entry; every `previous` cause follows in
  order. The chain is **flat** — no nesting.
- `message` is truncated at 1024 characters (prevents a `PDOException`
  carrying a long SQL fragment from blowing up the log line width).
- `trace` is truncated at 15 frames (`Class::method() at file:line`); a final
  `… N frames omitted` entry signals how many were cut.
- The chain is capped at 20 entries; beyond that a trailing `@truncated` entry
  signals the cut. Every entry carries the **same six keys**
  (`type, message, code, file, line, trace`) so a consumer can iterate with a
  single shape.

### `ExceptionFormatterProcessor`

`App\Shared\Infrastructure\Logging\ExceptionFormatterProcessor` is a Monolog
processor registered **globally** (no channel filter) that detects a
`\Throwable` in the `context.exception` slot and applies
`ExceptionFormatter::format()` to it. It is idempotent: a record whose
`exception` key is already an array (or absent) is returned unchanged.

It is registered as a service in `config/services.yaml` and tagged
`monolog.processor` (on `develop` it is picked up via its `#[AsMonologProcessor]`
attribute through the `App\Shared` service loader, which does not exist on this
branch). Being global, it guarantees a uniform exception shape on every channel
for any emitter — e.g. ad-hoc `$logger->error('…', ['exception' => $e])` calls
or records emitted by Symfony's `ErrorListener`.

## 4. Output files and rotation

In production the `prod.*.log` files are rotated by **logrotate**
(`centreon/logrotate/centreon`, deployed to `/etc/logrotate.d/centreon`):

- `prod.web.log` (catch-all)
- `prod.deprecations.log`
- `prod.access.log` (authentication)
- `prod.token.log`
- `prod.password.log`
- `prod.upgrade.log`
- `prod.plugin-pack-manager.log`

Default retention: `weekly` × `rotate 52` (1 year), with `compress` +
`delaycompress` + `copytruncate`.

In development (`when@dev`) the handlers use `rotating_file` directly (daily
`Y-m-d` suffix, `max_files: 14`) — no logrotate needed.
