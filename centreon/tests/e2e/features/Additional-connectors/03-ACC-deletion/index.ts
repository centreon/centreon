/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

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
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/additional-connector-configurations?*'
  }).as('getConnectorPage');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/additional-connector-configurations'
  }).as('addAdditionalConnector');
  cy.intercept({
    method: 'DELETE',
    url: '/centreon/api/latest/configuration/additional-connector-configurations/*'
  }).as('deleteConnector');
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
    cy.visit('/centreon/configuration/additional-connector-configurations');
    cy.wait('@getConnectorPage');
  }
);

Given('an additional connector configuration is already created', () => {
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
  cy.createAccWithMandatoryFields();
});

When('the user deletes the additional connector configuration', () => {
  cy.getByLabel({ label: 'Delete', tag: 'button' }).eq(0).click();
});

When('the user cancel on the pop-up', () => {
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
});

Then(
  'the additional connector configuration is still displayed in the listing page',
  () => {
    cy.get('*[role="rowgroup"]').should('contain', 'Connector-001');
  }
);

When('the user confirms on the pop-up', () => {
  cy.getByLabel({ label: 'Delete', tag: 'button' }).eq(1).click();
});

Then(
  'the additional connector configuration is no longer displayed in the listing page',
  () => {
    cy.wait('@deleteConnector');
    cy.contains('Welcome to the additional configurations page').should('be.visible');
  }
);
