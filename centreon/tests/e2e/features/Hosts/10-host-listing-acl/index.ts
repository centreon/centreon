import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { ActionClapi } from '../../../commons';
import { getListingRow, listingSelectors } from '../common';

const grantedHost = 'host-acl-granted';
const deniedHost = 'host-acl-denied';

const listingEndpoint =
  '/centreon/include/configuration/configObject/host/ajaxHostListing.php';
const toggleEndpoint =
  '/centreon/include/configuration/configObject/host/ajaxHostToggle.php';

/**
 * Read the activation flag straight from the database. An endpoint answering 404
 * proves nothing on its own — what matters is that no write happened, and only
 * the row can say that.
 */
const hostActivation = (name: string): Cypress.Chainable =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: `SELECT host_id, host_activate FROM host WHERE host_name = '${name}'`
    })
    .then(([rows]) => {
      if (rows.length === 0) {
        throw new Error(`Host ${name} not found`);
      }

      return cy.wrap(rows[0], { log: false });
    });

/**
 * A valid token, taken from a listing response the caller is entitled to. Using
 * a real one is the point: with an invalid token the toggle would be refused for
 * that reason and the ACL would never be reached.
 */
const freshCsrfToken = (): Cypress.Chainable =>
  cy
    .request({ method: 'GET', url: listingEndpoint })
    .then((response) => cy.wrap(response.body.centreon_token, { log: false }));

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
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.host_listing
  }).as('getHostListing');
});

afterEach(() => {
  cy.stopContainers();
});

Given(
  'two hosts exist and only one of them is granted to a non-admin user',
  () => {
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });

    [grantedHost, deniedHost].forEach((name) => {
      cy.addHost({
        hostGroup: 'Linux-Servers',
        name,
        template: 'generic-host'
      });
    });

    // The ACL resource grants the first host only. RELOAD rebuilds centreon_acl,
    // which is what every ACL-scoped query below actually reads — without it the
    // grants exist in the configuration and nowhere the endpoints can see them.
    cy.fixture('resources/clapi/config-ACL/hosts-acl-user.json').then(
      (actions: Array<ActionClapi>) => {
        actions.forEach((action) =>
          cy.executeActionViaClapi({ bodyContent: action })
        );
      }
    );

    cy.logout();
  }
);

Given('the non-admin user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'hosts-acl-user', loginViaApi: false });
});

When('the user opens the hosts listing', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
  cy.wait('@getHostListing');
  cy.waitForElementInIframe('#main-content', listingSelectors.table);
});

Then('only the granted host is listed', () => {
  getListingRow(grantedHost).should('have.length', 1);
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} tr td:nth-child(2)`)
    .should('not.contain', deniedHost);
});

When('the user posts a toggle for the host it was not granted', () => {
  hostActivation(deniedHost).then((host) => {
    cy.wrap(host.host_activate, { log: false }).as('activationBefore');

    freshCsrfToken().then((token) => {
      cy.request({
        body: { action: 'u', centreon_token: token, id: host.host_id },
        failOnStatusCode: false,
        form: true,
        method: 'POST',
        url: toggleEndpoint
      }).as('toggleResponse');
    });
  });
});

Then('the endpoint answers that the object was not found', () => {
  cy.get('@toggleResponse').then((response: unknown) => {
    const typedResponse = response as Cypress.Response<{ error: string }>;

    // The same answer an absent id gets, on purpose: telling the two apart would
    // let a caller enumerate the hosts it cannot see.
    expect(typedResponse.status).to.equal(404);
    expect(typedResponse.body.error).to.equal('Object not found');
  });
});

Then('the activation of that host is unchanged', () => {
  cy.get('@activationBefore').then((before) => {
    hostActivation(deniedHost).then((after) => {
      expect(String(after.host_activate)).to.equal(String(before));
    });
  });
});

When('the user posts a bulk disable for the host it was not granted', () => {
  hostActivation(deniedHost).then((host) => {
    cy.wrap(host.host_activate, { log: false }).as('activationBefore');

    // The legacy dispatcher, not the AJAX endpoint: it gates on the CSRF token
    // alone, so the host id it is handed has to be filtered against the caller's
    // ACL before the action runs.
    cy.visit(PAGES.configuration.hostsLegacy);
    cy.wait('@getTimeZone');
    cy.getIframeBody()
      .find('input[name="centreon_token"]')
      .invoke('val')
      .then((token) => {
        cy.request({
          body: {
            centreon_token: token,
            o1: 'mu',
            [`select[${host.host_id}]`]: '1'
          },
          failOnStatusCode: false,
          form: true,
          method: 'POST',
          url: '/centreon/main.php?p=60101'
        });
      });
  });
});
