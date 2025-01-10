/* eslint-disable prettier/prettier */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import categories from '../../../fixtures/host-categories/category.json';

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

When('an apiV2 call is made to "Add" a host category', () => {
  cy.addSubjectViaAPIv2(
    categories.default,
    '/centreon/api/latest/configuration/hosts/categories'
  );
});

Then(
  'a new host category is displayed on the host categories page',
  () => {
    cy.navigateTo({
      page: 'Categories',
      rootItemNumber: 3,
      subMenu: 'Hosts'
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains("${categories.default.name}")`
    );
    cy.getIframeBody()
      .contains('a', categories.default.name)
      .should('be.visible');
  }
);

Then(
  'a new "Added" line of log is getting added to the page Administration > Logs',
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
      .should('contain.text', 'hostcategories');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    cy.getIframeBody().contains(categories.default.name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', categories.default.name);
    cy.getIframeBody().contains('td', 'Create by admin').should('exist');
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(1, 1, 'hc_activate', '', '1');
    cy.checkLogDetails(1, 2, 'hc_comment', '', categories.default.comment);
    cy.checkLogDetails(1, 3, 'hc_name', '', categories.default.name);
    cy.checkLogDetails(1, 4, 'hc_alias', '', categories.default.alias);
  }
);

Given('a host category is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    categories.default,
    '/centreon/api/latest/configuration/hosts/categories'
  );
});

When(
  'an apiV2 call is made to "Delete" the configured host category',
  () => {
    cy.deleteSubjectViaAPIv2(
      '/centreon/api/latest/configuration/hosts/categories/1'
    );
  }
);

Then(
  'a new "Deleted" line of log is getting added to the page Administration > Log',
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
      .should('contain.text', 'hostcategories');
  }
);

When('an APIv2 call is made to "Update" the configured host category', () => {
    cy.updateSubjectViaAPIv2(
        categories.forTest,
        '/centreon/api/latest/configuration/hosts/categories/1'
    );
});

Then(
  'a new "Changed" line of log is getting added to the page Administration > Logs',
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
      .should('contain.text', 'hostcategories');
  }
);

Then(
  'the informations of the log are the same as those passed to te "PUT" api call',
  () => {
    cy.getIframeBody().contains(categories.forTest.name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', categories.forTest.name);
    cy.getIframeBody().contains('td', 'Change by admin').should('exist');
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(1, 1, 'hc_comment', categories.default.comment, categories.forTest.comment);
    cy.checkLogDetails(1, 2, 'hc_name', categories.default.name, categories.forTest.name);
    cy.checkLogDetails(1, 3, 'hc_alias', categories.default.alias, categories.forTest.alias);
  }
);

Given('an enabled host category is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    categories.default,
    '/centreon/api/latest/configuration/hosts/categories'
  );
});

When(
  'an APIv2 call is made to "Disable" the configured host category',
  () => {
    cy.updateSubjectViaAPIv2(
        categories.disabled,
        '/centreon/api/latest/configuration/hosts/categories/1'
    );
  }
);

Then(
  'a new "DISABLED" line of log is getting added to the page Administration > Logs',
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
      .should('contain.text', 'hostcategories');
  }
);

Given('a disabled host category is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    categories.disabled,
    '/centreon/api/latest/configuration/hosts/categories'
  );
});

When(
  'an APIv2 call is made to "Enable" the disabled host category',
  () => {
    cy.updateSubjectViaAPIv2(
        categories.default,
        '/centreon/api/latest/configuration/hosts/categories/1'
    );
}
);

Then(
  'a new "ENABLED" line of log is getting added to the page Administration > Logs',
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
      .should('contain.text', 'hostcategories');
  }
);
