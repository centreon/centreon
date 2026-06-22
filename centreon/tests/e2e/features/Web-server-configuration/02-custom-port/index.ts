import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';

import { reloadWebServer, updateWebServerPort } from '../common';

// 8080 is avoided (used by the map server).
const nonDefaultPort = 8555;
const host = 'Centreon-Server';

before(() => {
  // TODO (MON-201337): startContainers must publish nonDefaultPort — until it
  // exposes a port option, pass a custom composeFile that publishes it.
  cy.startContainers();
});

after(() => {
  cy.stopContainers();
});

Given('a running platform served on a non-default web server port', () => {
  updateWebServerPort(nonDefaultPort);
  reloadWebServer();

  // startContainers hardcodes baseUrl to :4000 — re-point it to the new port.
  Cypress.config('baseUrl', `http://127.0.0.1:${nonDefaultPort}`);

  cy.waitUntil(
    () =>
      cy
        .request({ failOnStatusCode: false, method: 'GET', url: '/centreon/' })
        .then((response) => /^[2-3]\d{2}/.test(response.status.toString())),
    { timeout: 30000 }
  );

  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the admin edits and saves a host through the legacy configuration form',
  () => {
    cy.visit(PAGES.configuration.hostsLegacy);
    cy.getIframeBody().contains(host).click();
    cy.getIframeBody()
      .find('input[name="host_alias"]')
      .clear()
      .type('Edited by e2e');
    cy.getIframeBody().find('input[name="submitC"]').click();
  }
);

Then('the host is saved without an internal API connection error', () => {
  cy.getIframeBody().should(($body) => {
    expect($body.text()).not.to.match(/Failed to connect to .* port 80/);
  });
});
