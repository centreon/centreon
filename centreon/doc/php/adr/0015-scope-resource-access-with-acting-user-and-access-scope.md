# ADR-0015: Scope resource access with an acting user and an access scope

- **Status:** Accepted
- **Date:** 2026-10-22

## Context

Most resources in `src/App` are restricted by ACL: a user without unrestricted resource access only sees and references what their Access Groups grant. Every read and every write must apply that rule.

Write commands carried both an actor (`int $creatorId`, `$updatedBy`...) and a `?UserId $viewerId`, where `null` meant "admin". That convention spread to the repositories, the listing criteria and every `ResourceAccessRepository` caller, each re-checking for admins with its own ternary. Its failure mode is the wrong one: a forgotten or lost viewer id reads as "admin", so the resource is not scoped and data leaks. The same `null` also stood for "no restriction" in the ACL lookups, with a different meaning.

Alternatives weighed:

- Pass the `Credential` aggregate across bounded contexts. It lost: other contexts would then depend on roles and permissions they don't use, and on an aggregate that Security must stay free to change.
- Let each handler load the credential from a `UserId` in the command. It lost: it adds a lookup per write that the authenticator already did, and it makes every handler depend on Security's repository.
- Keep the nullable viewer and require a check at each call site. It lost: the rule still lives in every caller, and a missing check still fails open.

## Decision

Represent the user performing an action as an **`ActingUser`** value object: their `UserId` and whether they have unrestricted resource access. It is built by `Credential::actingUser()` and is the only part of the credential that reaches the application and domain layers of other bounded contexts (commands, handlers, repositories). Their infrastructure, such as voters and API Platform providers, may still read the authenticated `Credential`.

Write commands carry it as **`$actor`**, built from the authenticated credential. Handlers don't load anything to know who acts.

Represent what a user may access as an **`AccessScope`**: unrestricted, or restricted to some ids. Ask it with `allows()`, `filter()` and `missing()` instead of comparing to `null`. `ResourceAccessRepository::find*AccessScope(UserId)` returns one, and **`AccessScopeResolver`** is the only place where a user with unrestricted access gets an unrestricted scope. A host scope looked up among some hosts only is partial and refuses to list what it allows.

A repository is not handed an `AccessScope`. It is given a criteria carrying the actor and resolves the scope itself, at query time, from the `AccessScopeResolver` injected into it — `$scope = $criteria->accessScope($this->accessScopes->hosts(...), HostId::class)` — then narrows the query with `allows()` / `filter()`. The resolver, not the repository, is where the "unrestricted user" decision is made.

Make listing criteria **fail closed by construction**: an actor-scoped criteria is built with `forActor(ActingUser)` or, for a listing that must see every resource whoever asks, the explicit `unscoped()`. It has no other constructor.

## Consequences

- No `null` means "admin" anymore. A forgotten scoping is a missing argument or a private constructor call, which PHPStan and PHP reject, not a data leak.
- The "admin sees everything" rule lives in one place, `AccessScopeResolver`, instead of in every caller.
- A repository does not receive an `AccessScope`. It is given a criteria and resolves the matching scope itself through the injected `AccessScopeResolver`; it narrows its query but never decides who is an admin.
- The logs of these commands carry their `actor` (`user_id`, `unrestricted_resource_access`) instead of a field per action (`creator_id`, `updated_by`...).
- A listing that legacy never scoped must say so with `unscoped()`, which makes it visible in review.
- `ActingUser` is a snapshot taken at authentication time. A change to a user's ACL during a request is not seen until the next request.
- Exception: `ResourceAccessRepository::hasAccessToPoller()` and `hasAccessToAllPollers()` keep a `UserId` for `InstallationCommandVoter`, which doesn't let users with unrestricted access through. Changing it would change behaviour, so it is left to its own change.
- Only the host write commands follow this rule yet. The poller, command and service category commands, and `DuplicateHostServicesCommand`, still carry a per-action actor id (`$creatorId`, `$updatedBy`, `$deletedBy`, `$duplicatedBy`). They move to `$actor` when they are next changed.
- MonitoringConfiguration and Security still depend on each other through `ResourceAccessRepository`.

## Amendment: services, scoped by Access Group in SQL

- **Date:** 2026-10-28

### Context

A user whose access is restricted sees a service through a (host, service) pair: `centAcl` writes the pairs each Access Group grants into the real-time ACL cache (`centreon_acl`, one row per group, host and service). Listing, deleting or updating services must apply it.

The ids-based scope does not fit. A platform holds more than 100,000 services, so loading every granted pair into an `AccessScope` would be unbounded. The scope is a pair, not an id, so it cannot be an `AccessScope<ServiceId>`. And it would have to be loaded before a query that can sort and page in SQL.

Alternatives weighed:

- Load the granted pairs into memory and filter on them. It lost: unbounded in memory and in query size.
- Page on `centreon_acl` first, then read the services of the page. It lost: the listing sorts by host and service name, which live in the configuration database, so the page cannot be cut on the real-time side.
- Write the `centreon_acl` condition in the repository without any scope. It lost: the "unrestricted user" decision would then be taken in the repository, against this ADR.

### Decision

Scope services by the actor's **Access Groups**: `AccessScopeResolver::serviceAccessGroups(ActingUser)` returns an `AccessScope<AccessGroupId>`. It is unrestricted for a user with unrestricted resource access, without any lookup, and otherwise restricted to the user's active Access Groups, from `ResourceAccessRepository::findServiceAccessGroupScope(UserId)`.

The repository resolves it from the acting user, as for any other scope: through its criteria for a listing (`$criteria->accessScope($this->accessScopes->serviceAccessGroups(...), AccessGroupId::class)`), or from the `ActingUser` it is given for a single service. It then applies it in SQL instead of narrowing with `allows()` or `filter()`: when the scope is restricted, it binds the allowed group ids (`allowedIdValues()`) as an array parameter of an `EXISTS` subquery, which keeps a (host, service) pair only if `centreon_acl` holds a row for one of those groups, that host and that service. When it allows nothing, the repository answers without running the query (an empty listing, or not found).

`centreon_acl` lives in the real-time database. Both connections point to the same database server, so the subquery reads it across databases, prefixed with the real-time database name taken from the `dbcstg` environment variable, as legacy does.

### Consequences

- The "unrestricted user" decision stays in `AccessScopeResolver`. Only the condition on `centreon_acl` lives in SQL.
- Sorting, filtering, counting and paging stay in one SQL query, whatever the number of services.
- Services cannot be checked with `allows()`, `filter()` or `missing()`: the scope lists groups, not services. Only the repository that joins `centreon_acl` can tell whether a service is granted.
- A query on services depends on both databases being on the same server, as the application configuration already assumes: both connections use the same host.
- The `centreon_acl` condition only says whether a (host, service) pair is granted. Which pairs a query considers (direct links, registered hosts and services, host group links) stays the repository's own rule.

## When to Amend

- An anti-corruption layer removes the cycle between MonitoringConfiguration and Security, and moves `AccessScope` or `ActingUser` with it.
- Access must be re-evaluated within a request (long-running or asynchronous commands), so a snapshot taken at authentication time is no longer enough.
- Another resource needs a scope that is not a set of ids, such as a rule evaluated in SQL only. Services are the first such case (see the amendment above).
- The configuration and real-time databases may live on different servers, which breaks the cross-database condition on `centreon_acl`.
