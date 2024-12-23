/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import ldaps from '../../../fixtures/ldaps/ldap.json';

const checkFirstLdapFromListing = () => {
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(1).click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setA(this.form.elements['o1'].value); submit(); }"
    );
};

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/non-admin-with-access-to-allmodules.json'
  );
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the user adds a new LDAP configuration', () => {
  cy.navigateTo({
    page: 'LDAP',
    rootItemNumber: 4,
    subMenu: 'Parameters'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateLdap(ldaps.default);
});

Then('the LDAP configuration is saved with its properties', () => {
  cy.getIframeBody().contains('a', ldaps.default.name).should('be.visible');
});

Given('one LDAP configuration has been created', () => {
  cy.navigateTo({
    page: 'LDAP',
    rootItemNumber: 4,
    subMenu: 'Parameters'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateLdap(ldaps.default);
});

When(
  'the user modifies some properties of the existing LDAP configuration',
  () => {
    cy.getIframeBody().contains('a', ldaps.default.name).click();
    cy.addOrUpdateLdap(ldaps.ldap1);
  }
);

Then('all changes are saved', () => {
  cy.getIframeBody().contains('a', ldaps.ldap1.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="ar_name"]');
  cy.getIframeBody()
    .find('input[name="ar_name"]')
    .should('have.value', ldaps.ldap1.name);
  cy.getIframeBody()
    .find('textarea[name="ar_description"]')
    .should('have.value', ldaps.ldap1.desc);
  cy.fixture(`../fixtures/users/user-with-access-to-allmodules.json`).then(
    (user) => {
      cy.getIframeBody()
        .find('input[name="bind_dn"]')
        .should('have.value', user.login);
    }
  );
  cy.getIframeBody()
    .find('select[id="ldap_template"]')
    .should('have.value', 'Posix');
  cy.getIframeBody()
    .find('input[name="user_base_search"]')
    .should('have.value', ldaps.ldap1.userBaseSearch);
  cy.getIframeBody()
    .find('input[name="group_base_search"]')
    .should('have.value', ldaps.ldap1.groupBaseSearch);
  cy.getIframeBody()
    .find('input[name="user_group"]')
    .should('have.value', ldaps.ldap1.userGrpAttribute);
});

When('the user has deleted the existing LDAP configuration', () => {
  checkFirstLdapFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'this configuration has disappeared from the LDAP configuration list',
  () => {
    cy.getIframeBody().contains(ldaps.default.name).should('not.exist');
  }
);
