/* eslint-disable prettier/prettier */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import categories from '../../../fixtures/services/category.json';

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

When('an apiV2 call is made to "Add" a service category', () => {
  cy.addSubjectViaAPIv2(
    categories.default,
    '/centreon/api/latest/configuration/services/categories'
  );
});

Then(
  'a new service category is displayed on the service categories page',
  () => {
    cy.navigateTo({
      page: 'Categories',
      rootItemNumber: 3,
      subMenu: 'Services'
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
  'a new "ADDED" line of log is getting added to the page Administration > Logs',
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
      .should('contain.text', 'servicecategories');
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
    cy.checkLogDetails(1, 1, 'sc_activate', '', '1');
    cy.checkLogDetails(1, 2, 'sc_name', '', categories.default.name);
    cy.checkLogDetails(1, 3, 'sc_alias', '', categories.default.alias);
  }
);

Given('a service category is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    categories.default,
    '/centreon/api/latest/configuration/services/categories'
  );
});

When(
  'an apiV2 call is made to "Delete" the configured service category',
  () => {
    cy.deleteSubjectViaAPIv2(
      '/centreon/api/latest/configuration/services/categories/5'
    );
  }
);

Then(
  'a new "DELETED" line of log is getting added to the page Administration > Log',
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
      .should('contain.text', 'servicecategories');
  }
);

When('the user changes some properties of the configured service category from UI', () => {
  cy.navigateTo({
    page: 'Categories',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${categories.default.name}")`
  );
  cy.getIframeBody()
    .contains('a', categories.default.name)
    .click();
  cy.getIframeBody().waitForElementInIframe('#main-content', 'input[name="sc_name"]');
  cy.getIframeBody().find('input[name="sc_name"]').clear().type(categories['service-category-changed'].name);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then(
  'a new "CHANGED" line of log is getting added to the page Administration > Logs',
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
      .should('contain.text', 'servicecategories');
  }
);

Then(
  'the informations of the log are the same as the changed properties',
  () => {
    cy.getIframeBody().contains(categories['service-category-changed'].name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', categories['service-category-changed'].name);
    cy.getIframeBody().contains('td', 'Change by admin').should('exist');
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(
      1,
      2,
      'sc_name',
      categories.default.name,
      categories['service-category-changed'].name
    );
  }
);

Given('an enabled service category is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    categories.default,
    '/centreon/api/latest/configuration/services/categories'
  );
});

When(
  'the user disables the configured service category from UI',
  () => {
    cy.navigateTo({
        page: 'Categories',
        rootItemNumber: 3,
        subMenu: 'Services'
      });
      cy.wait('@getTimeZone');
      cy.waitForElementInIframe(
        '#main-content',
        `a:contains("${categories.default.name}")`
      );
      cy.getIframeBody().find('img[alt="Disabled"]').eq(1).click();
      cy.wait('@getTimeZone');
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
      .should('contain.text', 'servicecategories');
  }
);

Given('a disabled service category is configured via APIv2', () => {
  cy.addSubjectViaAPIv2(
    categories['service-category-changed'],
    '/centreon/api/latest/configuration/services/categories'
  );
});

When(
  'the user enables the configured service category from UI',
  () => {
    cy.navigateTo({
        page: 'Categories',
        rootItemNumber: 3,
        subMenu: 'Services'
      });
      cy.wait('@getTimeZone');
      cy.waitForElementInIframe(
        '#main-content',
        `a:contains("${categories.default.name}")`
      );
      cy.getIframeBody().find('img[alt="Enabled"]').eq(2).click();
      cy.wait('@getTimeZone');
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
      .should('contain.text', 'servicecategories');
  }
);
