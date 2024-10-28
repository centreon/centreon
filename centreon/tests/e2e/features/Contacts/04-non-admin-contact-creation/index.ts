/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

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
});

afterEach(() => {
  cy.stopContainers();
});

const checkCreatedContactFromListing = () => {
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(5).click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
};

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the admin user creates a non admin contact', () => {
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/non-admin-with-access-to-allmodules.json'
  );
});

When('the admin user duplicates this contact', () => {
  checkCreatedContactFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

When('the admin delete this contact', () => {
  cy.reload();
  checkCreatedContactFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the duplicated contact is displayed in the user list', () => {
  cy.getIframeBody()
    .contains('a', 'user-with-access-to-allmodules_1')
    .should('be.visible');
});

Then('the deleted contact is not displayed in the user list', () => {
  cy.getIframeBody()
    .contains('a', 'user-with-access-to-allmodules_1')
    .should('be.visible');
  cy.getIframeBody()
    .find('a[href*="contact_id"]')
    .filter((index, element) => {
      return (
        Cypress.$(element).text().trim() === 'user-with-access-to-allmodules'
      );
    })
    .should('not.exist');
});

Then(
  'the admin can log in to Centreon Web with the duplicated contact account',
  () => {
    cy.logout();
    cy.loginByDuplicatedUser('user-with-access-to-allmodules');
    cy.url().should('include', '/centreon/monitoring/resources');
  }
);
