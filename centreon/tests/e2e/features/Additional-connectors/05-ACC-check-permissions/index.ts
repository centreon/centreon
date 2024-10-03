import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  // cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/acc-acl-user.json');
  cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/config-ACL/local-authentication-acl-user.json');
  // cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/pollers/poller-1.json');
  // cy.setUserTokenApiV1().executeCommandsViaClapi('resources/clapi/pollers/poller-2.json'); 
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
    method: 'DELETE',
    url: '/centreon/api/latest/configuration/additional-connector-configurations/*'
  }).as('deleteConnector');
});

after(() => {
  // cy.stopContainers();
});

Given('an admin user is in the Specific Connector Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
});

When('the admin user clicks on Add', () => {
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
});

Then('a pop-up menu with the form is displayed', () => {
  cy.contains('Create additional connector configuration').should('be.visible');
});

When('the admin user fills in all the informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type("I'm the first connector created");
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Abcde!2021');
  cy.get('#Portvalue').should('have.value', '5700');
});

When('the admin user clicks on Save', () => {
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
});

Then('the creation form is closed', () => {
  cy.wait('@addAdditionalConnector');
  cy.get('Create additional connector configuration').should('not.exist');
});

Then('the first configuration is displayed in the Specific Connector Configuration page', () => {
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-001');
});

Given('an additional connector configuration is already created', () => {
  cy.get('*[role="rowgroup"]')
    .should('not.contain', 'No result found');
});

When('the user clicks on the Edit button of the additional connector configuration', () => {
  cy.getByLabel({ label: 'Edit connector configuration', tag: 'button' }).click();
});

Then('a pop up is displayed with all of the additional connector information', () => {
  cy.wait('@getConnectorDetail');
  cy.contains('Update additional connector configuration').should('be.visible');
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('have.value', 'Connector-001');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('have.value', "I'm the first connector created");
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.get('*[class^="MuiChip-label MuiChip-labelMedium"]').should('contain', 'Central');
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(1).should('have.value', 'vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).eq(1).should('have.value', 'https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(1).should('be.empty');
  cy.getByTestId({ testId: 'Password_value' }).eq(1).should('be.empty');
  cy.get('#Portvalue').should('have.value', '5700');
});

When('the user modifies the configuration', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type('Connector-002');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.getByTestId({ testId: 'CancelIcon' }).click();
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click().click();
  cy.contains('Poller-1').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-002');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.3.3.3/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Abcde!2022');
  cy.get('#Portvalue').clear().click().type('6900');
});

When('the user clicks on Save', () => {
  cy.getByLabel({ label: 'Update', tag: 'button' }).click();
});

Then('the update form is closed', () => {
  cy.wait('@updateConnectorDetail');
  cy.get('Update additional connector configuration').should('not.exist');
});

Then('the updated configuration is displayed correctly in the Specific Connector Configuration page', () => {
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-002');
});

When('the admin user deletes the additional connector configuration', () => {
  cy.getByLabel({ label: 'Delete', tag: 'button' }).eq(0).click();
  cy.getByLabel({ label: 'Delete', tag: 'button' }).eq(1).click();
});

Then('the additional connector configuration is no longer displayed in the listing page', () => {
  cy.wait('@deleteConnector');
  cy.get('*[role="rowgroup"]')
    .should('not.contain', 'Connector-001');
});

Given('a non-admin user without topology rights is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user',
    loginViaApi: false
  });
});

When('the user tries to access the Specific Connector Configuration page', () => {
  cy.visit('/centreon/configuration/additional-connector-configurations');
});

Then('the user cannot access the Specific Connector Configuration page', () => {
  cy.getByTestId({ testId: 'You are not allowed to see this page' }).should('be.visible');
});

Given('a non-admin user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
});

Given('an Additional Connector Configuration already created linked with two pollers', () => {
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.contains('Poller-2').click();
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Abcde!2021');
  cy.get('#Portvalue').should('have.value', '5700');
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
  cy.wait('@addAdditionalConnector');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-001');
});

Given('the user has a filter on one of the pollers', () => {
  cy.setUserTokenApiV1().executeActionViaClapi({
    bodyContent: {
      action: 'addfilter_instance',
      object: 'ACLRESOURCE',
      values: `All Resources;Poller-1`
    }
  });
  cy.setUserTokenApiV1().executeActionViaClapi({
    bodyContent: {
      action: 'reload',
      object: 'ACL',
      values: ''
    }
  });
});

When('the user accesses the Specific Connector Configuration page', () => {
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
});

Then('the user can not view the additional connector linked to the 2 pollers', () => {
  cy.get('*[role="rowgroup"]')
    .should('contain', 'No result found');
});

When('the admin user updates the filtered pollers of the non-admin user', () => {
  cy.setUserTokenApiV1().executeActionViaClapi({
    bodyContent: {
      action: 'addfilter_instance',
      object: 'ACLRESOURCE',
      values: `All Resources;Poller-2`
    }
  });
  cy.setUserTokenApiV1().executeActionViaClapi({
    bodyContent: {
      action: 'reload',
      object: 'ACL',
      values: ''
    }
  });
});

Then('the user can view the additional connector linked to the pollers', () => {
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-001');
});

When('a pop up is displayed with all of the additional connector information with the 2 pollers', () => {
  cy.wait('@getConnectorDetail');
  cy.contains('Update additional connector configuration').should('be.visible');
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('have.value', 'Connector-001');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.get('*[class^="MuiChip-label MuiChip-labelMedium"]').should('contain', 'Poller-1', 'Poller-2');
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(1).should('have.value', 'vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).eq(1).should('have.value', 'https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(1).should('be.empty');
  cy.getByTestId({ testId: 'Password_value' }).eq(1).should('be.empty');
  cy.get('#Portvalue').should('have.value', '5700');
});

Then('the user can update the additional connector configuration', () => {
  cy.contains('Update additional connector configuration').should('be.visible');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.getByTestId({ testId: 'CancelIcon' }).eq(0).click();
  cy.getByLabel({ label: 'Update', tag: 'button' }).click();
  cy.wait('@updateConnectorDetail');
  cy.get('Update additional connector configuration').should('not.exist');
});

Given('a non-admin user is in the Specific Connector Configuration page', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-ACC',
    loginViaApi: false
  });
  cy.visit('/centreon/configuration/additional-connector-configurations');
  cy.wait('@getConnectorPage');
});

When('the user adds a second additional connector configuration', () => {
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
});

Then('only the free filtered pollers are listed in the Pollers field', () => {
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.get('[role="option"]').should('have.length', 1);
  cy.contains('Poller-1').should('be.visible');
});

When('the non-admin user fills in all the informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-002');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.getByTestId({ testId: 'vCenter name_value' }).eq(0).clear().type('vCenter-001');
  cy.getByTestId({ testId: 'URL_value' }).eq(0).clear().type('https://10.1.1.1/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(0).type('admin');
  cy.getByTestId({ testId: 'Password_value' }).eq(0).type('Abcde!2021');
  cy.get('#Portvalue').should('have.value', '5700');
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
});

Then('the new configuration is displayed in the Specific Connector Configuration page', () => {
  cy.get('*[role="rowgroup"]')
    .should('contain', 'Connector-002');
});

When('the user deletes the additional connector configuration', () => {
  cy.getByLabel({ label: 'Delete', tag: 'button' }).eq(0).click();
  cy.getByTestId({ testId: 'confirm' }).click();
});