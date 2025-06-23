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

Given('the ldap user has rights to access the contacts listing page', () => {
  cy.navigateTo({
    page: 'Access Groups',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');
  // Click on the 'All' access group
  cy.getIframeBody().contains('a', 'ALL').click();
  // Wait for the 'Group Name' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="acl_group_name"]');
  // Select the ldap user from the Linked Contacts list
  cy.getIframeBody().find('select#cg_contacts-f')
  .select(ldapLogin);
  // Add the selected ldap user
  cy.getIframeBody().find('input[name="add"]').eq(0).click();
  // Click on the first 'Save' button
  cy.getIframeBody().find('input[name="submitC"]').eq(0).click();
  cy.exportConfig();
  // Go to the default page
  cy.visit('/');
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');
  // Click on the 'Add' button to add a new menu access
  cy.getIframeBody().contains('a', 'Add').eq(0).click();
  // Wait for the 'ACL Definition' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="acl_topo_name"]');
  // Type a value in the field 'ACL Definition'
  cy.getIframeBody().find('input[name="acl_topo_name"]').type('action');
  // Chose the 'ALL' option from the Linked Groups list
  cy.getIframeBody().find('select#acl_groups-f')
  .select('ALL');
  // Add the select option
  cy.getIframeBody().find('input[name="add"]').eq(0).click();
  // Check the 'Configuration' accessible page
  cy.getIframeBody().find('input[name="acl_r_topos[6]"]').click({force: true});
  // Click to open the sub menus 
  cy.getIframeBody().find('#img_3').click();
  // Check the 'Users' accessible page
  cy.getIframeBody().find('#img_3_2').click();
  // Check 'Read/Write' option
  cy.getIframeBody().find('input[name="acl_r_topos[84]"][value="1"]')
  .check();
  // Click on the first 'Save' button
  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
  cy.exportConfig();
});

When('the ldap user goes to the contacts listing page', () => {
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: 0,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
});

Then('the ldap user cannot update the contact dn', () => {
  // Click on the ldap user 
  cy.getIframeBody().contains('a', ldapLogin).click();
  cy.wait('@getTimeZone');
  // Wait for the 'Alias / Login' field to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="contact_alias"]');
  // Click on the 'Centreon Authentication' tab
  cy.getIframeBody().contains('a', 'Centreon Authentication').click();
  // Click outside the form
  cy.get('body').click(0, 0);
  // Check that the 'DN' field is hidden
  cy.getIframeBody().find('input#contact_ldap_dn')
  .should('have.attr', 'type', 'hidden');
});

Then('the ldap user cannot update the contact password', () => {
  // Check that the 'password' field doesn't exist
  cy.getIframeBody().find('input#paswd1').should('not.exist');
});
