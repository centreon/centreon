// Local-only harness: run the suites against an already-running CDE instead of
// letting Cypress spin up its own containers. Never committed.
import './e2e';

Cypress.Commands.overwrite('startContainers', () => {
  Cypress.config('baseUrl', 'http://127.0.0.1:4000');

  // startContainers also does this, and the suites depend on it: without the
  // visit + token the API v1 calls go out with a null auth token and 500.
  return cy.visit('/').setUserTokenApiV1();
});

Cypress.Commands.overwrite('stopContainers', () => cy.wrap(null));

// The CDE injects a fixed banner at the bottom of every HTML response — the page
// and the nested panel iframe alike — and it covers form action bars, so a click
// on a Save button lands on it. CI has no banner. The banner ships its own hide
// toggle backed by localStorage, and localStorage is per origin, so setting the
// flag before the first load hides it in every frame.
Cypress.on('window:before:load', (win) => {
  win.localStorage.setItem('cde-dev-banner-hidden', '1');
});

// execInContainer goes through testcontainers, which owns no container here.
// Route it at the running CDE app container instead, so the steps that read the
// exported engine files work against the local stack.
Cypress.Commands.overwrite(
  'execInContainer',
  (_originalFn, { command }: { command: string }) =>
    cy
      .exec(`docker exec centreon-app bash -c ${JSON.stringify(command)} 2>&1`, {
        failOnNonZeroExit: false,
        timeout: 600_000
      })
      .then((result) =>
        cy.wrap({
          exitCode: result.code ?? 0,
          output: `${result.stdout ?? ''}${result.stderr ?? ''}`
        })
      )
);
