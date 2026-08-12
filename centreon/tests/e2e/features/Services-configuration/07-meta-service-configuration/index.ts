import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import data from '../../../fixtures/services/meta_service.json';

// Selection filters of the legacy service-configuration list endpoint:
// 'all' -> services and meta services, 's' -> services only, 'm' -> meta services only.
const serviceSelectionFilters = ['all', 's', 'm'];

const configurationServicesListUrl = (selection: string): string =>
  `/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list&page_limit=60&page=1&s=${selection}`;

const metaServiceLabel = `Meta - ${data.default.name}`;
const secondMetaServiceName = 'metaServiceSecond';

const serviceListByFilter: Record<
  string,
  Array<{ id: string; text: string }>
> = {};

const isMetaServiceItem = (item: { text: string }): boolean =>
  item.text.startsWith('Meta - ');

// Assert the items returned for a given selection filter contain the expected
// mix of regular services and meta services. metaServiceLabel is the anchor.
const assertServiceListForFilter = (
  items: Array<{ id: string; text: string }>,
  selection: string
): void => {
  expect(items, `items returned for s=${selection}`).to.be.an('array');
  const containsMetaService = items.some(
    (item) => item.text === metaServiceLabel
  );

  if (selection === 'all') {
    expect(
      containsMetaService,
      `meta service "${metaServiceLabel}" should be listed when s=all`
    ).to.be.true;
  } else if (selection === 'm') {
    expect(
      containsMetaService,
      `meta service "${metaServiceLabel}" should be listed when s=m`
    ).to.be.true;
    expect(
      items.every(isMetaServiceItem),
      'only meta services should be listed when s=m'
    ).to.be.true;
  } else {
    expect(
      containsMetaService,
      `meta service "${metaServiceLabel}" should not be listed when s=s`
    ).to.be.false;
  }
};

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.ajax.meta_service_toggle
  }).as('toggleMeta');
});

afterEach(() => {
  cy.stopContainers();
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Then('a meta service is configured', () => {
  cy.openMetaServicesListing();
  cy.getIframeBody().find('.cl-btn-add').click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_name"]', { timeout: 20_000 })
    .should('be.visible')
    .type(data.default.name);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_display"]')
    .type(data.default.output_format);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="warning"]')
    .type(data.default.warning_level);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="critical"]')
    .type(data.default.critical_level);
  cy.getMetaServiceSidePanelBody()
    .find('select[name="calcul_type"]')
    .select(data.default.calculation_type);
  cy.getMetaServiceSidePanelBody()
    .find('select[name="data_source_type"]')
    .select(data.default.data_source_type);
  cy.getMetaServiceSidePanelBody()
    .find(
      `input[name*="meta_select_mode"][value=${data.default.selection_mode}]`
    )
    .parent()
    .click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="regexp_str"]')
    .type(data.default.sql_like_clause_expression);
  cy.getMetaServiceSidePanelBody()
    .find('span[aria-labelledby="select2-check_period-container"]')
    .click();
  cy.getMetaServiceSidePanelBody()
    .find(`div[title=${data.default.check_period}]`)
    .click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="max_check_attempts"]')
    .type(data.default.max_check_attempts);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="normal_check_interval"]')
    .type(data.default.normal_check_interval);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="retry_check_interval"]')
    .type(data.default.retry_check_interval);
  cy.getMetaServiceSidePanelBody()
    .find(
      `input[name*="notifications_enabled"][value=${data.default.notification_enabled}]`
    )
    .parent()
    .click();
  cy.getMetaServiceSidePanelBody()
    .find('input[placeholder="Implied Contacts"]')
    .click();
  cy.getMetaServiceSidePanelBody().contains(data.default.contacts).click();
  cy.getMetaServiceSidePanelBody()
    .find('input[placeholder="Linked Contact Groups"]')
    .click();
  cy.getMetaServiceSidePanelBody()
    .contains(data.default.contact_groups)
    .click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="notification_interval"]')
    .type(data.default.notification_interval);
  cy.getMetaServiceSidePanelBody()
    .find('span#select2-notification_period-container')
    .click();
  cy.getMetaServiceSidePanelBody()
    .contains(data.default.notification_period)
    .click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="geo_coords"]')
    .type(data.default.geo_coordinates);
  cy.getMetaServiceSidePanelBody()
    .find('select[name="graph_id"]')
    .select(data.default.graph_template);
  cy.getMetaServiceSidePanelBody()
    .find('textarea[name="meta_comment"]')
    .type('metaServiceComments');
  cy.getMetaServiceSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
});

Given('a second meta service exists', () => {
  cy.openMetaServicesListing();
  cy.addMetaService({ maxCheckAttempts: '3', name: secondMetaServiceName });
});

When('the user opens the meta services listing', () => {
  cy.openMetaServicesListing();
});

