# HTML escaping in `www/` — per-context guide

This document describes how to escape a user-controlled or database-sourced value in `www/` PHP output per [ADR-0022](adr/0022-legacy-escape-at-www-html-output.md), including the output contexts that `HtmlSanitizer` or Smarty's `|escape:'html'` alone does not cover.

## PHP output

Escape the value with `HtmlSanitizer` where the markup is built, such as the template names that `listHost.php` links to. Keep the value out of a translated string: pass it through a `%s` placeholder.

```php
// Instead of:
$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$tplStr .= "<a href='main.php?p=60103&o=c&host_id={$key}'>{$value}</a>";

// Write:
$value = HtmlSanitizer::createFromString($value)->sanitize()->getString();
$tplStr .= "<a href='main.php?p=60103&o=c&host_id=" . (int) $key . "'>{$value}</a>";
```

## Smarty templates outside HTML text

- **Inline `<script>` block:** use `|escape:'javascript'` within a quoted JavaScript string, or `json_encode()` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- **Event-handler attribute (`on*`):** chain `|escape:'javascript'|escape:'html'`. `|escape:'javascript'` alone does not stop the value from closing the attribute.
- **`href` / `src`:** check the URL scheme against an allowlist (`http`, `https`) **and** escape the value as a quoted attribute (`|escape:'html'`).
- **Markup fragment built in PHP** and printed raw by the template: follows the [PHP rule](#php-output) for HTML text and quoted attributes; in a script, `on*` or URL context, apply the encoding of the bullets above. Never put a value in a `style` or an unquoted attribute.

## HTML_QuickForm and `select2`

- QuickForm's own elements escape attribute values (an input's value, an option's `value`): pass those raw, including through `setDefaults()`.
- QuickForm prints labels and texts as is (element labels, `header`, `static`, `checkbox` and `radio` text, `select` option labels): escape them with `HtmlSanitizer` before handing them to QuickForm.
- Centreon's `select2` writes option and default values raw into the `value` attribute and into an inline script, so a `select2` takes ids built only from integers (an integer, or integers joined by `-` or `_`). A form that fails validation prints the submitted values back the same way: validate them against that format on the server before the form is rendered again.
- A `select2` label also lands in a JavaScript string, so it must not contain `\` or a line break.
- A `select2`'s pre-selected values, from `getObjectForSelect2()`, a `defaultDataset` array or its `defaultDatasetRoute` endpoint, are printed as raw `<option>` markup: escape their `text` with `HtmlSanitizer` and keep their `id` built only from integers.
- Escape the `text` of the `availableDatasetRoute` list with `HtmlSanitizer` too: the dropdown inserts it as text, but the "Select all" button appends it as raw `<option>` markup. The dropdown then shows the label double-escaped, a display bug rather than an XSS.

## `CentreonXML` endpoints

An XML/AJAX endpoint that writes through `CentreonXML` gets **no** protection from `HtmlSanitizer`: `writeElement()`, `text()` and `writeAttribute()` run `html_entity_decode()` on the value. Write the value raw, and escape it where the XML is rendered: an XSL `xsl:value-of` without `disable-output-escaping="yes"`, or a JavaScript text insertion (`textContent`, jQuery `.text()`), never `innerHTML` or `.html()`.
