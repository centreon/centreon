import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkIfConfigurationIsExported,
  checkIfMethodIsAppliedToPollers,
  clearCentenginLogs,
  getPoller,
  insertHost,
  insertPollerConfigAclUser,
  removeFixtures
} from '../common';

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentification');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

Given(
  'I am granted the rights to access the poller page and export the configuration',
  () => {
    insertPollerConfigAclUser();
  }
);

Given('I am logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'user', preserveToken: true });
});

Given('I the platform is configured with some resources', () => {
  insertHost();
});

Given('some pollers are created', () => {
  getPoller('Central')
    .as('pollerId')
    .then(() => {
      cy.get('@pollerId').should('be.greaterThan', 0);
    });
});

Given('some post-generation commands are configured for each poller', () => {
  cy.get('@pollerId').then((pollerId) => {
    cy.visit(`/centreon/main.php?p=60901&o=c&server_id=${pollerId}`);

    cy.getIframeBody().find('form #pollercmd_controls').click();

    cy.getIframeBody()
      .find('form #pollercmd_0')
      .select(2)
      .should('have.value', 39);

    cy.getIframeBody().find('form input[name="submitC"]').eq(0).click();
  });
});

When('I visit the export configuration page', () => {
  cy.visit('/centreon/main.php?p=60901');
});

Then(
  'there is an indication that the configuration have changed on the listed pollers',
  () => {
    cy.getIframeBody().find('form .list_one>td').eq(5).contains('Yes');
  }
);

When('I select some pollers', () => {
  cy.getIframeBody()
    .find('form input[name="select[1]"]')
    .check({ force: true });

  cy.getIframeBody()
    .find('form .list_one>td')
    .eq(1)
    .invoke('text')
    .as('pollerName');
});

When('I click on the Export configuration button', () => {
  cy.get('@pollerId').then((pollerId) => {
    cy.visit(`/centreon/main.php?p=60902&poller=${pollerId}`).wait(
      '@getTimeZone'
    );
  });
});

Then('I am redirected to generate page', () => {
  cy.get('@pollerId').then((pollerId) => {
    cy.url().should('include', `/centreon/main.php?p=60902&poller=${pollerId}`);
  });
});

Then('the selected poller names are displayed', () => {
  cy.get<string>('@pollerName').then((pollerName) => {
    cy.getIframeBody()
      .find('form span[class="selection"]')
      .eq(0)
      .contains(pollerName);
  });
});

When('I select all action checkboxes', () => {
  cy.getIframeBody()
    .find('form input[name="gen"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="debug"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="move"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="restart"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="postcmd"]')
    .eq(0)
    .check({ force: true });
});

When('I select the {string} export method', (method: string) => {
  cy.getIframeBody()
    .find('form select[name="restart_mode"]')
    .eq(0)
    .select(method);
});

When('I click on the export button', () => {
  clearCentenginLogs()
    .getIframeBody()
    .find('form input[name="submit"]')
    .eq(0)
    .click();
});

Then('the configuration is generated on selected pollers', () => {
  checkIfConfigurationIsExported();
});

Then('the selected pollers are {string}', (poller_action: string) => {
  checkIfMethodIsAppliedToPollers(poller_action);

  cy.logout().reload();

  removeFixtures();
});
