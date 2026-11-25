# ADR-0027: Add every new `www/front_src` label to the six `.po` files in the same pull request

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

The translations of Centreon Web live in `lang/<locale>/LC_MESSAGES/messages.po`, for six locales: `de_DE`, `en_US`, `es_ES`, `fr_FR`, `pt_BR` and `pt_PT`. A [label constant](../glossary.md#label-constant) is a message id; a message id absent from a `.po` file is shown untranslated in that locale. `.github/scripts/translation/make_translation.sh` can extract the message ids with gettext, but nothing runs it in CI, and the periodic `chore(lang): update translations` pull requests catch up on what features left out.

The practice of the recent feature pull requests is the rule below: [#12721](https://github.com/centreon/centreon/pull/12721) and [#12688](https://github.com/centreon/centreon/pull/12688) add each new message id to the six files, with the French translation filled and the other `msgstr` left empty for the translators.

Two alternatives were weighed:

- **Leave every translation to the periodic update.** It lost: between two updates, a release can ship a screen untranslated in every locale, French included.
- **Fill every locale in the feature pull request.** It lost: the developer is not a translator; an empty `msgstr` is a visible task, a wrong one is a silent bug.

Settled in [MON-216175](https://centreon.atlassian.net/browse/MON-216175).

## Decision

A pull request that adds a label **adds its message id to the six `messages.po` files**, runtime-built messages included. The **`fr_FR` entry carries the French translation**; the other locales get an **empty `msgstr`**. A message id already present is not duplicated. Each touched `.po` file passes **`msgfmt -c`**.

## Consequences

- A new label is translated in French when it ships, and visible as untranslated in the other locales to the translators.
- `*.po` files are owned by `@centreon/owners-doc` in `CODEOWNERS`: the pull request gets their review.
- The rule is enforced in review. `msgfmt` comes with gettext and is not run in CI at the time of writing.

## When to Amend

- Message ids are extracted and checked in CI; the manual addition then goes.
- A locale is added or removed.
