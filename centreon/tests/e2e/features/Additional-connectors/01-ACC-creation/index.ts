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
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-3.json'
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

When('the user clicks on Add', () => {
  cy.getByLabel({ label: 'Add', tag: 'button' }).click();
});

Then('a pop-up menu with the form is displayed', () => {
  cy.contains('Create additional connector configuration').should('be.visible');
});

When('the user fills in all the informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    "I'm the first connector created"
  );
  cy.get('#mui-component-select-type').should('contain', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Central').click();
  cy.get('#Usernamevalue').type('admin');
  cy.get('#Passwordvalue').type('Abcde!2021');
  cy.get('#vCenternamevalue').clear().type('vCenter-001');
  cy.get('#URLvalue').clear().type('https://10.0.0.0/sdk');
  cy.get('#Portvalue').should('have.value', '5700');
});

When('the user clicks on Create', () => {
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
});

Then(
  'the first connector is displayed in the Additional Connector Configuration page',
  () => {
    cy.wait('@addAdditionalConnector');
    cy.get('*[role="rowgroup"]').should('contain', 'Connector-001');
  }
);

When('the user fills in the mandatory informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-002');
  cy.get('#mui-component-select-type').should('contain', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-1').click();
  cy.get('#Usernamevalue').type('admin');
  cy.get('#Passwordvalue').type('Abcde!2021');
  cy.get('#vCenternamevalue').clear().type('vCenter-002');
  cy.get('#URLvalue').clear().type('https://10.0.0.0/sdk');
  cy.get('#Portvalue').should('have.value', '5700');
});

Then(
  'the second configuration is displayed in the Additional Connector Configuration page',
  () => {
    cy.wait('@addAdditionalConnector');
    cy.get('*[role="rowgroup"]').should('contain', 'Connector-002');
  }
);

Then(
  'the third configuration is displayed in the Additional Connector Configuration page',
  () => {
    cy.wait('@addAdditionalConnector');
    cy.get('*[role="rowgroup"]').should('contain', 'Connector-003');
  }
);

When('the user clicks to add a second vCenter', () => {
  cy.getByLabel({ label: 'Add vCenter/ESX', tag: 'button' }).click();
});

Then('a second group of parameters is displayed', () => {
  cy.get('[class$="-parametersContainer"]').eq(0).should('exist');
  cy.get('[class$="-parametersContainer"]').eq(1).should('exist');
});

When('the user fills in the informations of all the parameter groups', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-003');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    'I have multiple parameters groups'
  );
  cy.get('#mui-component-select-type').should('contain', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-2').click();
  cy.get('#Usernamevalue').eq(0).type('admin');
  cy.get('#Passwordvalue').eq(0).type('Abcde!2021');
  cy.get('#vCenternamevalue').eq(0).clear().type('vCenter-001');
  cy.get('#URLvalue').eq(0).clear().type('https://10.0.0.0/sdk');
  cy.getByTestId({ testId: 'Username_value' }).eq(3).type('admin-2');
  cy.getByTestId({ testId: 'Password_value' }).eq(3).type('Abcde!2022');
  cy.getByTestId({ testId: 'vCenter name_value' })
    .eq(3)
    .clear()
    .type('vCenter-002');
  cy.getByTestId({ testId: 'URL_value' })
    .eq(3)
    .clear()
    .type('https://10.0.0.1/sdk');
  cy.get('#Portvalue').should('have.value', '5700');
});

When("the user doesn't fill in all the mandatory informations", () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).click();
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.get('#Usernamevalue').click();
  cy.get('#Passwordvalue').click();
  cy.get('#vCenternamevalue').clear();
  cy.get('#URLvalue').clear();
  cy.get('#Portvalue').click();
});

Then('the user cannot click on Create', () => {
  cy.getByTestId({ testId: 'Name' }).contains('Required').should('be.visible');
  cy.contains('At least one poller is required').should('be.visible');
  cy.getByTestId({ testId: 'vCenter name_value' })
    .contains('Required')
    .should('be.visible');
  cy.getByTestId({ testId: 'URL_value' })
    .contains('Required')
    .should('be.visible');
  cy.getByTestId({ testId: 'Username_value' })
    .contains('Required')
    .should('be.visible');
  cy.getByTestId({ testId: 'Password_value' })
    .contains('Required')
    .should('be.visible');
  cy.getByLabel({ label: 'Create', tag: 'button' }).should('be.disabled');
});

When("the user doesn't fill in correct type of informations", () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('a');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-3').click();
  cy.get('#Usernamevalue').type('admin');
  cy.get('#Passwordvalue').type('Abcde!2021');
  cy.get('#vCenternamevalue').clear().type('vCenter-002');
  cy.get('#URLvalue').click();
  cy.get('#Portvalue').clear().type('500000');
  cy.getByLabel({ label: 'Name', tag: 'input' }).click();
});

Then('the form displayed an error', () => {
  cy.getByTestId({ testId: 'Name' }).contains(
    'The name should be at least 3 characters long'
  );
  cy.getByTestId({ testId: 'URL_value' }).contains(
    'Please enter a valid URL or IP address'
  );
  cy.getByTestId({ testId: 'Port_value' })
    .eq(0)
    .contains('Invalid port number');
});

When('the user fills in the needed informations', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-004');
  cy.get('#mui-component-select-type').should('contain', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains('Poller-3').click();
  cy.get('#Usernamevalue').type('admin');
  cy.get('#Passwordvalue').type('Abcde!2021');
  cy.get('#vCenternamevalue').clear().type('vCenter-003');
  cy.get('#URLvalue').clear().type('https://10.0.0.0/sdk');
  cy.get('#Portvalue').should('have.value', '5700');
});

When('the user clicks on the Cancel button of the creation form', () => {
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
});

Then('a pop-up appears to confirm cancellation', () => {
  cy.get('*[role="dialog"]')
    .eq(1)
    .children()
    .contains('Do you want to save the changes?');
  cy.get('*[role="dialog"]')
    .eq(1)
    .children()
    .contains('If you click on Discard, your changes will not be saved.');
});

When('the user confirms the the cancellation', () => {
  cy.getByLabel({ label: 'Discard', tag: 'button' }).click();
});

Then('the creation form is closed', () => {
  cy.contains('Create additional connector configuration').should('not.exist');
});

Then('the additional connector configuration has not been created', () => {
  cy.get('*[role="rowgroup"]').should('not.contain', 'Connector-004');
});

Then('the form fields are empty', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
  cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).should('be.empty');
  cy.get('#Usernamevalue').should('be.empty');
  cy.get('#Passwordvalue').should('be.empty');
  cy.get('#vCenternamevalue').should('have.value', 'my_vcenter');
  cy.get('#URLvalue').should('have.value', 'https://<ip_hostname>/sdk');
  cy.get('#Portvalue').should('have.value', '5700');
});
