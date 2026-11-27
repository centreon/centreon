# ADR-0001: Style new `www/front_src` code with Tailwind utilities and theme tokens, never raw values

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

`www/front_src` was styled with `tss-react`: a `makeStyles` call in a `*.styles.ts` file next to each component. Tailwind was integrated in [#7413](https://github.com/centreon/centreon/pull/7413): the design system exposes its [theme tokens](../glossary.md#theme-token) as CSS custom properties in `packages/ui/src/ThemeProvider/tailwindcss.css`, and `ThemeProvider/index.tsx` declares the CSS layers `theme, base, mui, components, utilities`, so a [utility class](../glossary.md#utility-class) wins over a MUI style by design.

Both systems coexist: at the time of writing, 118 `*.styles.ts` files and 361 files calling `makeStyles` remain in `www/front_src` and `packages/ui`. With no written rule, a new component copies the `makeStyles` of its neighbour, and a utility with an arbitrary value (`p-[16px]`, a hex color) bypasses the theme as a raw `makeStyles` value did.

Two alternatives were weighed:

- **Keep `tss-react` for consistency with the existing code.** It lost: it keeps two systems forever, and `makeStyles` values do not come from the theme tokens the design system defines.
- **CSS modules or the MUI `sx` prop as the primary system.** It lost: neither reads the theme tokens of `tailwindcss.css`, and `sx` reproduces the per-component style objects the migration leaves behind.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Style new code with **Tailwind utilities in `className`**. Take every value from the **theme tokens** of `packages/ui/src/ThemeProvider/tailwindcss.css`: `p-4`, not `p-[16px]`; a token color, never a hex value. Use an arbitrary value (`[...]`) only when no token fits.

## Consequences

- Utilities override MUI styles through the CSS layers. When a utility seems not to apply, read the computed style in the browser before falling back to another mechanism: the cause is usually a more specific selector or a wrong token, not the layers.
- Existing `makeStyles` code stays until it is touched.
- The rule is enforced in review. Biome does not check class names.

## When to Amend

- The design system stops exposing its tokens as CSS custom properties, or the CSS layer order of `ThemeProvider` changes.
- The team adopts another styling system.
