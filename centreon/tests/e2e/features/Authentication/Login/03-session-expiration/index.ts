import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildSetSessionExpirationQuery,
  sessionExpirationQuery
} from '../common';

// The React shell calls the keepalive every 15 s (www/front_src/src/App/useApp.ts)
const keepAliveInterval = 15_000;
// Margin added to the expiration time before checking the session state
const expirationMargin = 30_000;

let sessionExpirationMinutes = 0;

beforeEach(() => {
  cy.startContainers();

  sessionExpirationMinutes = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.centreon_keepalive}&action=keepAlive`
  }).as('keepAlive');
});

afterEach(() => {
  cy.stopContainers();
});

const expirationDelay = (): number =>
  sessionExpirationMinutes * 60_000 + expirationMargin;

Given(
  'a Centreon platform whose Sessions Expiration Time is set to {int} minute',
  (minutes: number) => {
    sessionExpirationMinutes = minutes;

    cy.requestOnDatabase({
      database: 'centreon',
      query: buildSetSessionExpirationQuery(minutes)
    });
    cy.requestOnDatabase({
      database: 'centreon',
      query: sessionExpirationQuery
    }).then(([rows]) => {
      expect(String(rows[0].value), 'session_expire').to.eq(String(minutes));
    });
  }
);

Given('a local user logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
  cy.url().should('include', PAGES.monitoring.resourcesStatus);
  cy.wait('@keepAlive').its('response.statusCode').should('eq', 200);
});

When(
  'the user makes no request for more than {int} minute',
  (minutes: number) => {
    expect(minutes, 'idle time').to.eq(sessionExpirationMinutes);

    // Leave the application: no page of Centreon is left to send a request
    cy.window().then((win) => {
      win.location.href = 'about:blank';
    });
    cy.wait(expirationDelay());
  }
);

Then('the next page the user opens is the login page', () => {
  cy.visit(PAGES.monitoring.resourcesStatus);

  cy.url().should('include', PAGES.configuration.login);
  cy.getByLabel({ label: 'Alias', tag: 'input' }).should('be.visible');
  cy.get('input[type="password"]').should('be.visible');
});

Then('the user stays logged in while the page keeps polling', () => {
  let earlierKeepAliveCalls = 0;

  cy.get('@keepAlive.all').then((interceptions) => {
    earlierKeepAliveCalls = (interceptions as unknown as Array<unknown>).length;
  });

  // Requests sent before this login (expired session) are not waited for
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?page`
  }).as('getResourcesAfterLogin');

  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
  cy.url().should('include', PAGES.monitoring.resourcesStatus);
  cy.wait('@getResourcesAfterLogin')
    .its('response.statusCode')
    .should('eq', 200);

  // The Resources Status page stays open longer than the expiration time
  cy.wait(expirationDelay());

  cy.get('@keepAlive.all').then((interceptions) => {
    const calls = (
      interceptions as unknown as Array<{
        response?: { statusCode: number };
      }>
    ).slice(earlierKeepAliveCalls);
    // Enough calls to cover the whole expiration time
    const expectedCalls = Math.floor(
      (sessionExpirationMinutes * 60_000) / keepAliveInterval
    );

    expect(calls.length, 'keepalive calls').to.be.at.least(expectedCalls);
    calls.forEach(({ response }) => {
      expect(response?.statusCode, 'keepalive status code').to.eq(200);
    });
  });

  cy.reload();
  cy.wait('@getResourcesAfterLogin')
    .its('response.statusCode')
    .should('eq', 200);
  cy.url().should('include', PAGES.monitoring.resourcesStatus);
  cy.get('input[placeholder="Search"]').should('be.visible');
  cy.getByLabel({ label: 'Alias', tag: 'input' }).should('not.exist');
});
