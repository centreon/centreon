/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

const UIDtoSearchFor = '(&(uid=centréon-ldap4)(objectClass=posixAccount))';
const DNtoSearchFor = 'cn=centréon-ldap4,ou=users,dc=centreon,dc=com';
const ldapLogin = 'centréon-ldap4';

before(() => {
  cy.startContainers({ profiles: ['openldap'] });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'POST',
    url: '/centreon/include/configuration/configObject/contact/ldapsearch.php'
  }).as('getLdaps');
});

after(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given(
  'a LDAP configuration with Users auto import disabled has been created',
  () => {
    cy.navigateTo({
      page: 'LDAP',
      rootItemNumber: 4,
      subMenu: 'Parameters'
    });
    cy.wait('@getTimeZone');
    // Click on the default ldap configuration
    cy.getIframeBody().contains('openldap').click();
    // Wait for the "Configuration Name" input to be visible in the DOM
    cy.waitForElementInIframe('#main-content', 'input[name="ar_name"]');
    // Enable LDAP authentification
    cy.getIframeBody()
      .find('input[name="ldap_auth_enable[ldap_auth_enable]"]')
      .then(($input) => {
        cy.wrap($input).parent().find('label').contains('Yes').click();
      });
    // Disable Auto import users
    cy.getIframeBody()
      .find('input[name="ldap_auto_import[ldap_auto_import]"]')
      .then(($input) => {
        cy.wrap($input).parent().find('label').contains('No').click();
      });
    // Click on the first "Save" button
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
  }
);

When(
  'the user searchs a specific user whose alias contains a special character such as an accent',
  () => {
    cy.navigateTo({
      page: 'Contacts / Users',
      rootItemNumber: 3,
      subMenu: 'Users'
    });
    cy.wait('@getTimeZone');
    // Wait for the button "LDAP Import" to be visible in the DOM
    cy.waitForElementInIframe('#main-content', 'a:contains("LDAP Import")');
    // Click on the button "LDAP Import"
    cy.getIframeBody().contains('a', 'LDAP Import').click();
    cy.wait('@getTimeZone');
    // Wait for the "Search" button to be visible in the DOM
    cy.waitForElementInIframe(
      '#main-content',
      'input[name="ldap_search_button"]'
    );
    // Type a search value in the ldap search filter
    cy.getIframeBody()
      .find('input[name="ldap_search_filter[1]"]')
      .clear()
      .type(UIDtoSearchFor);
    // Click on the "Search" button
    cy.getIframeBody().find('input[name="ldap_search_button"]').click();
    // Wait for the get Ldaps result request to be done
    cy.wait('@getLdaps');
  }
);

Then('the LDAP search result displays the expected alias', () => {
  // Check that the filter return one matched value
  cy.getIframeBody()
    .find('table.ListTable')
    .eq(1)
    .find('tbody tr.list_one')
    .should('have.length', 1);
  // Check that the filter return the right value
  cy.getIframeBody().contains(DNtoSearchFor).should('be.visible');
});

When('the user imports the searched user', () => {
  // Click on the checkbox
  cy.getIframeBody().find('#checkall').click();
  // Click on the "Import" button
  cy.getIframeBody().find('input[value="Import"]').click();
  cy.wait('@getTimeZone');
});

Then('the user is added to the contacts listing page', () => {
  // Wait for the "Contact" filter to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="searchC"]');
  // Check that the "View contact notifications" is visible
  cy.getIframeBody()
    .contains('a', 'View contact notifications')
    .should('be.visible');
  // Check that the imported ldap is visible on the contacts listing
  cy.getIframeBody().contains('a', ldapLogin).should('be.visible');
});

Given('one ldap user has been manually imported', () => {
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  // Check that the contact listing contains an imported ldap user
  cy.getIframeBody().contains('a', ldapLogin).should('be.visible');
});

Then('this user can log in to Centreon Web', () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'ldap',
    loginViaApi: false
  });
});
