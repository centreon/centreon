import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { PAGES } from '../../../fixtures/shared/constants/pages';

beforeEach(() => {
  // loginByTypeOfUser waits on this alias, so it must be registered up front.
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');

  cy.startContainers();
});

Given('an admin user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

// AWIE is a legacy PHP module (iframe pages): the export page exposes the poller
// selector (#poller), the import page exposes the file input (#file).
When('the user opens the AWIE export page', () => {
  cy.visit(PAGES.awie.export);
});

Then('the export form is displayed', () => {
  cy.waitForElementInIframe('#main-content', '#poller');
});

When('the user opens the AWIE import page', () => {
  cy.visit(PAGES.awie.import);
});

Then('the import form is displayed', () => {
  cy.waitForElementInIframe('#main-content', '#file');
});

afterEach(() => {
  cy.stopContainers();
});
