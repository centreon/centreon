# ADR-0021: Escape user-controlled strings in legacy `www/` HTML output through `HtmlSanitizer`

- **Status:** Accepted
- **Date:** 2026-10-30

## Context

Legacy PHP pages under `www/` — the pages themselves, their templates, and the XML/AJAX endpoints that return markup — build HTML by concatenating strings, and those strings often carry user-controlled or database-sourced values (object names, search terms, form input). Put into the page unescaped, they are a stored or reflected XSS vector.

`www/` has a dedicated helper for this, `HtmlSanitizer` (`www/class/utils/HtmlSanitizer.php`), with a fluent API: `HtmlSanitizer::createFromString($value)->sanitize()->getString()` escapes, and `->removeTags($allowedTags)` strips tags. Its `sanitize()` is `htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. It is already the convention, applied at 70-plus call sites across `www/`, and it supersedes the older `HtmlAnalyzer`, which is `@deprecated` in favour of it.

Even so, escaping is still hand-rolled at the point of use. A [review comment on PR 12334](https://github.com/centreon/centreon/pull/12334) found the poller name escaped with a bare `htmlspecialchars(string: $host['name'], encoding: 'UTF-8')` in `restartPollers.php`, the same pattern as on `develop`. Hand-rolled calls are not more or less safe — the default flags of `htmlspecialchars()` on PHP 8.1+ (`ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401`) match what `sanitize()` does — but they scatter the escaping policy across the codebase, miss the `removeTags()` option, and make a grep for unescaped output harder. This ADR records the existing convention so it is applied consistently.

The rule is for `www/` only. `HtmlSanitizer` is a `www/` class and has no place elsewhere: in `src/App` a response leaves as a DTO serialized by API Platform, and HTML escaping is the frontend's concern; this ADR does not apply there.

## Decision

In `www/` code that emits HTML, pass every user-controlled or database-sourced string through **`HtmlSanitizer`** before it reaches the output:

- `HtmlSanitizer::createFromString($value)->sanitize()->getString()` to escape.
- `->removeTags($allowedTags)` where markup must be stripped rather than escaped.

Do **not** hand-roll `htmlspecialchars()` or `htmlentities()` at the call site, and do **not** reach for the deprecated `HtmlAnalyzer`.

```php
// Instead of:
$msg .= _('A reload signal has been sent to ' . htmlspecialchars(string: $host['name'], encoding: 'UTF-8'));

// Write:
$msg .= _('A reload signal has been sent to ' . HtmlSanitizer::createFromString($host['name'])->sanitize()->getString());
```

## Consequences

- The escaping policy lives in one class. A change of flags or encoding happens in `HtmlSanitizer::sanitize()`, not at every call site.
- This records the existing convention; it does not mandate a sweep of existing hand-rolled calls. Apply it to new and touched `www/` output, and improve surrounding code when reasonable.
- `HtmlAnalyzer` stays in place for its remaining callers but takes no new use.
- The rule is enforced in review. A search for `htmlspecialchars`, `htmlentities` or `HtmlAnalyzer` in `www/` is only a heuristic, so a hit is confirmed by reading the code.

## When to Amend

- `www/` HTML rendering is replaced, so the backend no longer emits markup and all output goes through DTOs and the frontend.
- `HtmlSanitizer` is superseded by another escaping helper.
