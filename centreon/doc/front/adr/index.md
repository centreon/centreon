# ADR index

Decisions taken by the frontend team on the React code, by topic. The terms they use are defined in the [glossary](../glossary.md). To write a new record, see the [README](README.md).

## Guides

The guides show how to apply the records; the code examples live there.

| Guide                                                                                     | Records                                                                    |
| ----------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| [MIGRATING_FROM_CYPRESS.md](../../../test-setup/MIGRATING_FROM_CYPRESS.md)                | 0028, 0029, 0030, 0031, 0032, 0033, 0034, 0035, 0036, 0037, 0038, 0039, 0040, 0041 |

## Styling

| ADR                                                                             | Decision                                                                                       | Scope | Status   |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----- | -------- |
| [0001](0001-web-style-new-code-with-tailwind-utilities-and-theme-tokens.md)     | Style new `www/front_src` code with Tailwind utilities and theme tokens, never raw values      | Web   | Accepted |
| [0003](0003-web-migrate-make-styles-to-tailwind-when-touched.md)                | Migrate the `makeStyles` rules a `www/front_src` change touches to Tailwind, and add none     | Web   | Accepted |
| [0005](0005-web-fall-back-to-mui-props-then-tss-react-never-mui-styles.md)      | Fall back to MUI props, then `tss-react/mui`, only for what Tailwind cannot express            | Web   | Accepted |
| [0007](0007-web-keep-tailwind-class-lists-minimal.md)                           | Keep `www/front_src` Tailwind class lists to the utilities that change the result              | Web   | Accepted |
| [0002](0002-ui-style-new-code-with-tailwind-utilities-and-theme-tokens.md)      | Style new `packages/ui` code with Tailwind utilities and theme tokens, never raw values        | UI    | Accepted |
| [0004](0004-ui-migrate-make-styles-to-tailwind-when-touched.md)                 | Migrate the `makeStyles` rules a `packages/ui` change touches to Tailwind, and add none       | UI    | Accepted |
| [0006](0006-ui-fall-back-to-mui-props-then-tss-react-never-mui-styles.md)       | Fall back to MUI props, then `tss-react/mui`, only for what Tailwind cannot express in `packages/ui` | UI | Accepted |
| [0008](0008-ui-keep-tailwind-class-lists-minimal.md)                            | Keep `packages/ui` Tailwind class lists to the utilities that change the result                | UI    | Accepted |

## Components

| ADR                                                                             | Decision                                                                                       | Scope | Status   |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----- | -------- |
| [0009](0009-web-write-function-components-only.md)                              | Write `www/front_src` components as functions, error boundaries excepted                       | Web   | Accepted |
| [0011](0011-web-default-export-components-and-name-export-the-rest.md)          | Default-export `www/front_src` components, and name-export everything else                     | Web   | Accepted |
| [0013](0013-web-lazy-load-every-route.md)                                       | Lazy-load every route of `www/front_src`                                                       | Web   | Accepted |
| [0014](0014-web-organise-code-by-feature-with-a-fixed-layout.md)                | Organise `www/front_src` code by feature, with a fixed layout                                  | Web   | Accepted |
| [0010](0010-ui-write-function-components-only.md)                               | Write `packages/ui` components as functions, error boundaries excepted                         | UI    | Accepted |
| [0012](0012-ui-default-export-components-and-name-export-the-rest.md)           | Default-export `packages/ui` components, and name-export everything else                       | UI    | Accepted |

## Data

| ADR                                                                             | Decision                                                                                       | Scope | Status   |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----- | -------- |
| [0015](0015-web-fetch-through-use-fetch-query-and-use-mutation-query.md)        | Fetch in `www/front_src` through `useFetchQuery` and `useMutationQuery`, never `useRequest`, axios or `useEffect` | Web | Accepted |
| [0017](0017-web-decode-every-api-response-with-a-json-decoder.md)               | Decode every API response of `www/front_src` with a `JsonDecoder`                              | Web   | Accepted |
| [0019](0019-web-send-a-query-parameter-only-when-it-carries-a-value.md)         | Send a query parameter from `www/front_src` only when it carries a value                       | Web   | Accepted |
| [0016](0016-ui-fetch-through-use-fetch-query-and-use-mutation-query.md)         | Fetch in `packages/ui` through `useFetchQuery` and `useMutationQuery`, never `useRequest`, axios or `useEffect` | UI | Accepted |
| [0018](0018-ui-decode-every-api-response-with-a-json-decoder.md)                | Decode every API response of `packages/ui` with a `JsonDecoder`                                | UI    | Accepted |

## State and forms

| ADR                                                                             | Decision                                                                                       | Scope | Status   |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----- | -------- |
| [0020](0020-web-keep-client-state-in-jotai-atoms.md)                            | Keep `www/front_src` client state in Jotai atoms, never Redux or a new React Context           | Web   | Accepted |
| [0022](0022-web-build-forms-with-form-an-inputs-hook-and-a-yup-schema.md)       | Build `www/front_src` forms with `Form` from `@centreon/ui`, an inputs hook and a Yup schema   | Web   | Accepted |
| [0021](0021-ui-keep-client-state-in-jotai-atoms.md)                             | Keep `packages/ui` client state in Jotai atoms, never Redux or a new React Context             | UI    | Accepted |

