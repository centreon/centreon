// Local-only harness: run the suites against an already-running CDE instead of
// letting Cypress spin up its own containers. Written by the test-pr skill.
// Never committed.
import './e2e';

Cypress.Commands.overwrite('startContainers', () => {
  Cypress.config('baseUrl', 'http://127.0.0.1:4000');

  // startContainers also does this, and the suites depend on it: without the
  // visit + token the API v1 calls go out with a null auth token and 500.
  return cy.visit('/').setUserTokenApiV1();
});

// In CI each scenario gets a freshly booted platform, so the suites re-create
// their fixtures in every beforeEach and CLAPI answers 409 on the second
// scenario here. Restoring the reference dump gives that isolation back.
// failOnNonZeroExit stays false so a reset problem does not fail the scenario
// itself, but the reason is surfaced: a silently absent reset looks exactly
// like a passing suite until the 409s start.
Cypress.Commands.overwrite('stopContainers', () =>
  cy
    // biome-ignore lint/suspicious/noTemplateCurlyInString: shell parameter expansion, not a JS template
    .exec('"${XDG_CACHE_HOME:-$HOME/.cache}/centreon/test-pr/dbreset.sh"', {
      failOnNonZeroExit: false,
      timeout: 120_000
    })
    .then((result) => {
      if (result.code !== 0) {
        cy.log(`test-pr: database reset failed — ${result.stderr}`);
      }

      return cy.wrap(null);
    })
);

// execInContainer goes through testcontainers, which owns no container here.
// Route it at the running CDE app container instead, so the steps that read the
// exported engine files (hosts.cfg, hostTemplates.cfg, …) answer on the local
// stack rather than failing on a container name that exists only in CI.
Cypress.Commands.overwrite(
  'execInContainer',
  (_originalFn, { command }: { command: string }) =>
    cy
      .exec(
        `docker exec centreon-app bash -c ${JSON.stringify(command)} 2>&1`,
        {
          failOnNonZeroExit: false,
          timeout: 600_000
        }
      )
      .then((result) =>
        cy.wrap({
          exitCode: result.code ?? 0,
          output: `${result.stdout ?? ''}${result.stderr ?? ''}`
        })
      )
);

// Safety net for a CDE still injecting its dev banner — the skill turns it off
// through CDE_DEV_BANNER_ENABLED=0, but a stack booted before that change still
// carries it, and its floating toggle sits above the legacy form action bars:
// a click meant for Save lands on the toggle instead.
Cypress.on('window:before:load', (win) => {
  win.localStorage.setItem('cde-dev-banner-hidden', '1');
});
