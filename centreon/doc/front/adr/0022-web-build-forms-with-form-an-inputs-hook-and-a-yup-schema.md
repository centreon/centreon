# ADR-0022: Build `www/front_src` forms with `Form` from `@centreon/ui`, an inputs hook and a Yup schema

- **Status:** Accepted
- **Date:** 2026-11-25
- **Scope:** Web

## Context

`@centreon/ui` ships a `Form` component that renders a form from a list of input definitions and validates it with a Yup schema, on top of Formik. The configuration pages use it: 105 files of `www/front_src` import `Form` from the package at the time of writing. `Configuration/HostGroups/Form/` shows the shape: `useFormInputs.tsx` returns the input definitions, `useValidationSchema.ts` the Yup schema, `defaultValues.ts` the initial values, and the submit goes through `useMutationQuery`.

Older forms use Formik directly (`formik` is imported by 182 files, `Form` included) or keep field values in `useState`. An earlier shared rule named the inputs hook `useInputs.ts`; the code has 9 `useFormInputs` against 4 `useInputs`, and the rule was dropped in [dotfiles#159](https://github.com/centreon/dotfiles/pull/159).

Two alternatives were weighed:

- **Raw Formik per form.** It lost: every form re-implements the layout, the error display and the submit state that `Form` already provides, and each one differs.
- **Field state in `useState`.** It lost: validation, dirty state and submission then have to be written by hand, for every form.

Settled in [#12098](https://github.com/centreon/centreon/pull/12098).

## Decision

Build a form with **`Form` from `@centreon/ui`**. Define its inputs in a **`useFormInputs`** hook and its validation in a **`useValidationSchema`** hook returning a **Yup** schema. **Submit through `useMutationQuery`.** No raw Formik, no field state in `useState`, in new forms.

## Consequences

- A new form is three files next to each other, with the same names in every feature.
- A field type `Form` does not offer is added to `Form` in `packages/ui`, not worked around in the feature.
- Existing raw Formik forms are migrated when touched.
- The rule is enforced in review.

## When to Amend

- `Form` is rebuilt on another form library; the hook names then follow it.
