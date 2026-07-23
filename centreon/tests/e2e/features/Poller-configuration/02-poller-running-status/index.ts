import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildDeleteInstanceByNameQuery,
  buildDeletePollerByNameQuery,
  buildInsertPollerQuery,
  buildInsertRunningInstanceQuery,
  getPoller,
  legacyPollerName,
  legacyPollerUid,
  waitPollerListToLoad
} from '../common';

const isRunningColumnIndex = 4;

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given(
  'a legacy poller is running and reports its config id as runtime instance id',
  () => {
    // Reset any leftover from a previous run, then create the poller config.
    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildDeleteInstanceByNameQuery(legacyPollerName)
    });
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildDeletePollerByNameQuery(legacyPollerName)
    });
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildInsertPollerQuery(legacyPollerName, legacyPollerUid)
    });

    // A legacy Broker writes the config id (nagios_server.id), not the Snowflake
    // uid, into instances.instance_id. Resolve the auto-incremented config id and
    // seed the runtime row keyed on it.
    getPoller(legacyPollerName).then((configId) => {
      cy.requestOnDatabase({
        database: 'centreon_storage',
        query: buildInsertRunningInstanceQuery(
          configId as number,
          legacyPollerName
        )
      });
    });
  }
);

When('the user opens the pollers configuration page', () => {
  cy.visit(PAGES.configuration.pollersLegacy);
  cy.url().should('include', '/centreon/main.php?p=60901');
});

Then('the legacy poller is displayed as running', () => {
  cy.wait(waitPollerListToLoad);

  cy.getIframeBody()
    .contains('td', legacyPollerName)
    .parent('tr')
    .find('td')
    .eq(isRunningColumnIndex)
    .find('.service_ok')
    .should('exist');
});
