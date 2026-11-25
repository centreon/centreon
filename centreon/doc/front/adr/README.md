# Architecture Decision Records

An ADR records one decision taken by the frontend team on the React code: the context, the decision, its consequences and the facts that would reopen it. The existing records are listed in the [index](index.md). The terms they use are defined in the [glossary](../glossary.md).

## Write an ADR

1. Copy [`template.md`](template.md) to `NNNN-<scope>-<decision-in-kebab-case>.md`, with `NNNN` the next free number and `<scope>` the record's scope in lowercase (`web` or `ui`).
2. Fill in every section of the template. Keep its headings and their order.
3. Add the record to [`index.md`](index.md), under its topic, in the same pull request. Within a topic, rows are grouped by scope: Web, then UI.
4. Open a pull request. Set the status to `Accepted`, or to `Replaces ADR-NNNN` when the record replaces an earlier one.

## What is expected

- **One decision per record.** Two independent decisions make two ADRs.
- **One scope per record:** `Web` or `UI`, as defined in the [glossary](../glossary.md#scope). A decision that holds for both scopes makes one ADR per scope.
- **Title:** the decision, stated as an imperative (`Decode every API response with a JsonDecoder`), repeated after `ADR-NNNN:` in the heading.
- **Context:** the problem, the current practice and why it is not settled. Name the alternatives weighed and why each lost. End with `Settled in`, linking the pull request or review comment where the question was settled, not the Jira ticket; link a Jira ticket only when no pull request exists yet, such as a follow-up. Every link is a clickable Markdown link, never a bare ID.
- **Decision:** the rule, in a few imperative sentences. Use bold for the words that carry the rule.
- **Consequences:** what changes, what it costs, the known exceptions, and how the rule is enforced (tooling, or review only).
- **When to Amend:** the facts that would reopen the decision.
- **No forward reference:** a record never cites a higher-numbered ADR, except in its `Replaced by ADR-NNNN` status.
- **No cross-scope citation:** a record cites only records of its own scope.
- **Cross-references:** link other ADRs and glossary terms with relative links (`[ADR-0015](0015-web-fetch-through-use-fetch-query-and-use-mutation-query.md)`, `[glossary](../glossary.md#feature)`). Define a new term in the glossary rather than in the ADR.
- **Language:** English, short sentences, present tense.

## Change an ADR

- Fix wording, links or facts in place.
- Replace a decision with a new record rather than rewriting it. Set the new record's status to `Replaces ADR-NNNN` and the old record's status to `Replaced by ADR-NNNN`, and update the index.
