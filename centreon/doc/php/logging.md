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
5. [Writing authentication events](#5-writing-authentication-events)

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

## 5. Writing authentication events

Authentication is the only flow where the platform splits two destinations on purpose, following the [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html) and [ASVS V7](https://owasp.org/www-project-application-security-verification-standard/) recommendations:

- **Application Security Events** → `prod.access.log` (channel `authentication`) — login success/failure, logout, token refresh, unauthorized, forbidden. Consumed by SOC / SIEM / fail2ban.
- **Application Error Logs** → `prod.web.log` (channel `web`) — exceptions, protocol diagnostics, dependency errors.

On this branch every auth provider routes through this split via the `LoggerAuthentication` facade (there is no new-kernel Symfony DI `monolog.logger.authentication` service here).

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

The DDD scope (`src/App/...`) calls `LoggerAuthentication` directly the same way as the legacy code — Application is allowed to depend on `Adaptation\Log\*` because that namespace is the platform-side wrapper, not a third-party I/O. If a particular use case wants strict DIP (Application depending on an abstraction, Infrastructure providing the adapter), define an `AuthenticationLoggerInterface` in `App\<Bounded>\Application\Logger\` and a `LoggerAuthenticationAdapter` in `App\<Bounded>\Infrastructure\Logger\` that delegates to the facade.

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

> The `[local]` success/failure lines above originate from the legacy `centreonAuth` path (`CentreonUserLog` on the `authentication` channel), **not** from `LoggerAuthentication::loginSuccess()`: the `Login` use case deliberately skips local/LDAP to avoid a duplicate entry (see [§5 above](#recommended-pattern-in-a-provider)). The `openid` / `web-sso` lines are the ones emitted through the facade.

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
