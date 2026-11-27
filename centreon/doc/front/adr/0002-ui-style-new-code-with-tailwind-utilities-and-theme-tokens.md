# ADR-0002: Style new `packages/ui` code with Tailwind utilities and theme tokens, never raw values

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** UI

## Context

`packages/ui` defines the design system and was styled with `tss-react` (`makeStyles` in `*.styles.ts` files). Since [#7413](https://github.com/centreon/centreon/pull/7413), the package also exposes its [theme tokens](../glossary.md#theme-token) as CSS custom properties in `src/ThemeProvider/tailwindcss.css`, and `src/ThemeProvider/index.tsx` orders the CSS layers `theme, base, mui, components, utilities`, so a [utility class](../glossary.md#utility-class) wins over a MUI style.

The package is where the tokens live, so a raw value here (a hex color, a pixel size) is a token that was never defined, and every consumer inherits it. With no written rule, a new component copies the `makeStyles` of its neighbour.

Two alternatives were weighed:

- **Keep `tss-react` in the package, Tailwind in the applications.** It lost: the package would style its components with values the applications cannot override with the same tokens.
- **The MUI `sx` prop as the primary system.** It lost: it does not read `tailwindcss.css`, and it reproduces per-component style objects.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Style new code with **Tailwind utilities in `className`**. Take every value from the **theme tokens** of `src/ThemeProvider/tailwindcss.css`: `p-4`, not `p-[16px]`; a token color, never a hex value. A value that no token expresses is a **new token** to add to `tailwindcss.css`, not an arbitrary `[...]` value.

## Consequences

- A new token is a change of the design system: it is named, added to `tailwindcss.css` and reviewed as such.
- Existing `makeStyles` code stays until it is touched.
- The rule is enforced in review. Biome does not check class names.

## When to Amend

- The design system stops exposing its tokens as CSS custom properties, or the CSS layer order of `ThemeProvider` changes.
- The team adopts another styling system.
