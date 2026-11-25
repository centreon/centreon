# Glossary

The frontend team's definitions of the terms its rules use. Use them when designing, naming and reviewing code, and point to them in reviews instead of debating a term again.

A new term, or a change to a definition, goes through a pull request on this page. Decisions that build on these terms live in the [ADRs](adr/index.md).

## Table of contents

1. [Code scopes](#1-code-scopes)
2. [Application building blocks](#2-application-building-blocks)
3. [Styling](#3-styling)
4. [Testing](#4-testing)
5. [Module federation](#5-module-federation)
6. [Not defined yet](#6-not-defined-yet)

---

## 1. Code scopes

### Scope

The part of the frontend code a rule applies to:

- **Web:** the Centreon Web application, `www/front_src`, and the dashboard widgets, `www/widgets`.
- **UI:** the packages published to npm from `packages/`: `@centreon/ui` (`packages/ui`), `@centreon/ui-context` (`packages/ui-context`) and `@centreon/js-config` (`packages/js-config`).

Every [ADR](adr/index.md) states its scope. The frontends of the `centreon-modules` repository are out of both scopes: they consume the UI packages and follow the Web records for new code through their own rules.

### Touched code

The code a pull request adds or changes: a new file, component, hook or function, or an existing line it modifies. Unless a record says otherwise, a rule applies to touched code, and the rest of the existing code follows it when it is next touched.

---

## 2. Application building blocks

### Feature

A directory of `www/front_src/src` that owns one screen or one business area, with its API calls, components, hooks, state and labels. `Configuration/HostGroups` is the canonical one. Its layout is set by [ADR-0014](adr/0014-web-organise-code-by-feature-with-a-fixed-layout.md).

### Endpoint builder

A function of a feature's `api/endpoints.ts` that returns the URL of one API call, with its query parameters. Listing endpoints are built with `buildListingEndpoint` from `@centreon/ui`.

### Decoder

A `JsonDecoder` value from `ts.data.json` that validates the shape of an API response and types it, defined in a feature's `api/decoders.ts`. A response that does not match the decoder is an error, not a value.

### Atom

A Jotai atom: a unit of client state that components read and write through hooks. A feature's atoms live in its `atoms.ts`; the atoms shared by the whole application and the module frontends are exported by `@centreon/ui-context`.

### Label constant

An exported English string of a feature's `translatedLabels.ts`, used as the only argument type of `t()`. It is the message id that the `.po` files translate.

### Inputs hook

A hook, `useFormInputs.ts` in a feature, that returns the input definitions of a `Form` from `@centreon/ui`. Its validation schema is a Yup schema returned by `useValidationSchema.ts`.

---

## 3. Styling

### Utility class

A Tailwind class set in a `className`, such as `flex` or `p-4`. The utilities are loaded in the `utilities` CSS layer, above the `mui` layer, so a utility wins over a MUI style.

### Theme token

A value of the design system exposed as a CSS custom property in `packages/ui/src/ThemeProvider/tailwindcss.css`, such as a color, a spacing or a radius, and used by the utilities. A raw value (a hex color, a pixel size) is not a token.

### Legacy styles

Styles written with `makeStyles` from `tss-react/mui`, usually in a `*.styles.ts` file next to the component. They are the previous styling system, replaced by utilities when touched.

---

## 4. Testing

### Browser test

A component test run by rstest in a real Chromium, with React Testing Library, `user-event` and MSW, in a `*.browser.test.tsx` file. The shared setup lives in `test-setup/` (`@test-setup/api`, `@test-setup/render`).

### Legacy spec

A component test run by Cypress, in a `*.cypress.spec.tsx` file. No new one is written; existing ones are migrated to browser tests.

### Visual snapshot

A screenshot of a rendered component compared with a baseline image. Legacy specs take them with `cy.makeSnapshot()`. Browser tests do not take screenshots.

### API mock

A handler that answers a request sent by the component under test, registered with `interceptAPIRequest` from `@test-setup/api` with an alias. `waitForRequest('@alias')` returns the request the handler received.

### Semantic query

A Testing Library query that finds an element the way a user does: by role (`getByRole`), by label (`getByLabelText`) or by text (`getByText`). A query by `data-testid` is not semantic.

---

## 5. Module federation

### Host

Centreon Web, built by the rspack configuration of `@centreon/js-config`, which loads the federated modules at runtime.

### Federated module

A frontend of the `centreon-modules` repository, built with module federation and loaded by the host. It declares its pages and components in a `moduleFederation.json`.

### Exposed entry

A file a federated module lists in the `exposes` of its rspack configuration. The host loads it with `React.lazy`, so it default-exports its component.

---

## 6. Not defined yet

Terms the team uses without an agreed definition. Add the definition above when it is settled.

- **Widget:** a dashboard widget of `www/widgets`, built and loaded like a federated module.
- **Page vs feature:** when a directory is a page and when it is a feature.
