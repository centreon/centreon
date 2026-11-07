# ADR-0026: Mark every secret `#[Sensitive]` so the logging pipeline masks it

- **Status:** Accepted
- **Date:** 2026-11-02
- **Scope:** APIP

## Context

`App\Shared\Infrastructure\Messenger\LoggingMiddleware` logs the full payload of every command and query it dispatches, and logs it again when the handler fails. A secret carried by a message, such as a password or a token, therefore reaches the logs unless something masks it.

`App\Shared\Infrastructure\Logging\PayloadSanitizer` masks as `***`:

- fields whose name contains a keyword of `SensitiveKeywordDenylist` (`password`, `token`, `secret`, `api_key`, `credential`, …);
- fields marked with `App\Shared\Domain\Logging\Attribute\Sensitive` ([#10409](https://github.com/centreon/centreon/pull/10409)), on a property, an accessor method or a class;
- the denylisted parameters of a query string inside a URL value.

The attribute is read only when a message payload is logged. The context of a direct log call is masked by the [keyword net](../glossary.md#keyword-net) alone.

The keyword net is name-based, so it misses a secret carried by an unlisted name: an SNMP community, a password macro's value, a poller token's `value`. The attribute is what catches those. It is already applied (`HostMacro`, `ServiceMacro`, `SnmpCommunity`, `PollerToken`, the host write inputs and commands). Nothing states that it is required, so a new secret field is masked only if its author thinks of it.

Three alternatives were weighed:

- **Rely on the keyword net alone.** It lost: it depends on the field's name, and the secrets it misses are exactly the ones named after their business meaning.
- **Use the native `#[\SensitiveParameter]`.** It lost: it only redacts a value in exception stack traces, and the logging pipeline does not read it.
- **Mask by hand at the logging call.** It lost: `LoggingMiddleware` logs the whole payload without any call site, and a manual mask is forgotten as soon as a field is added.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Mark every property or accessor method that carries a secret with `#[Sensitive]`** (`App\Shared\Domain\Logging\Attribute\Sensitive`), even when its name already matches the keyword net.

What counts as a secret is defined in the [glossary](../glossary.md#secret).

Put the attribute on the **property or accessor** that holds the secret, in the command or query that carries it through the bus, in the domain value object or aggregate that holds it, and in the input DTO that receives it. On a private property declared in a parent class, mark its public accessor instead: the attribute on that property is not read for the child class. A class-level attribute alone is not enough: it is not read for the message itself, and a `Stringable` object in a list or behind an interface is cast to a string before its class can be inspected. The input DTO is marked so the secret stays masked if the DTO is ever embedded in a message. When the class itself is the secret (a password value object), mark the class **and** every property or accessor that holds it.

Never put a secret in an exception or assertion message: those are logged unmasked, every message of the `previous` chain included; [ADR-0021](0021-apip-enforce-invariants-with-webmozart-assert.md) covers the `Assert::*` messages. A repository writing a secret catches the database failure and throws an exception with a generic message, **without** the caught exception as `previous`: a driver message can quote the value, as a duplicate-key error does. It is the one exception to the rule of [ADR-0020](0020-apip-let-the-command-bus-own-the-transaction.md) that keeps the caught exception as `previous`.

Do not use `#[\SensitiveParameter]` for this purpose.

## Consequences

- A new secret field without `#[Sensitive]` is a blocking review finding. The rule is enforced in review.
- The attribute protects bus payloads only. A direct log call keeps a secret out by logging identifiers, never the object that carries it.
- Values the platform does not hide stay unmarked. Command arguments, for example, are stored in clear, returned by the API and written to the engine configuration; their exposure in logs is an accepted risk. A secret is passed through a password macro, never inline in the arguments.
- A macro value is masked even when the macro is not a password, because the attribute is static. Losing that debug value is accepted.
- The attribute masks logs only. It does not hide a field from an API response: that is the job of the serialization groups or the `*Output` DTO.

## When to Amend

- The logging pipeline stops logging message payloads, or masks secrets by another mechanism.
- The keyword net becomes type-based and catches every secret on its own.
