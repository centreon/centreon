# ADR-0022: Escape user-controlled and database-sourced strings at the point of `www/` HTML output

- **Status:** Accepted
- **Date:** 2026-10-30
- **Scope:** Legacy

## Context

Legacy PHP pages under `www/` — the pages themselves, their templates, and the XML/AJAX endpoints that return markup — build HTML by concatenating strings, and those strings often carry user-controlled or database-sourced values (object names, search terms, form input). Put into the page unescaped, they are a stored or reflected XSS vector.

`www/` has a dedicated helper for this, `HtmlSanitizer` (`www/class/utils/HtmlSanitizer.php`), with a fluent API: `HtmlSanitizer::createFromString($value)->sanitize()->getString()` escapes, and `->removeTags($allowedTags)` strips tags. Its `sanitize()` is `htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. It replaces the older `HtmlAnalyzer`, which is `@deprecated` in its favour.

It is not yet the dominant practice. In `www/`, `HtmlAnalyzer::sanitizeAndRemoveTags()` and `htmlentities()` calls far outnumber `HtmlSanitizer` ones. Much of the legacy code sanitizes on **input**, before the write, and its readers print the stored value as is: `listContactGroup.ihtml`, for example, has no `|escape`. A [review on PR 12334](https://github.com/centreon/centreon/pull/12334#pullrequestreview-5709832198) found the poller name escaped with a bare `htmlspecialchars()` in `restartPollers.php`. Hand-rolled calls scatter the escaping policy, and input-side sanitizing corrupts the stored value without knowing the rendering context.

The rule is for `www/` only: `src/App`, `src/Core` and the legacy code outside `www/` are out of scope.

Two alternatives were weighed:

- **Keep sanitizing on input.** It lost: the stored value is altered, it is escaped again or not at all depending on the reader, and the input side cannot know the output context.
- **Turn on Smarty's global auto-escape (`escape_html`).** It lost for now: it double-escapes every value already sanitized on input and breaks templates that print trusted markup, so it needs the input-side sanitizing gone first.

Settled in [#12302](https://github.com/centreon/centreon/pull/12302).

## Decision

**Escape on output.** In new code, write user-controlled data to the database **raw** and escape it only where it is rendered. Before writing raw to a column, check every existing reader that renders it: escape any that prints it unescaped in the same change, or sanitize on input with the same call as the existing write paths, `HtmlAnalyzer::sanitizeAndRemoveTags()` included, so the stored encoding stays uniform.

**On an existing write path that sanitizes on input, keep that sanitizing** unless the same change escapes the value in every reader that renders it (pages, templates, XML/AJAX endpoints). Removing it alone turns the readers' raw output into a stored XSS. A **new reader** of a value still sanitized on input escapes it anyway: a double-escaped quote is a display bug, a missing escape is an XSS.

In `www/` PHP that emits HTML **text or a quoted attribute value** directly (echo or string concatenation), pass every user-controlled or database-sourced string through **`HtmlSanitizer`** before it reaches the output:

- `HtmlSanitizer::createFromString($value)->sanitize()->getString()` to escape.
- To strip tags as well, call `->removeTags($allowedTags)` **before** `->sanitize()`. `removeTags()` alone does not encode, and `$allowedTags` is a tag filter, not an XSS-safe allowlist.

This covers HTML text and quoted attribute values only. Event-handler attributes (`on*`), `style`, URL attributes (`href`, `src`), inline `<script>` or `<style>` content and unquoted attributes are **out of scope**: `htmlspecialchars()` does not make a value safe there. Never put a user-controlled or database-sourced value in them without context-specific encoding; a URL attribute also needs its scheme checked against an allowlist.

Do **not** hand-roll `htmlspecialchars()` or `htmlentities()` at the call site, and do **not** reach for the deprecated `HtmlAnalyzer`, except to match an existing input-side sanitizing. Keep the value out of the translated string, so the translation key does not change:

```php
// Instead of:
$msg .= _('A reload signal has been sent to ' . htmlspecialchars(string: $host['name'], encoding: 'UTF-8'));

// Write:
$msg .= sprintf(
    _('A reload signal has been sent to %s'),
    HtmlSanitizer::createFromString($host['name'])->sanitize()->getString(),
);
```

Output rendered through a **Smarty template** is escaped by Smarty: apply the `|escape:'html'` modifier to the variable in the template, and pass the raw value from PHP. Do **not** pre-escape a Smarty-bound value with `HtmlSanitizer`, which double-escapes. The `SmartyBC::createSmartyTemplate` factory sets no global auto-escape, so the modifier is applied per variable.

How to escape in the other `www/` contexts (inline scripts and event-handler attributes in templates, HTML_QuickForm elements, `select2`, `CentreonXML` endpoints) is described in [html-escaping.md](../html-escaping.md).

## Consequences

- The escaping policy lives in one class. A change of flags or encoding happens in `HtmlSanitizer::sanitize()`, not at every call site.
- There is no sweep of existing calls. Apply the rule to new and touched `www/` output; input-side sanitizing goes away only path by path, with its readers.
- `HtmlAnalyzer` stays in place for its remaining callers. Its only new use is a new write path that must match an existing input-side sanitizing.
- The rule is enforced in review. A search for `htmlspecialchars`, `htmlentities` or `HtmlAnalyzer` in `www/` is only a heuristic, so a hit is confirmed by reading the code.

## When to Amend

- `www/` HTML rendering is replaced, so the backend no longer emits markup and all output goes through DTOs and the frontend.
- `HtmlSanitizer` is superseded by another escaping helper, or Smarty's global auto-escape is turned on.
