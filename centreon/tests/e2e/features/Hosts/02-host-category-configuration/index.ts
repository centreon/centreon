import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored } from 'commons';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import hostCategories from '../../../fixtures/host-categories/category.json';

const checkFirstHostCategoryFromListing = () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(1).click();
  cy.getIframeBody()
    .find('select')
    .eq(0)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
};

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: 'host2',
    template: 'generic-host'
  }).applyPollerConfiguration();

  checkHostsAreMonitored([{ name: 'host2' }]);
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('a host category is configured', () => {
  cy.request({
    body: hostCategories.default,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'POST',
    url: '/centreon/api/beta/configuration/hosts/categories'
  }).then((response) => {
    expect(response.status).to.eq(201);
  });
});

When('the user changes the properties of a host category', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(hostCategories.default.name).click();
  cy.waitUntil(
    () => {
      return cy
        .getByLabel({ label: 'Up status hosts', tag: 'a' })
        .invoke('text')
        .then((text) => {
          if (text !== '2') {
            cy.exportConfig();
          }

          return text === '2';
        });
    },
    { interval: 20000, timeout: 100000 }
  );
  cy.waitForElementInIframe('#main-content', 'input[name="hc_name"]');
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .clear()
    .type(hostCategories.forTest.name);
  cy.getIframeBody()
    .find('input[name="hc_alias"]')
    .clear()
    .type(hostCategories.forTest.alias);
  cy.getIframeBody().find('input[placeholder="Linked Hosts"]').click();
  cy.getIframeBody().find('div[title="host2"]').click();
  cy.getIframeBody().find('input[placeholder="Linked Host Template"]').click();
  cy.getIframeBody().find('div[title="generic-host"]').click();
  cy.getIframeBody().contains('label', 'Disabled').click();
  cy.getIframeBody()
    .find('textarea[name="hc_comment"]')
    .clear()
    .type(hostCategories.forTest.comment);

  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  cy.getIframeBody().contains(hostCategories.forTest.name).should('exist');
  cy.getIframeBody().contains(hostCategories.forTest.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="hc_name"]');
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .should('have.value', hostCategories.forTest.name);
  cy.getIframeBody()
    .find('input[name="hc_alias"]')
    .should('have.value', hostCategories.forTest.alias);
  cy.getIframeBody()
    .find('span.select2-content')
    .eq(0)
    .should('have.attr', 'title', 'host2');
  cy.getIframeBody()
    .find('span.select2-content')
    .eq(1)
    .should('have.attr', 'title', 'generic-host');
  cy.checkLegacyRadioButton('Disabled');
  cy.getIframeBody()
    .find('textarea[name="hc_comment"]')
    .should('have.value', hostCategories.forTest.comment);
});

When('the user duplicates a host category', () => {
  checkFirstHostCategoryFromListing();
  cy.getIframeBody().find('select').eq(0).select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new host category is created with identical properties', () => {
  cy.getIframeBody()
    .contains(`${hostCategories.default.name}_1`)
    .should('exist');
  cy.getIframeBody().contains(`${hostCategories.default.name}_1`).click();
  cy.waitForElementInIframe('#main-content', 'input[name="hc_name"]');
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .should('have.value', `${hostCategories.default.name}_1`);
  cy.getIframeBody()
    .find('input[name="hc_alias"]')
    .should('have.value', hostCategories.default.alias);
  cy.checkLegacyRadioButton('Enabled');
  cy.getIframeBody()
    .find('textarea[name="hc_comment"]')
    .should('have.value', hostCategories.default.comment);
});

When('the user deletes a host category', () => {
  checkFirstHostCategoryFromListing();
  cy.getIframeBody().find('select').eq(0).select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted host category is not visible anymore on the host category page',
  () => {
    cy.getIframeBody()
      .contains(hostCategories.default.name)
      .should('not.exist');
    cy.getIframeBody()
      .find('table.ListTable tbody tr')
      .should('have.length', 1);
  }
);

const addedCategory = 'host-category-added';
const severityCategory = 'host-category-severity';
const regularCategory = 'host-category-regular';

const openAddHostCategoryForm = (): void => {
  cy.visit(`${PAGES.configuration.hostCategoriesLegacy}&o=a`);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="hc_name"]');
};

