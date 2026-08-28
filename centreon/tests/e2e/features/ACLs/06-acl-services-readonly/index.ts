import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const roService = 'ro-service';
const roServiceGroup = 'ro-service-group';

const toggleUrl =
  '/centreon/include/configuration/configObject/service/ajaxServiceToggle.php';

const activationOf = (description: string): Cypress.Chainable<string> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: `SELECT service_activate FROM service WHERE service_description = '${description}' AND service_register = '1'`
    })
    .then(([rows]) => String(rows[0].service_activate));

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/services-readonly-user.json'
  );
});

beforeEach(() => {
  // loginByTypeOfUser({ loginViaApi: false }) waits on this alias internally.
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept('GET', '**/ajaxServiceGroupListing.php*').as('getServiceGroups');
  cy.intercept('GET', '**/ajaxServiceByHostListing.php*').as('getServices');
});

after(() => {
  cy.stopContainers();
});

Given('a read-only user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-readonly-for-services',
    loginViaApi: false
  });
});

When('the read-only user opens the service groups listing', () => {
  cy.visit(PAGES.configuration.servicesGroupsLegacy);
  cy.wait('@getServiceGroups');
});

When('the read-only user opens the services by host listing', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  cy.wait('@getServices').as('servicesListing');
});

Then('the seeded service group is listed', () => {
  // Named, so the spec cannot be satisfied by whatever else the platform holds.
  cy.getIframeBody().find('#clTableBody').should('contain', roServiceGroup);
});

Then('the seeded service is listed', () => {
  cy.getIframeBody().find('#clTableBody').should('contain', roService);
});

Then('the listing offers no add button and no bulk actions', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('.cl-btn-add').should('not.exist');
  cy.getIframeBody().find('.cl-more-actions-btn').should('not.exist');
});

Then('the row toggles are inert and carry no duplication field', () => {
  // clStripWriteControls() does two things: it removes the duplication field and
  // it strips onchange before disabling the checkbox. Asserting only on disabled
  // would leave the handler removal unguarded.
  // renderEmptyState() injects a real <tr>, so require a data row: the picker
  // cell is emitted for rendered records only.
  cy.getIframeBody()
    .find('#clTableBody tr .cl-col-picker')
    .should('have.length.at.least', 1);
  cy.getIframeBody().find('#clTableBody .cl-empty-state').should('not.exist');
  cy.getIframeBody()
    .find('#clTableBody input[type="checkbox"][data-row-id]')
    .each(($toggle) => {
      cy.wrap($toggle).should('be.disabled');
      cy.wrap($toggle).should('not.have.attr', 'onchange');
    });
  cy.getIframeBody().find('#clTableBody .cl-dup-input').should('not.exist');
});

Then('every row holds as many cells as the header holds columns', () => {
  // The options cell is emitted whenever renderOptions is set, read-only or not,
  // so a header hidden behind mode_access shifts every column after the picker.
  // Checked on every row, not just the first.
  cy.getIframeBody()
    .find('table.cl-listing-table thead tr th')
    .its('length')
    .then((headerCount) => {
      cy.getIframeBody()
        .find('#clTableBody tr')
        .each(($row) => {
          cy.wrap($row).find('td').should('have.length', headerCount);
        });
    });
});

When('the read-only user posts a toggle for the listed service', () => {
  // The UI hides the control; this checks the endpoint refuses it too, which is
  // the half of the feature title the rendering assertions cannot cover.
  // cy.get, not cy.wait: the alias was created with .as() on a cy.wait, so it
  // names a command result, not a route — cy.wait would reject it outright.
  cy.get('@servicesListing').then((interception: unknown) => {
    const token = String(
      (interception as { response?: { body?: { centreon_token?: string } } })
        .response?.body?.centreon_token
    );
    cy.requestOnDatabase({
      database: 'centreon',
      query: `SELECT service_id FROM service WHERE service_description = '${roService}' AND service_register = '1'`
    }).then(([rows]) => {
      cy.request({
        body: {
          action: 'u',
          centreon_token: token,
          id: Number(rows[0].service_id)
        },
        failOnStatusCode: false,
        form: true,
        method: 'POST',
        url: toggleUrl
      }).as('readOnlyToggle');
    });
  });
});

Then('the toggle endpoint refuses the write', () => {
  cy.get('@readOnlyToggle').its('status').should('eq', 403);
  // Discriminated: this is the menu-level write check, not a CSRF or id problem.
  cy.get('@readOnlyToggle')
    .its('body.error')
    .should('eq', 'Write access denied');
});

Then('the listed service is still enabled in the database', () => {
  activationOf(roService).should('eq', '1');
});
