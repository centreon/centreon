# Architecture Decision Records

An ADR records one decision taken by the PHP team on the backend architecture: the context, the decision, its consequences and the facts that would reopen it. The existing records are listed in the [index](index.md). The terms they use are defined in the [glossary](../glossary.md).

## Write an ADR

1. Copy [`template.md`](template.md) to `NNNN-<decision-in-kebab-case>.md`, with `NNNN` the next free number.
2. Fill in every section of the template. Keep its headings and their order.
3. Add the record to [`index.md`](index.md), under its topic, in the same pull request.
4. Open a pull request. The record is `Proposed` while it is discussed, and `Accepted` once the team agrees.

## What is expected

- **One decision per record.** Two independent decisions make two ADRs.
- **Title:** the decision, stated as an imperative (`Name a Transformer after the object it produces`), repeated after `ADR-NNNN:` in the heading.
- **Context:** the problem, the current practice and why it is not settled. Name the alternatives weighed and why each lost.
- **Decision:** the rule, in a few imperative sentences. Use bold for the words that carry the rule.
- **Consequences:** what changes, what it costs, the known exceptions, and how the rule is enforced (tooling, or review only).
- **When to Amend:** the facts that would reopen the decision.
- **Sources:** link the pull request or the review comment where the question was raised or settled, not the Jira ticket. Link a Jira ticket only when no pull request exists yet, such as a follow-up. Every link is a clickable Markdown link, never a bare ID.
- **Cross-references:** link other ADRs and glossary terms with relative links (`[ADR-0012](0012-change-aggregate-internals-through-the-root.md)`, `[glossary](../glossary.md#aggregate)`). Define a new term in the glossary rather than in the ADR.
- **Language:** English, short sentences, present tense.

## Change an ADR

- Fix wording, links or facts in place.
- Supersede a decision with a new record rather than rewriting it. Set the old record's status to `Superseded by ADR-NNNN`, and update the index.