Then(
  'the AJAX listing table is displayed with the configured meta service',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(data.default.name)
      .should('exist');
  }
);

When('the user searches for the first meta service', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type(data.default.name);
  cy.getIframeBody().find('#clTableBody td').should('not.contain', 'Loading');
});

Then('only the matching meta service is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(data.default.name)
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(secondMetaServiceName)
    .should('not.exist');
});

When('the user changes the properties of a meta service', () => {
  cy.openMetaServiceForm(data.default.name);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_name"]')
    .clear()
    .type('metaServiceNameChanged');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_display"]')
    .clear()
    .type('metaServiceOutputFormatChanged');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="warning"]')
    .clear()
    .type('50');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="critical"]')
    .clear()
    .type('75');
  cy.getMetaServiceSidePanelBody()
    .find('select[name="calcul_type"]')
    .select('Max');
  cy.getMetaServiceSidePanelBody()
    .find('select[name="data_source_type"]')
    .select('COUNTER');
  cy.getMetaServiceSidePanelBody()
    .find('input[name*="meta_select_mode"][value="2"]')
    .parent()
    .click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="regexp_str"]')
    .clear()
    .type('metaServiceExpressionChanged');
  cy.getMetaServiceSidePanelBody()
    .find('span[aria-labelledby="select2-check_period-container"]')
    .click();
  cy.getMetaServiceSidePanelBody().contains('nonworkhours').click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="max_check_attempts"]')
    .clear()
    .type('5');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="normal_check_interval"]')
    .clear()
    .type('10');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="retry_check_interval"]')
    .clear()
    .type('20');
  cy.getMetaServiceSidePanelBody()
    .find('input[name*="notifications_enabled"][value="2"]')
    .parent()
    .click();
  cy.getMetaServiceSidePanelBody()
    .find(`li[title=${data.default.contact_groups}]`)
    .find('span[class*="choice__remove"]')
    .click();
  cy.getMetaServiceSidePanelBody().contains('Supervisors').click();
  cy.getMetaServiceSidePanelBody()
    .find(`li[title=${data.default.contacts}]`)
    .find('span[class*="choice__remove"]')
    .click();
  cy.getMetaServiceSidePanelBody()
    .find(`div[title=${data.default.contact_groups}]`)
    .click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="notification_interval"]')
    .clear()
    .type('12');
  cy.getMetaServiceSidePanelBody()
    .find('span#select2-notification_period-container')
    .click();
  cy.getMetaServiceSidePanelBody().contains('24x7').click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="geo_coords"]')
    .clear()
    .type(data.default.geo_coordinates);
  cy.getMetaServiceSidePanelBody()
    .find('select[name="graph_id"]')
    .select('Memory');
  cy.getMetaServiceSidePanelBody()
    .find('textarea[name="meta_comment"]')
    .clear()
    .type('metaServiceCommentsChanged');
  cy.getMetaServiceSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
});

Then('the properties are updated', () => {
  cy.openMetaServiceForm('metaServiceNameChanged');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_name"]')
    .should('have.value', 'metaServiceNameChanged');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_display"]')
    .should('have.value', 'metaServiceOutputFormatChanged');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="warning"]')
    .should('have.value', '50');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="critical"]')
    .should('have.value', '75');
  cy.getMetaServiceSidePanelBody()
    .find('select[name="calcul_type"]')
    .find('option:selected')
    .should('have.value', 'MAX');
  cy.getMetaServiceSidePanelBody()
    .find('select[name="data_source_type"]')
    .find('option:selected')
    .should('have.value', '1');
  cy.getMetaServiceSidePanelBody()
    .find('input[name*="meta_select_mode"][value="2"]')
    .should('be.checked');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="regexp_str"]')
    .should('have.value', 'metaServiceExpressionChanged');
  cy.getMetaServiceSidePanelBody()
    .find('span[aria-labelledby="select2-check_period-container"]')
    .contains('nonworkhours')
    .should('be.visible');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="max_check_attempts"]')
    .should('have.value', '5');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="normal_check_interval"]')
    .should('have.value', '10');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="retry_check_interval"]')
    .should('have.value', '20');
  cy.getMetaServiceSidePanelBody()
    .find('input[name*="notifications_enabled"][value="2"]')
    .should('be.checked');
  cy.getMetaServiceSidePanelBody()
    .find('li[title=Guest]')
    .contains('Guest')
    .should('exist');
  cy.getMetaServiceSidePanelBody()
    .find('li[title="Supervisors"]')
    .contains('Supervisors')
    .should('exist');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="notification_interval"]')
    .should('have.value', '12');
  cy.getMetaServiceSidePanelBody()
    .find('span#select2-notification_period-container')
    .contains('24x7')
    .should('be.visible');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="geo_coords"]')
    .should('have.value', data.default.geoCoordinatesTruncated);
  cy.getMetaServiceSidePanelBody()
    .find('select[name="graph_id"]')
    .find('option:selected')
    .should('have.value', '4');
  cy.getMetaServiceSidePanelBody()
    .find('textarea[name="meta_comment"]')
    .should('have.value', 'metaServiceCommentsChanged');
});

