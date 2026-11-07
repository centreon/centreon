# HTML escaping in `www/` — per-context guide

This document describes how to escape a user-controlled or database-sourced value in the `www/` output contexts that [ADR-0022](adr/0022-legacy-escape-at-www-html-output.md) does not cover with `HtmlSanitizer` or Smarty's `|escape:'html'` alone.

## Smarty templates outside HTML text

- **Inline `<script>` block:** use `|escape:'javascript'` within a quoted JavaScript string, or `json_encode()` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- **Event-handler attribute (`on*`):** chain `|escape:'javascript'|escape:'html'`. `|escape:'javascript'` alone does not stop the value from closing the attribute.
- **`href` / `src`:** check the URL scheme against an allowlist (`http`, `https`) **and** escape the value as a quoted attribute (`|escape:'html'`).
- **Markup fragment built in PHP** and printed raw by the template: follows the PHP rule of [ADR-0022](adr/0022-legacy-escape-at-www-html-output.md).

## HTML_QuickForm and `select2`

- QuickForm's own elements escape attribute values (an input's value, an option's `value`): pass those raw, including through `setDefaults()`.
- QuickForm prints a `select` option's label and a `static` element's text as is: escape those with `HtmlSanitizer` before handing them to QuickForm.
- Centreon's `select2` writes option and default values raw into the `value` attribute and into an inline script, so a `select2` takes integer ids only. A form that fails validation prints the submitted values back the same way: cast or validate them as integers on the server before the form is rendered again.
- A `select2` label also lands in a JavaScript string, so it must not contain `\` or a line break.
- A `select2`'s pre-selected values, from `getObjectForSelect2()` or its `defaultDatasetRoute` endpoint, are printed as raw `<option>` markup: escape their `text` with `HtmlSanitizer` and keep their `id` an integer.
- Escape the `text` of the `availableDatasetRoute` list with `HtmlSanitizer` too: the dropdown inserts it as text, but the "Select all" button appends it as raw `<option>` markup. The dropdown then shows the label double-escaped, a display bug rather than an XSS.

## `CentreonXML` endpoints

An XML/AJAX endpoint that writes through `CentreonXML` gets **no** protection from `HtmlSanitizer`: `writeElement()`, `text()` and `writeAttribute()` run `html_entity_decode()` on the value. Write the value raw, and escape it where the XML is rendered: an XSL `xsl:value-of` without `disable-output-escaping="yes"`, or a JavaScript text insertion (`textContent`, jQuery `.text()`), never `innerHTML` or `.html()`.
