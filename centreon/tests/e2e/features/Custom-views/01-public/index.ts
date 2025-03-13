/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { addCustomView, addSharedView } from '../common';

const logByAclUser = () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'custom-view-acl-user',
    loginViaApi: false
  });
  cy.visit('/centreon/main.php?p=103');
  cy.wait('@getTimeZone');
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

Given('a publicly shared custom view is configured', () => {
  addCustomView('public-view', true);
});

Given('a user with custom views edition rights on the custom views listing page', () => {
  logByAclUser();
});

When('the user wishes to add a new custom view', () => {
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Wait until the button 'Add view' is visible 
  cy.waitForElementInIframe(
    '#main-content',
    'button:contains("Add view")'
  );
});

When('he can add the public view', () => {
  addSharedView('public-view');
});

Then('he cannot modify the content of the shared view', () => {
   // Check that the buttons 'Edit View', 'Share view' and 'Add widget' are disabled
   ["editView", "shareView", "addWidget" ].forEach((style) => {
    cy.getIframeBody().find(`button.${style}`).should('be.disabled')
   })
});

Given('a publicly shared custom view is configured by the owner', () => {
  // Visit the page 'Home > Custom Views'
  cy.visit('/centreon/main.php?p=103')
  cy.wait('@getTimeZone');
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', 'public-view').should('exist');
});

Given('the user is using the public view', () => {
  cy.wait('@getViews');
  cy.waitForElementInIframe(
    '#main-content',
    'a:contains("public-view")'
  );
  cy.getIframeBody().contains('a', 'public-view').should('exist');
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
});

When('he removes the shared view', () => {
  cy.wait('@action');
  cy.waitForElementInIframe('#main-content', 'button.deleteView');
  // Click on the 'Delete view' button
  cy.getIframeBody().find('button.deleteView').click({force: true});
  // Click on the delete in the confirmation popup
  cy.getIframeBody().find('#deleteViewConfirm .bt_danger').click(); 
});

Then('the view is not visible anymore', () => {
  cy.getIframeBody().contains('a', 'public-view').should('not.exist');
});

Then('the user can use the public view again', () => {
  addSharedView('public-view');
});

When('the owner removes the view', () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  // Visit the page 'Home > Custom Views'
  cy.visit('/centreon/main.php?p=103')
  cy.wait('@getTimeZone');
  cy.wait('@getViews');
  // Wait until the "Show/Hide edit mode" icon is visible
  cy.waitForElementInIframe('#main-content', 'a[title="Show/Hide edit mode"]');
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Click on the 'Delete view' button
  cy.getIframeBody().find('button.deleteView').click();
  // Click on the delete in the confirmation popup
  cy.getIframeBody().find('#deleteViewConfirm .bt_danger').click(); 
});

Then('the view is not visible anymore for the user', () => {
  logByAclUser();
  cy.getIframeBody().contains('a', 'public-view').should('not.exist');
});
