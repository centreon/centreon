# Monitoring — Ubiquitous Language

This is the glossary of the **Monitoring** bounded context. It records the
_ubiquitous language_: the exact set of terms the domain model, the code and the
team agree to use when talking about monitoring notifications.

## How to read and maintain this file

- **Scope is this context only.** The same word may mean something different in
  another bounded context (`Security`, `MonitoringConfiguration`, …). A term
  defined here describes the `App\Monitoring` model, nothing else.
- **This file is part of the model.** When you rename a concept, add an
  invariant, or introduce a new aggregate, update this glossary in the _same
  pull request_. Documentation that drifts from the code is worse than none.
- **The code is the source of truth.** Every term links to the class that
  implements it. If the two disagree, the code wins and this file must be fixed.
- Tactical building blocks follow standard DDD vocabulary:
  - **Aggregate / Aggregate Root** — a consistency boundary; the root is the
    only entry point and guards the aggregate's invariants.
  - **Entity** — an object with identity and a lifecycle, living inside an
    aggregate.
  - **Value Object (VO)** — an immutable, identity-less value defined only by
    its attributes; validates its own invariants on construction.
  - **Enumeration** — a closed set of allowed values.
  - **Repository** — the collection-like abstraction used to load and persist an
    aggregate.

---

## Aggregates

### Notification
> **Type:** Aggregate Root ·
> **Class:** `Domain/Aggregate/Notification/Notification.php`

The central concept of this context. A Notification is a configured rule that
decides **who** is alerted, **through which channels**, **when**, and **on which
events**. It is the aggregate root that ties together its recipients
(`UserId` / `UserGroupId`), its `Message`s, its active `TimePeriod` and the
`HostGroupEventEnum` events it reacts to.

| Attribute         | Type                        | Meaning                                                        |
| ----------------- | --------------------------- | -------------------------------------------------------------- |
| `id`              | `NotificationId`            | Identity of the notification.                                  |
| `name`            | `NotificationName`          | Human-readable name.                                           |
| `isActivated`     | `bool`                      | Whether the notification is currently enabled.                 |
| `timePeriod`      | `TimePeriodId`              | The time window during which the notification is allowed.      |
| `userIds`         | `list<UserId>`              | Individual recipients.                                         |
| `userGroupIds`    | `list<UserGroupId>`         | Group recipients.                                              |
| `messages`        | `list<Message>`             | The messages to send, one per channel.                         |
| `hostGroupEvents` | `list<HostGroupEventEnum>`  | Host-group state changes that trigger the notification.        |

### User
> **Type:** Aggregate Root · **Class:** `Domain/Aggregate/User/User.php`

A person who can be a **recipient** of a notification. In this context a User is
intentionally minimal — it carries only an identity and an alias, because
Monitoring only needs to know _whom to notify_, not how to authenticate them
(that concern belongs to the `Security` context).

### UserGroup
> **Type:** Aggregate Root · **Class:** `Domain/Aggregate/UserGroup/UserGroup.php`

A named group of users used as a **collective recipient** of a notification.

### TimePeriod
> **Type:** Aggregate Root · **Class:** `Domain/Aggregate/TimePeriod/TimePeriod.php`

A named time window that constrains _when_ a notification is allowed to fire. In
this context it is referenced by a `Notification` through its `TimePeriodId`.

---

## Entities

### Message
> **Type:** Entity (inside the `Notification` aggregate) ·
> **Class:** `Domain/Aggregate/Notification/Message/Message.php`

The concrete content a Notification sends over a single channel. A Notification
holds a list of Messages — typically one per `MessageChannelEnum`.

| Attribute          | Type                 | Meaning                                                     |
| ------------------ | -------------------- | ----------------------------------------------------------- |
| `id`               | `MessageId`          | Identity of the message.                                    |
| `channel`          | `MessageChannelEnum` | The channel this message is meant for.                      |
| `subject`          | `MessageSubject`     | The message subject.                                        |
| `message`          | `string`             | The raw message body (template as authored).                |
| `formattedMessage` | `string`             | The rendered/formatted body ready to be delivered.          |

> _Note:_ `Message` currently extends `AggregateRoot` in code but is
> conceptually owned by `Notification` (accessed only through it). Treat it as an
> entity of the Notification aggregate.

---

## Value Objects

