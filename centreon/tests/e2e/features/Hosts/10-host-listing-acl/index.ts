import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { ActionClapi } from '../../../commons';
import {
  confirmModalSelectors,
  getListingRow,
  listingSelectors
} from '../common';

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
  'two hosts exist and only one of them is granted to non-admin users',
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
        actions.forEach((action) => {
          cy.executeActionViaClapi({ bodyContent: action });
        });
      }
    );

    cy.logout();
  }
);

Given('the non-admin user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'hosts-acl-user', loginViaApi: false });
});

Given('the read-only user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'hosts-acl-ro-user', loginViaApi: false });
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

When('the user posts a bulk disable for both hosts', () => {
  hostActivation(grantedHost).then((granted) => {
    cy.wrap(granted.host_activate, { log: false }).as(
      'grantedActivationBefore'
    );
    hostActivation(deniedHost).then((denied) => {
      cy.wrap(denied.host_activate, { log: false }).as('activationBefore');

      // Include one granted host as a control proving that the dispatcher ran,
      // while the denied host proves that its selection was narrowed by the ACL.
      cy.visit(PAGES.configuration.hostsLegacy);
      cy.wait('@getTimeZone');
      cy.getIframeBody()
        .find('form[name="form"] input[name="centreon_token"]')
        .invoke('val')
        .then((token) => {
          cy.request({
            body: {
              centreon_token: token,
              o: 'mu',
              [`select[${granted.host_id}]`]: '1',
              [`select[${denied.host_id}]`]: '1'
            },
            failOnStatusCode: false,
            form: true,
            method: 'POST',
            url: '/centreon/main.get.php?p=60101'
          });
        });
    });
  });
});

Then('the granted host is disabled', () => {
  cy.get('@grantedActivationBefore').then((before) => {
    expect(String(before)).to.equal('1');
    hostActivation(grantedHost).then((after) => {
      expect(String(after.host_activate)).to.equal('0');
    });
  });
});

When('the user opens a mass change for both hosts', () => {
  hostActivation(grantedHost).then((granted) => {
    cy.wrap(granted.host_id, { log: false }).as('grantedHostId');
    hostActivation(deniedHost).then((denied) => {
      cy.visit(
        `${PAGES.configuration.hostsLegacy}&o=mc&select=${granted.host_id},${denied.host_id}`
      );
      cy.wait('@getTimeZone');
    });
  });
});

Then('only the granted host is carried into the mass change', () => {
  cy.get('@grantedHostId').then((grantedHostId) => {
    cy.getIframeBody()
      .find('input[name="select"]')
      .should('have.value', String(grantedHostId));
  });
});

/**
 * The row is rendered with its toggle and its onchange whatever the access level;
 * the read-only pass is what takes them away. Asserting the attribute as well as
 * the property keeps this on that pass rather than on the browser's own handling
 * of a disabled control.
 */
Then('the row toggle is disabled and carries no handler', () => {
  getListingRow(grantedHost)
    .find(listingSelectors.rowToggle)
    .should('be.disabled')
    .and('not.have.attr', 'onchange');
});

/**
 * Stripping those controls re-parses the whole options cell, so a parse that
 * dropped nodes would take this link with them — and a listing missing only its
 * options would still pass every other assertion here.
 */
Then('the row still links to the services of that host', () => {
  getListingRow(grantedHost)
    .find(`${listingSelectors.optionsCell} a`)
    .should('have.length', 1)
    .and('have.attr', 'href')
    .and('include', encodeURIComponent(grantedHost));
});

/**
 * The nominal path, which no scenario covered: every other bulk test posts the
 * form by hand. Here the operation reaches $o the way the page sets it — the
 * menu calls setO(), which writes the hidden input the dispatcher reads. The
 * o1/o2 fallback that used to carry it was removed, so nothing else would catch
 * a break in that wiring.
 */
When('the user disables the granted host through the actions menu', () => {
  hostActivation(grantedHost).then((host) => {
    cy.wrap(String(host.host_activate), { log: false }).as('activationBefore');
  });

  getListingRow(grantedHost)
    .find(listingSelectors.rowCheckbox)
    .check({ force: true });
  cy.getIframeBody().find(listingSelectors.moreActionsButton).click();
  cy.getIframeBody()
    .contains(listingSelectors.moreActionsItem, 'Disable')
    .click();
  cy.getIframeBody().find(confirmModalSelectors.confirm).click();
  cy.wait('@getHostListing');
});

Then('the granted host is disabled in the database', () => {
  cy.get('@activationBefore').then((before) => {
    expect(String(before), 'the host was enabled to begin with').to.equal('1');
  });
  hostActivation(grantedHost).then((host) => {
    expect(String(host.host_activate)).to.equal('0');
  });
});
