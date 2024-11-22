/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

beforeEach(() => {
  cy.startContainers();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/non-admin-with-access-to-allmodules.json'
  );
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

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given(
  'the user replaced the default page connection with Home > Dashboards',
  () => {
    cy.navigateTo({
      page: 'My Account',
      rootItemNumber: 4,
      subMenu: 'Parameters'
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="contact_name"]');
    cy.getIframeBody()
      .find('select[name="default_page"]')
      .select('Home > Dashboards');
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
  }
);

When('the admin user logs back to Centreon', () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Then('the active page is Home > Dashboards', () => {
  cy.url().should('include', '/home/dashboards');
});

Given('an non-admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-with-access-to-allmodules',
    loginViaApi: false
  });
});

Given('the user has access to all menus', () => {
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.getIframeBody().contains('a', 'name-non-admin-ACLMENU').click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="acl_topo_name"]');
  const pagesCheckboxIds = ['i0', 'i1', 'i2', 'i3', 'i4'];
  pagesCheckboxIds.forEach((id) => {
    cy.getIframeBody().find(`#${id}`).should('be.checked');
  });
});

Given(
  'the user replaced the default page connection with Configuration > Hosts',
  () => {
    cy.navigateTo({
      page: 'My Account',
      rootItemNumber: 4,
      subMenu: 'Parameters'
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="contact_name"]');
    cy.getIframeBody()
      .find('select[name="default_page"]')
      .select('Configuration > Hosts');
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
  }
);

When('the non-admin user logs back to Centreon', () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'user-with-access-to-allmodules',
    loginViaApi: false
  });
});

Then('the active page is Configuration > Hosts', () => {
  cy.getIframeBody()
    .find('a.pathWay')
    .eq(0)
    .should('have.text', 'Configuration');
  cy.getIframeBody().find('a.pathWay').eq(1).should('have.text', 'Hosts');
});
