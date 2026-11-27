import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildHostCountQuery,
  invalidTokenMessage,
  withFormFields
} from '../common';

// Host name of the replayed submissions: it must never be created
const replayedHostName = 'rbt-csrf-host2';

let capturedUrl = '';
let capturedBody = '';
let replayResponse: Cypress.Response<unknown> | null = null;

beforeEach(() => {
  cy.startContainers();

  capturedUrl = '';
  capturedBody = '';
  replayResponse = null;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'POST',
    url: `${PAGES.configuration.hostsLegacyFrame}*`
  }).as('submitHostForm');
});

afterEach(() => {
  cy.stopContainers();
});

const countHosts = (name: string): Cypress.Chainable<number> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: buildHostCountQuery(name)
    })
    .then(([rows]) => cy.wrap(Number(rows[0].count), { log: false }));

// Same session as the browser: only the token and the host name differ
const replaySubmission = (fields: Record<string, string | null>): void => {
  cy.request({
    body: withFormFields(capturedBody, fields),
    failOnStatusCode: false,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    method: 'POST',
    url: capturedUrl
  }).then((response) => {
    replayResponse = response;
  });
};

const replayedHost = {
  host_alias: replayedHostName,
  host_name: replayedHostName
};

Given(
  'an administrator has created the host {word} through the legacy host form',
  (hostName: string) => {
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });

    cy.visit(`${PAGES.configuration.hostsLegacy}&o=a`);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');

    cy.getIframeBody().find('input[name="host_name"]').type(hostName);
    cy.getIframeBody().find('input[name="host_alias"]').type(hostName);
    cy.getIframeBody().find('input[name="host_address"]').type('127.0.0.1');
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();

    cy.wait('@submitHostForm').then(({ request }) => {
      capturedUrl = request.url;
      capturedBody = String(request.body);

      expect(new URLSearchParams(capturedBody).get('centreon_token')).to.not.be
        .empty;
    });

    countHosts(hostName).should('eq', 1);
  }
);

When(
  'the captured form submission is replayed with its already used centreon_token',
  () => {
    replaySubmission(replayedHost);
  }
);

When('the submission is replayed without the centreon_token field', () => {
  replaySubmission({ ...replayedHost, centreon_token: null });
});

When('the submission is replayed with a forged centreon_token', () => {
  replaySubmission({
    ...replayedHost,
    centreon_token: 'ffffffffffffffffffffffffffffffff'
  });
});

Then(
  'the form is refused with the invalid token message and no host is created',
  () => {
    expect(replayResponse?.status).to.eq(200);
    expect(String(replayResponse?.body)).to.contain(invalidTokenMessage);

    countHosts(replayedHostName).should('eq', 0);
  }
);

Then('the form is refused and no host is created', () => {
  expect(String(replayResponse?.body)).to.contain(invalidTokenMessage);

  countHosts(replayedHostName).should('eq', 0);
});
