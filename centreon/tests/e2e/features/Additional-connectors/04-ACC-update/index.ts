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
  cy.createAccWithMandatoryFields();
  cy.get('*[role="rowgroup"]').should('contain', 'Connector-001');
});

When(
  'the user clicks on the Edit properties button of an additional connector configuration',
  () => {
    cy.contains('Connector-001').click();
  }
);

Then('a pop-up menu with the form is displayed', () => {
  cy.wait('@getConnectorDetail');
  cy.contains('Modify an additional configuration').should('be.visible');
});

Then(
  'all of the informations of the additional connector configuration are correct',
  () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'have.value',
      'Connector-001'
    );
    cy.getByLabel({ label: 'Description', tag: 'input' }).should('be.empty');
    cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
    cy.get('*[class^="MuiChip-label MuiChip-labelMedium"]').should(
      'contain',
      'Central'
    );
    cy.get('#Usernamevalue').should('be.empty');
    cy.get('#Passwordvalue').should('be.empty');
    cy.get('#vCenternamevalue').should('have.value', 'vCenter-001');
    cy.get('#URLvalue').should('have.value', 'https://10.0.0.0/sdk');
    cy.get('#Portvalue').should('have.value', '5700');
  }
);

When('the user updates some information', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type('Connector-002');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.get('svg[class*="deleteIcon"]').click();
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click().click();
  cy.contains('Poller-1').click();
  cy.get('#Usernamevalue').type('admin');
  cy.get('#Passwordvalue').type('Abcde!2022');
  cy.get('#vCenternamevalue').clear().type('vCenter-002');
  cy.get('#URLvalue').clear().type('https://10.3.3.3/sdk');
  cy.get('#Portvalue').clear().click().type('6900');
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
  cy.contains('VMWare 6/7').click();
  cy.ensureConnectorInputValue('Connector-002', { maxAttempts: 6, interval: 5000 });
  cy.contains('VMWare 6/7').click();
  cy.wait('@keepAlive');
  cy.getByLabel({ label: 'Name', tag: 'input' }).should(
    'have.value',
    'Connector-002'
  );
  cy.getByLabel({ label: 'Description', tag: 'input' }).should('be.empty');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.get('*[class^="MuiChip-label MuiChip-labelMedium"]').should(
    'contain',
    'Poller-1'
  );
  cy.get('#Usernamevalue').should('be.empty');
  cy.get('#Passwordvalue').should('be.empty');
  cy.get('#vCenternamevalue').should('have.value', 'vCenter-002');
  cy.get('#URLvalue').should('have.value', 'https://10.3.3.3/sdk');
  cy.get('#Portvalue').should('have.value', '6900');
});
