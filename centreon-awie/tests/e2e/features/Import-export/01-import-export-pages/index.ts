import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { INTERCEPTORS } from '../../../fixtures/shared/constants/interceptors';
import { PAGES } from '../../../fixtures/shared/constants/pages';

beforeEach(() => {
  // loginByTypeOfUser waits on this alias, so register it up front.
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');

  // Use the dedicated AWIE image built by the dockerize job (not the slim
  // centreon-web base image, which is not published for this branch).
  cy.startContainers({ moduleName: 'centreon-awie', useSlim: false });
});

Given('a super administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

// Legacy iframe page: tick the contacts checkbox and submit the export.
When('the user exports the contacts from the AWIE export page', () => {
  cy.visit(PAGES.awie.export);
  cy.waitForElementInIframe('#main-content', '#contact');
  cy.getIframeBody().find('#contact').check();
  cy.getIframeBody().find('.bt_success').click();
});

Then('an export archive is generated', () => {
  // The archive is written to the web container's /tmp before download. execInContainer
  // has no auto-retry and the file appears asynchronously, so poll /tmp a few times.
  const assertArchiveExists = (remainingAttempts: number): void => {
    cy.execInContainer({ command: 'ls /tmp', name: 'web' }).then(
      ({ output }) => {
        if (/\.zip/.test(output)) {
          return;
        }

        if (remainingAttempts <= 0) {
          throw new Error(
            `No .zip export archive found in /tmp. Got:\n${output}`
          );
        }

        cy.wait(1000);
        assertArchiveExists(remainingAttempts - 1);
      }
    );
  };

  assertArchiveExists(10);
});

When('the user opens the AWIE import page', () => {
  cy.visit(PAGES.awie.import);
});

Then('the import form accepts a zip archive', () => {
  cy.waitForElementInIframe('#main-content', '#file');
});

afterEach(() => {
  cy.stopContainers();
});
