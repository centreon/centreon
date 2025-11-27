import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
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
    method: 'GET',
    url: '/centreon/api/latest/configuration/additional-connector-configurations/*'
  }).as('getConnectorDetail');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/configuration/additional-connector-configurations/*'
  }).as('updateConnectorDetail');
  cy.intercept({
    method: 'GET',
    url: 'centreon/api/internal.php?object=centreon_keepalive&action=keepAlive'
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
    cy.visit('/centreon/configuration/additional-connector-configurations');
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
  cy.wait('@getConnectorDetail');
  cy.verifyAccFieldValues(data.updated);
});
