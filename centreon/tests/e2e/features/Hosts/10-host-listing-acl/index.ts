import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { ActionClapi } from '../../../commons';

const grantedHost = 'host-acl-granted';
const deniedHost = 'host-acl-denied';

// The listing page (inside the iframe) both renders the rows and dispatches the
// bulk actions the legacy form posts back to it.
const listingPageUrl = '/centreon/main.get.php?p=60101';

/**
 * Read the activation flag straight from the database. A quiet page proves
 * nothing on its own — what matters is that no write happened, and only the row
 * can say that.
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
 * A valid token, scraped from the listing form the caller is entitled to see.
 * Using a real one is the point: with an invalid token the action would be
 * refused for that reason and the ACL filter would never be reached.
 */
const legacyCsrfToken = (): Cypress.Chainable => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');

  return cy
    .getIframeBody()
    .find('form[name="form"] input[name="centreon_token"]')
    .invoke('val');
};

const postBulkDisable = (hostIds: Array<number>): void => {
  legacyCsrfToken().then((token) => {
    const body: Record<string, string> = {
      centreon_token: String(token),
      o: 'mu'
    };
    hostIds.forEach((id) => {
      body[`select[${id}]`] = '1';
    });
    cy.request({
      body,
      failOnStatusCode: false,
      form: true,
      method: 'POST',
      url: listingPageUrl
    });
  });
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
    // grants exist in the configuration and nowhere the pages can see them.
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
  cy.waitForElementInIframe('#main-content', `a:contains("${grantedHost}")`);
});

Then('only the granted host is listed', () => {
  cy.getIframeBody().find(`a:contains("${grantedHost}")`).should('exist');
  cy.getIframeBody().find(`a:contains("${deniedHost}")`).should('not.exist');
});

When('the user posts a disable for the host it was not granted', () => {
  hostActivation(deniedHost).then((host) => {
    cy.wrap(host.host_activate, { log: false }).as('activationBefore');

    legacyCsrfToken().then((token) => {
      cy.request({
        body: {
          centreon_token: String(token),
          host_id: String(host.host_id),
          o: 'u'
        },
        failOnStatusCode: false,
        form: true,
        method: 'POST',
        url: listingPageUrl
      });
    });
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
      postBulkDisable([granted.host_id, denied.host_id]);
    });
  });
});

Then('the granted host is disabled', () => {
  cy.get('@grantedActivationBefore').then((before) => {
    expect(String(before), 'the host was enabled to begin with').to.equal('1');
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

When('the user posts a bulk disable for the granted host', () => {
  hostActivation(grantedHost).then((granted) => {
    postBulkDisable([granted.host_id]);
  });
});

Then('the granted host is still enabled', () => {
  hostActivation(grantedHost).then((after) => {
    expect(String(after.host_activate)).to.equal('1');
  });
});
