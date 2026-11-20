# ADR-0047: Drop the driver exception when a repository write of a secret fails

- **Status:** Accepted
- **Date:** 2026-11-12
- **Scope:** APIP

## Context

[ADR-0026](0026-apip-mark-every-secret-sensitive.md) keeps a secret out of exception messages, because those are logged unmasked, every message of the `previous` chain included. A database failure breaks that rule on its own: a database server message can quote the value it failed to write, as a duplicate-key error does, and DBAL passes it through unchanged. [ADR-0020](0020-apip-let-the-command-bus-own-the-transaction.md) lets a repository translate a DBAL exception into a domain exception, keeping it as `previous`, so a translated failure still logs the driver message.

No `src/App` repository handles this today. `DbalHostRepository` writes the SNMP community and the macro values with no `catch`, so a driver message quoting them reaches the logs.

One alternative was weighed:

- **Keep the driver exception as `previous`, and mask it in the logging pipeline.** It lost: the pipeline cannot tell which part of a free-text driver message is a secret.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302#discussion_r4551239040).

## Decision

**A repository method that writes a [secret](../glossary.md#secret) catches the database failure of that write and throws an exception with a generic message, without the caught exception as `previous`.** The message and the exception's context name the operation and the aggregate's identifier only, never the value.

Apply the rule to every new or touched repository method that writes a secret.

## Consequences

- The secret stays out of the logged exception chain, at the cost of the driver message: a failure is diagnosed from the operation and the identifier, and by reproducing it.
- It is the one exception to [ADR-0020](0020-apip-let-the-command-bus-own-the-transaction.md), which keeps the caught exception as `previous`.
- Existing repositories, such as `DbalHostRepository`, comply when they are touched.
- The rule is enforced in review.

## When to Amend

- MySQL and MariaDB stop quoting values in their error messages.
- The logging pipeline masks driver messages reliably.
