import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { initializeOIDCUserAndGetLoginPage, removeContact } from '../common';

before(() => {
  initializeOIDCUserAndGetLoginPage();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

Given('an administrator logged in the platform', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    preserveToken: true
  }).wait('@getNavigationList');
});

When('the administrator first configures the authentication mode', () => {
  cy.navigateTo({
    page: 'Authentication',
    rootItemNumber: 4
  })
    .get('div[role="tablist"] button:nth-child(2)')
    .eq(0)
    .contains('OpenID Connect Configuration')
    .click();
});

Then(
  'default authentication mode must be Mixed and users created locally to centreon platform must be able to authenticate',
  () => {
    cy.getByLabel({
      label: 'Mixed',
      tag: 'input'
    })
      .should('be.checked')
      .and('have.value', 'false')
      .logout()
      .reload()
      .loginByTypeOfUser({
        jsonName: 'user-non-admin-for-OIDC-authentication',
        preserveToken: true
      })
      .wait('@getNavigationList')
      .logout()
      .reload();
  }
);

after(() => {
  removeContact();
});