| Term               | Class                                                     | Definition & invariants                                              |
| ------------------ | --------------------------------------------------------- | -------------------------------------------------------------------- |
| `NotificationName` | `Domain/Aggregate/Notification/NotificationName.php`      | Trimmed name of a notification. Length **1–250**.                    |
| `MessageSubject`   | `Domain/Aggregate/Notification/Message/MessageSubject.php`| Subject line of a `Message`. _(Stub — invariants not yet defined.)_  |
| `UserAlias`        | `Domain/Aggregate/User/UserAlias.php`                     | Trimmed display alias of a `User`. Length **1–200**.                 |
| `UserGroupName`    | `Domain/Aggregate/UserGroup/UserGroupName.php`            | Name of a `UserGroup`. _(Stub — invariants not yet defined.)_        |
| `TimePeriodName`   | `Domain/Aggregate/TimePeriod/TimePeriodName.php`          | Trimmed name of a `TimePeriod`. Length **1–200**.                    |

### Identifiers
> Identity value objects, all extending `App\Shared\Domain\Aggregate\AggregateRootId`.

| Term             | Class                                                    | Identifies      |
| ---------------- | ------------------------------------------------------- | --------------- |
| `NotificationId` | `Domain/Aggregate/Notification/NotificationId.php`      | `Notification`  |
| `MessageId`      | `Domain/Aggregate/Notification/Message/MessageId.php`   | `Message`       |
| `UserId`         | `Domain/Aggregate/User/UserId.php`                      | `User`          |
| `UserGroupId`    | `Domain/Aggregate/UserGroup/UserGroupId.php`            | `UserGroup`     |
| `TimePeriodId`   | `Domain/Aggregate/TimePeriod/TimePeriodId.php`          | `TimePeriod`    |

---

## Enumerations

### MessageChannelEnum
> **Class:** `Domain/Aggregate/Notification/Message/MessageChannelEnum.php`

The delivery channel of a `Message`. Allowed values: **`Email`**, **`Sms`**,
**`Slack`**.

### HostGroupEventEnum
> **Class:** `Domain/Aggregate/Notification/HostGroupEventEnum.php`

A host-group state change that a `Notification` can react to. Allowed values:
**`Up`**, **`Down`**, **`Unreachable`**.

### NotificationPermissionEnum
> **Class:** `Domain/Security/NotificationPermissionEnum.php`

Permission required to operate on notifications. Value:
**`CanReadAndWriteNotifications`** (`can_read_and_write_notifications`).

---

## Repositories

| Term                    | Class                                             | Responsibility                                                        |
| ----------------------- | ------------------------------------------------- | --------------------------------------------------------------------- |
| `NotificationRepository`| `Domain/Repository/NotificationRepository.php`    | `get(NotificationId)` and `add(Notification)`.                        |
| `UserRepository`        | `Domain/Repository/UserRepository.php`            | `findByNotification(NotificationId)` — recipients of a notification.  |
| `UserGroupRepository`   | `Domain/Repository/UserGroupRepository.php`       | Load/persist `UserGroup`. _(Stub.)_                                   |
| `TimePeriodRepository`  | `Domain/Repository/TimePeriodRepository.php`      | Load/persist `TimePeriod`. _(Stub.)_                                  |

---

## Exceptions (domain errors)

| Term                            | Class                                                 | Meaning                                             |
| ------------------------------- | ----------------------------------------------------- | --------------------------------------------------- |
| `NotificationNotFoundException` | `Domain/Exception/NotificationNotFoundException.php`  | No `Notification` exists for the given identity.    |
| `UserNotFoundException`         | `Domain/Exception/UserNotFoundException.php`          | No `User` exists for the given identity.            |

---

## Words to avoid (and what to say instead)

Keeping the language consistent is the whole point of this glossary. Prefer the
domain term on the right.

| Avoid            | Use instead   | Why                                                                                   |
| ---------------- | ------------- | ------------------------------------------------------------------------------------- |
| `Contact`        | **`User`**    | Legacy Centreon wording; this context models recipients as `User`.                    |
| `ContactGroup`   | **`UserGroup`** | Same reason; the DTO/model was renamed from `ContactGroup` to `UserGroup`.          |
| "alert rule"     | **`Notification`** | The aggregate is named `Notification`; use it consistently.                      |
| "template"       | **`Message`** | The thing sent over a channel is a `Message` (with a raw and a formatted body).       |
