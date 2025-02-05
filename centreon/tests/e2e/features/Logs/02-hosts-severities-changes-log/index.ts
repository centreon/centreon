/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import severities from '../../../fixtures/host-categories/severity.json';

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

Given('a user is logged in a Centreon server via APIv2', () => {
  cy.loginAsAdminViaApiV2();
  cy.visit('/').url().should('include', '/monitoring/resources');
});

When('an apiV2 call is made to "Add" a host severity', () => {
  cy.addSubjectViaAPIv2(
    severities.default,
    '/centreon/api/latest/configuration/hosts/severities'
  );
});

Then('a new severity is displayed on the hosts severities page', () => {
  cy.navigateTo({
    page: 'Categories',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${severities.default.name}")`
  );
  cy.getIframeBody()
    .contains('a', severities.default.name)
    .should('be.visible');
});

Then(
  'a new "ADDED" ligne of log is getting added to the page Administration > Logs',
  () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_ok"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_ok', 'Added')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'hostseverity');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    cy.getIframeBody().contains(severities.default.name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', severities.default.name);
    cy.getIframeBody().contains('td', 'Create by admin').should('exist');
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(1, 1, 'hc_activate', '', '1');
    cy.checkLogDetails(1, 2, 'hc_name', '', severities.default.name);
    cy.checkLogDetails(1, 3, 'hc_alias', '', severities.default.alias);
    cy.checkLogDetails(
      1,
      4,
      'hc_severity_level',
      '',
      `${severities.default.level}`
    );
    cy.checkLogDetails(
      1,
      5,
      'hc_severity_icon',
      '',
      `${severities.default.icon_id}`
    );
  }
);

Given('a host severity is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    severities.default,
    '/centreon/api/latest/configuration/hosts/severities'
  );
});

When('an apiV2 call is made to "Delete" the configured host severity', () => {
  cy.deleteSubjectViaAPIv2(
    '/centreon/api/latest/configuration/hosts/severities/1'
  );
});

Then(
  'a new "DELETED" ligne of log is getting added to the page Administration > Log',
  () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_critical"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_critical', 'Deleted')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'hostseverity');
  }
);

When(
  'an apiV2 call is made to "Update" the parameters of the configured host severity',
  () => {
    cy.updateSubjectViaAPIv2(
      severities.changed_severity,
      '/centreon/api/latest/configuration/hosts/severities/1'
    );
  }
);

Then(
  'a new "CHANGED" ligne of log is getting added to the page Administration > Logs',
  () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_warning"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_warning', 'Changed')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'hostseverity');
  }
);

Then(
  'the informations of the log are the same as those of the updated host severity',
  () => {
    cy.getIframeBody().contains(severities.changed_severity.name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', severities.changed_severity.name);
    cy.getIframeBody().contains('td', 'Change by admin').should('exist');
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(
      1,
      1,
      'hc_name',
      severities.default.name,
      severities.changed_severity.name
    );
    cy.checkLogDetails(
      1,
      2,
      'hc_alias',
      severities.default.alias,
      severities.changed_severity.alias
    );
  }
);

Given('an enabled host severity is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    severities.default,
    '/centreon/api/latest/configuration/hosts/severities'
  );
});

When('an apiV2 call is made to "Disable" the configured host severity', () => {
  cy.updateSubjectViaAPIv2(
    severities.disabled_severity,
    '/centreon/api/latest/configuration/hosts/severities/1'
  );
});

Then(
  'a new "DISABLED" ligne of log is getting added to the page Administration > Logs',
  () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_critical"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_critical', 'Disabled')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'hostseverity');
  }
);

Given('a disabled host severity is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    severities.disabled_severity,
    '/centreon/api/latest/configuration/hosts/severities'
  );
});

When('an apiV2 call is made to "Enable" the configured host severity', () => {
  cy.updateSubjectViaAPIv2(
    severities.enabled_severity,
    '/centreon/api/latest/configuration/hosts/severities/1'
  );
});

Then(
  'a new "ENABLED" ligne of log is getting added to the page Administration > Logs',
  () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_ok"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_ok', 'Enabled')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'hostseverity');
  }
);
