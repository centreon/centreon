import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/additional-configurations/acc.json';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/acc-acl-user.json'
  );
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-1.json'
  );
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-2.json'
  );
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.connector_configurations}?*`
  }).as('getConnectorPage');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.api.connector_configurations
  }).as('addAdditionalConnector');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.connector_configurations}/*`
  }).as('getConnectorDetail');
  cy.intercept({
    method: 'PUT',
    url: `${INTERCEPTORS.api.connector_configurations}/*`
  }).as('updateConnectorDetail');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.centreon_keepalive}&action=keepAlive`
  }).as('keepAlive');
});

after(() => {
  cy.stopContainers();
});

Given(
  'a non-admin user is in the Additional Connector Configuration page',
  () => {
    cy.loginByTypeOfUser({
      jsonName: 'user-non-admin-for-ACC',
      loginViaApi: false
    });
    cy.visit(PAGES.configuration.additionalConfigurations);
    cy.wait('@getConnectorPage');
  }
);

Given('an additional connector configuration is already created', () => {
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
  cy.createAccWithMandatoryFields(data.default);
  cy.saveAcc();
  cy.wait('@addAdditionalConnector');
  cy.get('*[role="rowgroup"]').should('contain', 'Connector-001');
});

When(
  'the user clicks on the Edit properties button of an additional connector configuration',
  () => {
    cy.contains(data.default.name).click();
  }
);

Then('a pop-up menu with the form is displayed', () => {
  cy.wait('@getConnectorDetail');
  cy.contains('Modify an additional configuration').should('be.visible');
});

Then(
  'all of the informations of the additional connector configuration are correct',
  () => {
    cy.verifyAccFieldValues(data.default);
  }
);

When('the user updates some information', () => {
  cy.updateAcc(data.updated);
});

When('the user clicks on Update', () => {
  cy.saveAcc();
});

Then('the form is closed', () => {
  cy.wait('@updateConnectorDetail');
  cy.wait('@getConnectorPage');
  cy.contains('Modify an additional configuration').should('not.exist');
});

Then('the informations are successfully saved', () => {
  cy.contains(data.updated.name).click();
  // The detail request may legitimately not fire a second time: the connector
  // was just saved, so reopening the form can be served from the client cache.
  // Waiting on a request that never happens fails the scenario, and the retry
  // then runs against a listing that already holds the connector — which is
  // why the whole spec ended at 0 passing. Wait on the reopened form instead.
  cy.contains('Modify an additional configuration').should('be.visible');
  cy.verifyAccFieldValues(data.updated);
});
