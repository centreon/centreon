# ADR-0016: Change one aggregate per command, and let other aggregates react through events

- **Status:** Accepted
- **Date:** 2026-10-21

## Context

An aggregate is one transactional, consistent unit ([ADR-0013](0013-adopt-the-team-building-block-vocabulary.md)), and a command runs in one transaction ([ADR-0014](0014-define-messaging-and-hexagonal-building-blocks.md)). The question is whether a command may change several aggregates.

Three cases were raised against a strict rule:

- Mass changes update many objects of the same type at once.
- Poller creation also creates the Engine and Broker configurations, which can also be created on their own today, and all of it rolls back together.
- Host creation deploys the host's services, while services can also be created on their own.

The answers: if the configurations must roll back with the poller, they are part of the poller aggregate and are changed through its root ([ADR-0012](0012-change-aggregate-internals-through-the-root.md)) once they move to the new architecture. Service deployment need not make host creation fail, so it is not transactional with it and belongs in an event handler. Writing a log after a change is the same: an event, not the same handler.

## Decision

Make a command change **one aggregate**. Changing **several instances of the same aggregate type** in one command is allowed (mass changes). Effects on other aggregates, such as deploying services or writing logs, happen **in event handlers** reacting to the command's event, not in the command handler.

This is the **default rule to tend toward, not an absolute ban**: when a case is genuinely blocked, a command changing several aggregate types is acceptable and is justified in review.

## Consequences

- When two objects must always change and roll back together, they likely belong to the same aggregate, and the change goes through its root.
- A command handler dispatches an event for follow-up work instead of calling other aggregates' repositories.
- Follow-up work in an event handler may run outside the command's transaction. If the command fails after the event left, other patterns (outbox, saga) are needed. They are not needed yet.
- Poller creation with its Engine and Broker configurations is a known existing exception, to fix when it moves to `src/App`.

## When to Amend

- Events are processed asynchronously, and consistency between a command and its follow-ups becomes a real problem (outbox, saga).
- Several exceptions accumulate and show that the aggregate boundaries are wrong.
