# ADR-0021: Escape user-controlled and database-sourced strings at the point of `www/` HTML output

- **Status:** Accepted
- **Date:** 2026-10-30

## Context

Legacy PHP pages under `www/` — the pages themselves, their templates, and the XML/AJAX endpoints that return markup — build HTML by concatenating strings, and those strings often carry user-controlled or database-sourced values (object names, search terms, form input). Put into the page unescaped, they are a stored or reflected XSS vector.

`www/` has a dedicated helper for this, `HtmlSanitizer` (`www/class/utils/HtmlSanitizer.php`), with a fluent API: `HtmlSanitizer::createFromString($value)->sanitize()->getString()` escapes, and `->removeTags($allowedTags)` strips tags. Its `sanitize()` is `htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. It is already the convention, applied at 70-plus call sites across `www/`, and it supersedes the older `HtmlAnalyzer`, which is `@deprecated` in favour of it.

Even so, escaping is still hand-rolled at the point of use. A [review comment on PR 12334](https://github.com/centreon/centreon/pull/12334) found the poller name escaped with a bare `htmlspecialchars(string: $host['name'], encoding: 'UTF-8')` in `restartPollers.php`, the same pattern as on `develop`. Hand-rolled calls are not more or less safe — the default flags of `htmlspecialchars()` on PHP 8.1+ (`ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401`) match what `sanitize()` does — but they scatter the escaping policy across the codebase, miss the `removeTags()` option, and make a grep for unescaped output harder. This ADR records the existing convention so it is applied consistently.

The rule is for `www/` only. `HtmlSanitizer` is a `www/` class and has no place elsewhere: in `src/App` a response leaves as a DTO serialized by API Platform, and HTML escaping is the frontend's concern; this ADR does not apply there.

## Decision

Escape on **output**, never before storage. User-controlled data is written to the database **raw** — do not `HtmlSanitizer`- or `htmlspecialchars`-it before a write — and is escaped only at the point it is rendered. Escaping before storage corrupts the stored value and still cannot know the rendering context.

In `www/` PHP that emits HTML **text or a quoted attribute** directly (echo or string concatenation), pass every user-controlled or database-sourced string through **`HtmlSanitizer`** before it reaches the output:

- `HtmlSanitizer::createFromString($value)->sanitize()->getString()` to escape.
- `->removeTags($allowedTags)` strips tags but does **not** encode output — apply `->sanitize()` before HTML output, and treat `$allowedTags` as a tag filter, not an XSS-safe allowlist.

This rule covers the HTML text and quoted-attribute contexts only. URL attributes (`href`, `src`), inline `<script>` or `<style>` content, and other JavaScript or CSS contexts are **out of scope**: `htmlspecialchars()` leaves a `javascript:` URL executable, so those values need URL validation or context-specific encoding that this ADR does not define.

Do **not** hand-roll `htmlspecialchars()` or `htmlentities()` at the call site, and do **not** reach for the deprecated `HtmlAnalyzer`.

```php
// Instead of:
$msg .= _('A reload signal has been sent to ' . htmlspecialchars(string: $host['name'], encoding: 'UTF-8'));

// Write:
$msg .= _('A reload signal has been sent to ' . HtmlSanitizer::createFromString($host['name'])->sanitize()->getString());
```

Output rendered through a **Smarty template** is escaped by Smarty, not on the PHP side: apply the `|escape` (`|escape:'html'`) modifier to the variable in the template, and pass the raw value from PHP. Do **not** pre-escape a Smarty-bound value with `HtmlSanitizer` — that double-escapes. There is no global auto-escape (the `SmartyBC::createSmartyTemplate` factory sets none), so the modifier is applied per variable, at the point of output.

## Consequences

- The escaping policy lives in one class. A change of flags or encoding happens in `HtmlSanitizer::sanitize()`, not at every call site.
- This records the existing convention; it does not mandate a sweep of existing hand-rolled calls. Apply it to new and touched `www/` output, and improve surrounding code when reasonable.
- `HtmlAnalyzer` stays in place for its remaining callers but takes no new use.
- The rule is enforced in review. A search for `htmlspecialchars`, `htmlentities` or `HtmlAnalyzer` in `www/` is only a heuristic, so a hit is confirmed by reading the code.

## When to Amend

- `www/` HTML rendering is replaced, so the backend no longer emits markup and all output goes through DTOs and the frontend.
- `HtmlSanitizer` is superseded by another escaping helper.