## Internationalization

| ADR                                                                             | Decision                                                                                       | Scope | Status   |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----- | -------- |
| [0023](0023-web-translate-through-label-constants.md)                           | Translate `www/front_src` text through label constants, never a string literal in `t()`        | Web   | Accepted |
| [0025](0025-web-interpolate-dynamic-values-with-i18next.md)                     | Put dynamic values in `www/front_src` labels with i18next interpolation, never concatenation   | Web   | Accepted |
| [0027](0027-web-add-every-new-label-to-the-six-po-files.md)                     | Add every new `www/front_src` label to the six `.po` files in the same pull request            | Web   | Accepted |
| [0024](0024-ui-translate-through-label-constants.md)                            | Translate `packages/ui` text through label constants, never a string literal in `t()`          | UI    | Accepted |
| [0026](0026-ui-interpolate-dynamic-values-with-i18next.md)                      | Put dynamic values in `packages/ui` labels with i18next interpolation, never concatenation     | UI    | Accepted |

## Testing

| ADR                                                                             | Decision                                                                                       | Scope | Status   |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----- | -------- |
| [0028](0028-web-write-new-component-tests-with-rstest.md)                       | Write every new `www/front_src` component test as an rstest browser test, never a Cypress spec | Web   | Accepted |
| [0030](0030-web-keep-jest-for-pure-logic.md)                                    | Keep Jest for pure logic in `www/front_src`, never for a component                             | Web   | Accepted |
| [0032](0032-web-make-every-test-assert.md)                                      | Make every `www/front_src` browser test assert                                                 | Web   | Accepted |
| [0034](0034-web-migrate-a-cypress-spec-test-for-test.md)                        | Migrate a `www/front_src` Cypress spec test for test, dropping nothing                         | Web   | Accepted |
| [0036](0036-web-query-elements-by-label-role-or-text.md)                        | Query elements in `www/front_src` tests by label, role or text; a test id is the last resort   | Web   | Accepted |
| [0038](0038-web-snapshot-one-region-and-review-every-snapshot-diff.md)          | Snapshot one region at most in `www/front_src` tests, and update a snapshot only after reviewing its diff | Web | Accepted |
| [0040](0040-web-mock-every-api-call-and-assert-the-sent-request.md)             | Mock every API call of a `www/front_src` component test, and assert the request it sends       | Web   | Accepted |
| [0029](0029-ui-write-new-component-tests-with-rstest.md)                        | Write every new `packages/ui` component test as an rstest browser test, never a Cypress spec   | UI    | Accepted |
| [0031](0031-ui-keep-jest-for-pure-logic.md)                                     | Keep Jest for pure logic in `packages/ui`, never for a component                               | UI    | Accepted |
| [0033](0033-ui-make-every-test-assert.md)                                       | Make every `packages/ui` browser test assert                                                   | UI    | Accepted |
| [0035](0035-ui-migrate-a-cypress-spec-test-for-test.md)                         | Migrate a `packages/ui` Cypress spec test for test, dropping nothing                           | UI    | Accepted |
| [0037](0037-ui-query-elements-by-label-role-or-text.md)                         | Query elements in `packages/ui` tests by label, role or text; a test id is the last resort     | UI    | Accepted |
| [0039](0039-ui-snapshot-one-region-and-review-every-snapshot-diff.md)           | Snapshot one region at most in `packages/ui` tests, and update a snapshot only after reviewing its diff | UI | Accepted |
| [0041](0041-ui-mock-every-api-call-and-assert-the-sent-request.md)              | Mock every API call of a `packages/ui` component test, and assert the request it sends         | UI    | Accepted |

## Shared packages

| ADR                                                                             | Decision                                                                                       | Scope | Status   |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----- | -------- |
| [0042](0042-ui-keep-centreon-ui-backward-compatible-for-the-module-frontends.md) | Keep `@centreon/ui` backward compatible for the module frontends                              | UI    | Accepted |
| [0043](0043-ui-load-federated-entries-with-react-lazy-and-default-exports.md)   | Load federated entries with `React.lazy`, so every exposed entry default-exports its component | UI    | Accepted |

## Tooling

| ADR                                                                             | Decision                                                                                       | Scope | Status   |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----- | -------- |
| [0044](0044-web-let-biome-own-code-style.md)                                    | Let Biome own `www/front_src` code style; no formatting rule in prose                          | Web   | Accepted |
| [0046](0046-web-use-ramda-and-dayjs.md)                                         | Use Ramda and dayjs in `www/front_src`, never lodash, moment or date-fns                       | Web   | Accepted |
| [0045](0045-ui-let-biome-own-code-style.md)                                     | Let Biome own `packages/ui` code style; no formatting rule in prose                            | UI    | Accepted |
| [0047](0047-ui-use-ramda-and-dayjs.md)                                          | Use Ramda and dayjs in `packages/ui`, never lodash, moment or date-fns                         | UI    | Accepted |