When('the user toggles the meta service off from the listing', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(data.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click({ force: true });
  cy.wait('@toggleMeta');
});

Then('the toggle request succeeds and the meta service is disabled', () => {
  cy.get('@toggleMeta').its('response.statusCode').should('eq', 200);
  cy.get('@toggleMeta')
    .its('response.body')
    .should('have.property', 'success', true);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(data.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

When('the user toggles the meta service on from the listing', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(data.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click({ force: true });
  cy.wait('@toggleMeta').its('response.statusCode').should('eq', 200);
});

Then('the meta service is enabled again', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(data.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

When('the user duplicates a meta service', () => {
  cy.openMetaServicesListing();
  cy.selectMetaServiceRowAndRunBulkAction(data.default.name, 'Duplicate');
  cy.wait('@getTimeZone');
});

Then('the new meta service has the same properties', () => {
  cy.reload();
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.openMetaServiceForm('metaServiceName_1');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_name"]')
    .should('have.value', 'metaServiceName_1');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_display"]')
    .should('have.value', data.default.output_format);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="warning"]')
    .should('have.value', data.default.warning_level);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="critical"]')
    .should('have.value', data.default.critical_level);
  cy.getMetaServiceSidePanelBody()
    .find('select[name="calcul_type"]')
    .find('option:selected')
    .should('have.value', 'SOM');
  cy.getMetaServiceSidePanelBody()
    .find('select[name="data_source_type"]')
    .find('option:selected')
    .should('have.value', '3');
  cy.getMetaServiceSidePanelBody()
    .find('input[name*="meta_select_mode"][value="2"]')
    .should('be.checked');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="regexp_str"]')
    .should('have.value', data.default.sql_like_clause_expression);
  cy.getMetaServiceSidePanelBody()
    .find('span[aria-labelledby="select2-check_period-container"]')
    .contains(data.default.check_period)
    .should('be.visible');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="max_check_attempts"]')
    .should('have.value', data.default.max_check_attempts);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="normal_check_interval"]')
    .should('have.value', data.default.normal_check_interval);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="retry_check_interval"]')
    .should('have.value', data.default.retry_check_interval);
  cy.getMetaServiceSidePanelBody()
    .find('input[name*="notifications_enabled"][value="1"]')
    .should('be.checked');
  cy.getMetaServiceSidePanelBody()
    .find(`li[title=${data.default.contacts}]`)
    .contains(data.default.contacts)
    .should('exist');
  cy.getMetaServiceSidePanelBody()
    .find(`li[title=${data.default.contact_groups}]`)
    .contains(data.default.contact_groups)
    .should('exist');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="notification_interval"]')
    .should('have.value', data.default.notification_interval);
  cy.getMetaServiceSidePanelBody()
    .find('span#select2-notification_period-container')
    .contains(data.default.notification_period)
    .should('be.visible');
  cy.getMetaServiceSidePanelBody()
    .find('input[name="geo_coords"]')
    .should('have.value', data.default.geoCoordinatesTruncated);
  cy.getMetaServiceSidePanelBody()
    .find('select[name="graph_id"]')
    .find('option:selected')
    .should('have.value', '2');
  cy.getMetaServiceSidePanelBody()
    .find('textarea[name="meta_comment"]')
    .should('have.value', data.default.comments);
});

When('the user deletes a meta service', () => {
  cy.openMetaServicesListing();
  cy.selectMetaServiceRowAndRunBulkAction(data.default.name, 'Delete');
  cy.wait('@getTimeZone');
});

Then('the deleted meta service is not displayed in the list', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(data.default.name)
    .should('not.exist');
});

Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When(
  'the user opens the meta service form and comes back to the listing',
  () => {
    cy.openMetaServiceForm(data.default.name);
    cy.openMetaServicesListing();
  }
);

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', data.default.name);
});

When(
  'the configuration services list is requested for each selection filter',
  () => {
    serviceSelectionFilters.forEach((selection) => {
      cy.request(configurationServicesListUrl(selection)).then((response) => {
        expect(response.status).to.eq(200);
        const body =
          typeof response.body === 'string'
            ? JSON.parse(response.body)
            : response.body;
        serviceListByFilter[selection] = body.items;
      });
    });
  }
);

Then(
  'services and meta services are returned according to the selected filter',
  () => {
    serviceSelectionFilters.forEach((selection) => {
      assertServiceListForFilter(serviceListByFilter[selection], selection);
    });
  }
);
