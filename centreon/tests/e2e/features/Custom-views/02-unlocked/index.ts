/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { addCustomView, addSharedView, deleteCustomView, shareUnlockedCustomView, visitCustomViewPage } from '../common';

const viewName = 'Unlocked-View';
const logByAclUser = () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'custom-view-acl-user',
    loginViaApi: false
  });
  visitCustomViewPage();
};

before(() => {
  cy.startContainers();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/custom-view-acl-user.json'
  );
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
    method: 'GET',
    url: '/centreon/include/home/customViews/views.php?currentView=*'
  }).as('getViews');
  cy.intercept({
    method: 'POST',
    url: '/centreon/include/home/customViews/action.php'
  }).as('action');
  cy.intercept({
    method: 'Get',
    url: '/centreon/api/internal.php?object=centreon_configuration_contact&action=list*'
  }).as('getContacts');
  cy.intercept({
    method: 'Get',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_contactgroup&action=list*'
  }).as('getContactGroups');
});

after(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('the admin is on the "Home > Custom Views" page', () => {
  visitCustomViewPage();
});

When('the admin adds a new unlocked custom view shared with a configured non admin user', () => {
  addCustomView(viewName, false);
  shareUnlockedCustomView("Unlocked users", "custom-view-acl-user");
});

Then('the view is added', () => {
  cy.getIframeBody().contains('a', viewName).should('exist');
});

Given('a shared custom view with the non admin user', () => {
  visitCustomViewPage();
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', viewName).should('exist');
});

When('the non admin user is using the shared view', () => {
  logByAclUser();
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Wait until the button 'Add view' is visible 
  cy.waitForElementInIframe(
    '#main-content',
    'button:contains("Add view")'
  );
  addSharedView(viewName);
});

When('the non admin user is using the configured shared view', () => {
  logByAclUser();
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', viewName).should('exist');
});

Then('he can modify the content of the shared view', () => {
  // Check that the buttons 'Edit View' and 'Add widget' are not disabled
  ["editView", "addWidget" ].forEach((style) => {
    cy.getIframeBody().find(`button.${style}`).should('not.be.disabled')
   });
});

When('he removes the shared view', () => {
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  deleteCustomView();
});

Then('the view is not visible anymore', () => {
  cy.getIframeBody().contains('a', viewName).should('not.exist');
});

Then('the user can use the shared view again', () => {
  addSharedView(viewName);
});

When('the user modifies the custom view', () => {
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Wait until the button 'Add view' is visible 
  cy.waitForElementInIframe(
    '#main-content',
    'button:contains("Add view")'
  );
  // Click on the 'Edit View' button
  cy.getIframeBody().find('button.editView').click();
  // Type a new value in the field 'Name' of the custom view
  cy.getIframeBody().find('#editView').find('input[name="name"]')
    .clear().type(`${viewName}-changed`);
  // Click on the 'Submit' button
  cy.getIframeBody().find('#editView').find('input[name="submit"]').eq(0).click();
  cy.wait('@getViews');
});

Then('the changes are reflected on all users displaying the custom view', () => {
  cy.getIframeBody().contains('a', `${viewName}-changed`).should('exist');
});

When('the owner removes the view', () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  visitCustomViewPage();
  cy.wait('@getViews');
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  deleteCustomView();
});

Then('the view remains visible for all users displaying the custom view', () => {
  logByAclUser();
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', `${viewName}-changed`).should('exist');

  /** This part is for delete the shared custom view for the acl user **/
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  deleteCustomView();
  /*************************************************************** */
});

Then('the view is removed for the owner', () => {
  cy.getIframeBody().contains('a', `${viewName}-changed`).should('not.exist');
});

Given('a shared custom view with a group', () => {
  /*** this part is for setting the Guest contact group to the configured acl user ***/
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a','custom-view-acl-user').click();
  cy.waitForElementInIframe('#main-content', 'input[name="contact_alias"]');
  cy.getIframeBody().find('input[placeholder="Linked to Contact Groups"]').click();
  cy.wait('@getContactGroups');
  cy.getIframeBody().contains('Guest').click();
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();

  /*** this part is for adding an unlocked custom view with the group Guest ***/
  visitCustomViewPage();
  addCustomView(viewName, false);
  shareUnlockedCustomView("Unlocked user groups", "Guest");
});

When('an user of this group is using the shared view', () => {
  logByAclUser();
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Wait until the button 'Add view' is visible 
  cy.waitForElementInIframe(
    '#main-content',
    'button:contains("Add view")'
  );
  addSharedView(viewName);
});

When('a configured shared custom view with a group', () => {
   visitCustomViewPage();
   cy.wait('@getViews');
   cy.getIframeBody().contains('a', viewName).should('exist');
});

When('an user of this group is using the configured shared view', () => {
  logByAclUser();
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', viewName).should('exist');
});