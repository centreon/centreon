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
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
});

When('the user clicks on the Additional Connector Configuration page', () => {
  cy.navigateTo({
    page: 'Additional Configuration',
    rootItemNumber: 0,
    subMenu: 'Connectors'
  });
});

Then('the user sees the Additional Connector Configuration page', () => {
  cy.wait('@getConnectorPage');
});

Then('there is no additional connector configuration listed', () => {
  cy.contains('Welcome to the additional configurations page').should('be.visible');
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

Given('an already existing additional connector configuration', () => {
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
  cy.createAccWithMandatoryFields();
  cy.get('*[role="rowgroup"]').should('contain', 'Connector-001');
});

When(
  'the user clicks on the Edit button of the additional connector configuration',
  () => {
    cy.contains('Connector-001').click();
  }
);

Then(
  'a pop up is displayed with all of the additional connector informations',
  () => {
    cy.wait('@getConnectorDetail');
    cy.contains('Modify an additional configuration').should(
      'be.visible'
    );
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
