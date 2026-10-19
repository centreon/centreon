# Migrating a Cypress component test to rstest

Component tests run with [rstest](https://rstest.rs) in a real Chromium
(Browser Mode, Playwright), with React Testing Library and `user-event`.
A test file is `*.browser.test.tsx`; `rstest.config.ts` picks them up.

```bash
pnpm rstest:run                        # every browser test, headless
pnpm rstest:run <path>                 # one file
pnpm rstest:run <path> -t '<name>'     # one test, by name pattern
pnpm rstest:ui <path>                  # the same in a visible browser, to debug
```

## The rules that keep a migration honest

A migrated test must fail when the behaviour it covers breaks. The ways a
translation silently stops testing anything are the ones below.

- **Same tests, same names.** Every `it()` of the Cypress spec has its
  counterpart, with the same name, in the same `describe`. None is dropped,
  merged or skipped (`it.skip`, `it.todo`, `.only` are not allowed).
- **Every test asserts.** The spec entry calls `expect.hasAssertions()` in a
  `beforeEach`, so a test whose assertions never ran fails.
- **Await everything.** Every `userEvent.*`, `findBy*`, `waitFor` and
  `waitForRequest` is awaited. An un-awaited one runs after the test ended.
- **Keep every assertion.** Each Cypress `.should(...)` and `expect(...)` has an
  equivalent. A check is only removed when it was a visual snapshot (see below).
- **Never update a snapshot to make a test pass** without looking at the diff.

## Cypress retries, rstest does not

`cy.get(...).should(...)` retries until it passes. A plain `expect` checks once.
Whatever appears or changes after an async step (a request, a click, a
transition, an image loading) must be awaited:

| Cypress                                       | rstest                                                                                                         |
| --------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `cy.findByTestId(id).should('be.visible')`    | `expect(await screen.findByTestId(id)).toBeVisible()`                                                          |
| `cy.contains(text).should('be.visible')`      | `expect(await screen.findByText(text)).toBeVisible()` (`getAllByText(text)[0]` when it appears more than once) |
| `.should('not.exist')`                        | `expect(screen.queryByTestId(id)).not.toBeInTheDocument()`, in `waitFor` when it disappears after an action    |
| `.should('have.value', v)` on something async | `await waitFor(() => expect(el).toHaveValue(v))`                                                               |
| `.should('have.attr', name, value)`           | `expect(el).toHaveAttribute(name, value)`                                                                      |
| `.should('have.text', t)`                     | `expect(el).toHaveTextContent(/^t$/)` (exact)                                                                  |
| `.should('contain.text', t)`                  | `expect(el).toHaveTextContent(t)`                                                                              |
| `.should('be.disabled' / 'be.enabled')`       | `toBeDisabled()` / `toBeEnabled()`                                                                             |
| `.should('be.checked')`                       | `toBeChecked()`                                                                                                |
| `.should('have.length', n)`                   | `expect(list).toHaveLength(n)`                                                                                 |
| `cy.wrap(x).should(fn)`                       | `await waitFor(() => fn(x))`                                                                                   |

`toHaveValue` gives a number for `type="number"` inputs: `toHaveValue(3)`,
not `'3'`.

## Commands

| Cypress                                                  | rstest                                                                                                                 |
| -------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `cy.mount({ Component })`                                | `render(component)` from `@test-setup/render`                                                                          |
| `cy.interceptAPIRequest({...})`                          | `interceptAPIRequest({...})` from `@test-setup/api` — no 500 ms delay unless `delay` is given                          |
| `cy.interceptRequest(method, path, fn, alias)`           | `interceptRequest(method, path, () => HttpResponse.json(...), alias)`                                                  |
| `cy.waitForRequest('@alias').then(({ request }) => ...)` | `const { request } = await waitForRequest('@alias')`                                                                   |
| `cy.getRequestCalls('@alias')`                           | `getRequestCalls('@alias')`                                                                                            |
| `cy.get(selector)`                                       | `document.querySelector(selector)` / `querySelectorAll`, or a Testing Library query                                    |
| `cy.findByTestId(id)` / `cy.findAllByTestId(id).eq(n)`   | `await screen.findByTestId(id)` / `(await screen.findAllByTestId(id))[n]`                                              |
| `cy.findByLabelText(l)`                                  | `screen.getByLabelText(l)`                                                                                             |
| `.within(...)`, `cy.get(a).contains(b)`                  | `within(a).getByText(b)`                                                                                               |
| `.closest(sel)`, `.parent()`, `.find(sel)`               | the DOM API: `el.closest(sel)`, `el.parentElement`, `el.querySelector(sel)`                                            |
| `.click()`                                               | `await userEvent.click(el)`                                                                                            |
| `.type('abc')`                                           | `await userEvent.type(el, 'abc')`                                                                                      |
| `.type('{enter}')` / `{backspace}` / `{selectall}`       | `await userEvent.type(el, '{Enter}')` / `{Backspace}` / `await userEvent.tripleClick(el)` then type                    |
| `.clear()`                                               | `await userEvent.clear(el)`                                                                                            |
| `.blur()` / `.focus()`                                   | `el.blur()` / `el.focus()` (inside `act` if it changes state), or `await userEvent.tab()`                              |
| `cy.focused()`                                           | `document.activeElement`                                                                                               |
| `.trigger('mouseover')`                                  | `await userEvent.hover(el)`                                                                                            |
| `cy.window().then((win) => cy.stub(win, 'open'))`        | `const open = rs.spyOn(window, 'open').mockImplementation(() => null)` (`rs` from `@rstest/core`), restored at the end |
| `cy.stub()`                                              | `rs.fn()`                                                                                                              |
| `cy.wait(ms)`                                            | Avoid; wait for what the delay was for instead                                                                         |
| `cy.moveSortableElement(...)`                            | keyboard drag with `userEvent`: focus the handle, `{ }` (space), arrows, `{ }`                                         |

## The API mocks

- The paths take the Cypress globs as they are (`**/configuration/hosts?**`).
  MSW ignores the query string, so `*/hosts` matches the listing and
  `*/hosts/0` one host.
- `waitForRequest('@alias')` resolves once the alias answered a request the
  test has not waited for yet, with the latest one. Waiting twice on a request
  that is only sent once times out: check the earlier one with
  `getRequestCalls('@alias')` instead.
- The request is `{ url: URL, body, method }`; the body is parsed JSON.
- `waitForRequest` resolves when the response is sent, before the page has
  applied it. A check right after it on what the page shows must be a
  `findBy*` or a `waitFor`: a plain `queryBy...` or `getRequestCalls(...)`
  check there can pass before anything happened.
- Mocked responses come back at once. A test about a pending state (a
  disabled button while saving, a late answer) passes a `delay` to the mock.

## Visual snapshots

`cy.makeSnapshot()` compared a screenshot with a 7% tolerance. It has no direct
equivalent. Either:

- drop it when the test already asserts what it shows (state it in the PR), or
- snapshot the HTML of the one region that matters:
  `expect(screen.getByTestId(id)).toMatchSnapshot()`. Never the whole page.

## Gotchas

- An unhandled promise rejection or uncaught error fails the whole test file,
  even when every test passed; Cypress hid them (`uncaught:exception`). Fix
  the code that leaves the promise unhandled rather than filtering the error:
  a `CancelledError` from React Query, for instance, means a `fetchQuery()`
  without a `.catch` in a component that unmounted while it loaded.

- Storage, the request log and the MSW handlers are reset after each test, as
  Cypress did. URL state is not: reset it in the test's `initialize`.
- MUI menus, poppers and dialogs render in a portal: query them from `screen`
  or `document`, not from the rendered container. A closing menu can still be
  in the page: take the last one, `(await screen.findAllByRole('menu')).at(-1)`.
- `@centreon/ui` text fields put the same `data-testid` on the wrapper and the
  input: `findAllByTestId(id)` then the input (`[1]`, or filter on `tagName`).
- `userEvent` refuses to click an element with `pointer-events: none`: use
  `fireEvent.click` for those, and say why in a comment.