const submitHostCategoryForm = (): void => {
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
};

When('the user adds a host category from the form', () => {
  openAddHostCategoryForm();
  cy.getIframeBody().find('input[name="hc_name"]').clear().type(addedCategory);
  cy.getIframeBody().find('input[name="hc_alias"]').clear().type(addedCategory);
  submitHostCategoryForm();
});

Then('the added host category appears in the listing', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('table.ListTable')
    .contains('tr', addedCategory)
    .should('exist');
});

When('the user adds a host category with the severity type enabled', () => {
  openAddHostCategoryForm();
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .clear()
    .type(severityCategory);
  cy.getIframeBody()
    .find('input[name="hc_alias"]')
    .clear()
    .type(severityCategory);
  // Enable the severity type: the level and icon fields become required.
  cy.getIframeBody().find('input[name="hc_type"]').check({ force: true });
  cy.getIframeBody()
    .find('input[name="hc_severity_level"]')
    .clear({ force: true })
    .type('1', { force: true });
  cy.getIframeBody()
    .find('select[name="hc_severity_icon"] option')
    .eq(1)
    .then((option) => {
      cy.getIframeBody()
        .find('select[name="hc_severity_icon"]')
        .select(option.val() as string);
    });
  submitHostCategoryForm();
});

Then('the host category is listed as a severity category', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('table.ListTable')
    .contains('tr', severityCategory)
    .should('contain.text', 'Severity');
});

When('the user adds a host category with the severity type disabled', () => {
  openAddHostCategoryForm();
  cy.getIframeBody()
    .find('input[name="hc_name"]')
    .clear()
    .type(regularCategory);
  cy.getIframeBody()
    .find('input[name="hc_alias"]')
    .clear()
    .type(regularCategory);
  // Deliberately leave the severity type off; the insert path must not store a
  // stray level/icon (the hc_type gate fix).
  submitHostCategoryForm();
});

Then('the host category is listed as a regular category', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('table.ListTable')
    .contains('tr', regularCategory)
    .should('contain.text', 'Regular')
    .and('not.contain.text', 'Severity');
});

When('the user searches the listing for the configured category', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('input[name="searchH"]')
    .clear()
    .type(hostCategories.default.name);
  cy.getIframeBody().find('form[name="form"]').submit();
  cy.wait('@getTimeZone');
});

Then('only the matching category is listed', () => {
  cy.getIframeBody()
    .find('table.ListTable')
    .contains('tr', hostCategories.default.name)
    .should('exist');
});

When('the user searches the listing with special characters', () => {
  cy.getIframeBody().find('input[name="searchH"]').clear().type('a"&% _');
  cy.getIframeBody().find('form[name="form"]').submit();
  cy.wait('@getTimeZone');
});

Then('the listing renders with no result and no error', () => {
  // The page must still render (no SQL error, no broken layout): a crafted
  // search only filters, it never breaks the listing.
  cy.getIframeBody().find('table.ListTable').should('exist');
  cy.getIframeBody().contains(hostCategories.default.name).should('not.exist');
});

When('the user bulk disables the configured host category', () => {
  checkFirstHostCategoryFromListing();
  cy.getIframeBody().find('select').eq(0).select('Disable');
  cy.wait('@getTimeZone');
});

Then('the configured host category is disabled', () => {
  cy.getIframeBody()
    .find('table.ListTable')
    .contains('tr', hostCategories.default.name)
    .should('contain.text', 'Disabled');
});

When('the user bulk enables the configured host category', () => {
  checkFirstHostCategoryFromListing();
  cy.getIframeBody().find('select').eq(0).select('Enable');
  cy.wait('@getTimeZone');
});

Then('the configured host category is enabled', () => {
  cy.getIframeBody()
    .find('table.ListTable')
    .contains('tr', hostCategories.default.name)
    .should('contain.text', 'Enabled');
});
