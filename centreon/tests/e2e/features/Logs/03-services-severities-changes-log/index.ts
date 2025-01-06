/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import severities from '../../../fixtures/services/severity.json';

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

When('an apiV2 call is made to "Add" a service severity', () => {
  cy.addSubjectViaAPIv2(
    severities.enabled_severity,
    'centreon/api/latest/configuration/services/severities'
  );
});

Then(
  'a new service severity is displayed on the service severities page',
  () => {
    cy.navigateTo({
      page: 'Categories',
      rootItemNumber: 3,
      subMenu: 'Services'
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains("${severities.enabled_severity.name}")`
    );
    cy.getIframeBody()
      .contains('a', severities.enabled_severity.name)
      .should('be.visible');
  }
);

Then(
  'a new "Added" ligne of log is getting added to the page Administration > Logs',
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
      .should('contain.text', 'serviceseverity');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    cy.getIframeBody().contains(severities.enabled_severity.name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', severities.enabled_severity.name);
    cy.getIframeBody().contains('td', 'Create by admin').should('exist');
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(1, 1, 'sc_activate', '', '1');
    cy.checkLogDetails(1, 2, 'sc_name', '', severities.enabled_severity.name);
    cy.checkLogDetails(
      1,
      3,
      'sc_description',
      '',
      severities.enabled_severity.alias
    );
    cy.checkLogDetails(
      1,
      4,
      'sc_severity_level',
      '',
      `${severities.enabled_severity.level}`
    );
    cy.checkLogDetails(
      1,
      5,
      'sc_severity_icon',
      '',
      `${severities.enabled_severity.icon_id}`
    );
  }
);

Given('a service severity is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    severities.enabled_severity,
    '/centreon/api/latest/configuration/services/severities'
  );
});

When(
  'an apiV2 call is made to "Delete" the configured service severity',
  () => {
    cy.deleteSubjectViaAPIv2(
      '/centreon/api/latest/configuration/services/severities/5'
    );
  }
);

Then(
  'a new "Deleted" ligne of log is getting added to the page Administration > Log',
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
      .should('contain.text', 'serviceseverity');
  }
);

When(
  'an apiV2 call is made to "Update" the parameters of the configured severity',
  () => {
    cy.updateSubjectViaAPIv2(
      severities.changed_severity,
      '/centreon/api/latest/configuration/services/severities/5'
    );
  }
);

Then(
  'a new "Changed" ligne of log is getting added to the page Administration > Logs',
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
      .should('contain.text', 'serviceseverity');
  }
);

Then(
  'the informations of the log are the same as those of the updated service severity',
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
      'sc_name',
      severities.enabled_severity.name,
      severities.changed_severity.name
    );
    cy.checkLogDetails(
      1,
      2,
      'sc_description',
      severities.enabled_severity.alias,
      severities.changed_severity.alias
    );
    cy.checkLogDetails(
      1,
      3,
      'sc_severity_level',
      `${severities.enabled_severity.level}`,
      `${severities.changed_severity.level}`
    );
  }
);

Given('an enabled service severity is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    severities.enabled_severity,
    '/centreon/api/latest/configuration/services/severities'
  );
});

When(
  'an apiV2 call is made to "Disable" the configured service severity',
  () => {
    cy.updateSubjectViaAPIv2(
      severities.disabled_severity,
      '/centreon/api/latest/configuration/services/severities/5'
    );
  }
);

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
      .should('contain.text', 'serviceseverity');
  }
);

Given('a disabled service severity is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    severities.disabled_severity,
    '/centreon/api/latest/configuration/services/severities'
  );
});

When(
  'an apiV2 call is made to "Enable" the configured service severity',
  () => {
    cy.updateSubjectViaAPIv2(
      severities.enabled_severity,
      '/centreon/api/latest/configuration/services/severities/5'
    );
  }
);

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
      .should('contain.text', 'serviceseverity');
  }
);
