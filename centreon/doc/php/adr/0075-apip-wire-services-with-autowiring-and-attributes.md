# ADR-0075: Wire `src/App` services with autowiring and attributes, not with service definitions

- **Status:** Accepted
- **Date:** 2026-11-16
- **Scope:** APIP

## Context

The `config.new/services/*.php` file of each context loads it with `autowire()` and `autoconfigure()`, and `src/App` classes complete their wiring with attributes: `#[Autowire]` for a named service, an environment variable or a parameter, `#[AutowireLocator]`, `#[AutowireIterator]`, `#[AsTaggedItem]`, `#[AsDecorator]`. The rest of these files, and `monolog.php`, hold parameters and a few definitions: Monolog processors, the `ConnectionConfig` of each database, and the name converter of `LegacyValidationStatusListener`. No record says which form a new service takes.

Two alternatives were weighed:

- **Define services in the configuration files.** It lost: the wiring lives away from the class, and a renamed argument breaks at container compilation only.
- **Leave the form free.** It lost: both forms grow side by side, and a reader must look in two places.

Settled in [#12663](https://github.com/centreon/centreon/pull/12663).

## Decision

**Wire a `src/App` class by autowiring and autoconfiguration**, and **complete it with attributes on the class** when its types are not enough: `#[Autowire(service: …)]`, `#[Autowire(env: …)]`, `#[Autowire(param: …)]`, `#[AutowireLocator]`, `#[AutowireIterator]`, `#[AsTaggedItem]`, `#[AsDecorator]`, `#[AsAlias]`.

**A `config.new/services/*.php` file holds only** the loading of its context, the parameters, and the definitions that an attribute cannot express: a class `src/App` does not own (the Monolog processors, `ConnectionConfig`), a service that another configuration references by id (`monolog.processor.sanitizing`), or a second instance of one class (`upgrade.realtime_connection_config`).

## Consequences

- A class shows its own wiring.
- `LegacyValidationStatusListener` moves its argument to `#[Autowire(service: …)]` when it is touched.
- The rule is enforced in review.

## When to Amend

- A Symfony version changes the attributes, or a wiring needs what an attribute cannot express.
