import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { ActionClapi } from '../../../commons';

const grantedCategory = 'hc-acl-granted';
const deniedCategory = 'hc-acl-denied';

// The legacy host categories page both renders the rows and dispatches the
// enable/disable/delete/duplicate actions the form posts back to it.
const hostCategoriesDispatchUrl = '/centreon/main.get.php?p=60104';

/**
 * Read the activation flag straight from the database. A quiet page proves
 * nothing on its own — what matters is that no write happened, and only the row
 * can say that.
 */
const categoryActivation = (name: string): Cypress.Chainable =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: `SELECT hc_id, hc_activate FROM hostcategories WHERE hc_name = '${name}'`
    })
    .then(([rows]) => {
      if (rows.length === 0) {
        throw new Error(`Host category ${name} not found`);
      }

      return cy.wrap(rows[0], { log: false });
    });

/**
 * A valid single-use CSRF token, scraped from the listing form the caller is
 * entitled to see. Using a real one is the point: with an invalid token the
 * action would be refused for that reason and the ACL filter would never be
 * reached. Re-scrape before every forged request — the legacy dispatcher
 * rotates the token on use.
 */
const legacyHostCategoryToken = (): Cypress.Chainable => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');

  return cy
    .getIframeBody()
    .find('form[name="form"] input[name="centreon_token"]')
    .invoke('val');
};

const postSingle = (action: string, hcId: number): void => {
  legacyHostCategoryToken().then((token) => {
    cy.request({
      body: { centreon_token: String(token), hc_id: String(hcId), o: action },
      failOnStatusCode: false,
      form: true,
      method: 'POST',
      url: hostCategoriesDispatchUrl
    });
  });
};

const postBulk = (action: string, hcIds: Array<number>): void => {
  legacyHostCategoryToken().then((token) => {
    const body: Record<string, string> = {
      centreon_token: String(token),
      o: action
    };
    hcIds.forEach((id) => {
      body[`select[${id}]`] = '1';
    });
    cy.request({
      body,
      failOnStatusCode: false,
      form: true,
      method: 'POST',
      url: hostCategoriesDispatchUrl
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
  'two host categories exist and only one is granted to the non-admin user',
  () => {
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
    cy.setUserTokenApiV1();

    // The categories, the two users and the ACL resource whose host-category
    // filter grants only the "granted" category (RELOAD rebuilds centreon_acl,
    // which every ACL-scoped query below actually reads).
    cy.fixture(
      'resources/clapi/config-ACL/host-categories-acl-scope.json'
    ).then((actions: Array<ActionClapi>) => {
      actions.forEach((action) => {
        cy.executeActionViaClapi({ bodyContent: action });
      });
    });

    cy.logout();
  }
);

Given('the non-admin host-category user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'hostcat-acl-user', loginViaApi: false });
});

Given('the read-only host-category user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'hostcat-acl-ro-user', loginViaApi: false });
});

When('the user opens the host categories listing', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.ListTable');
});

Then('only the granted host category is listed', () => {
  cy.getIframeBody().find(`a:contains("${grantedCategory}")`).should('exist');
  cy.getIframeBody()
    .find(`a:contains("${deniedCategory}")`)
    .should('not.exist');
});

When('the user posts a disable for the category it was not granted', () => {
  categoryActivation(deniedCategory).then((denied) => {
    cy.wrap(denied.hc_activate, { log: false }).as('activationBefore');
    postSingle('u', denied.hc_id);
  });
});

Then('the activation of that category is unchanged', () => {
  cy.get('@activationBefore').then((before) => {
    categoryActivation(deniedCategory).then((after) => {
      expect(String(after.hc_activate)).to.equal(String(before));
    });
  });
});

When('the user posts a disable for the granted category', () => {
  categoryActivation(grantedCategory).then((granted) => {
    cy.wrap(granted.hc_activate, { log: false }).as('grantedActivationBefore');
    postSingle('u', granted.hc_id);
  });
});

Then('the granted category is disabled', () => {
  cy.get('@grantedActivationBefore').then((before) => {
    expect(String(before), 'the category was enabled to begin with').to.equal(
      '1'
    );
    categoryActivation(grantedCategory).then((after) => {
      expect(String(after.hc_activate)).to.equal('0');
    });
  });
});

When('the user posts a bulk disable for both categories', () => {
  categoryActivation(grantedCategory).then((granted) => {
    categoryActivation(deniedCategory).then((denied) => {
      cy.wrap(denied.hc_activate, { log: false }).as('deniedActivationBefore');
      // One granted id proves the dispatcher ran; the denied id proves its
      // selection was narrowed away by the ACL filter.
      postBulk('mu', [granted.hc_id, denied.hc_id]);
    });
  });
});

Then('only the granted category is disabled', () => {
  categoryActivation(grantedCategory).then((granted) => {
    expect(String(granted.hc_activate)).to.equal('0');
  });
  cy.get('@deniedActivationBefore').then((before) => {
    categoryActivation(deniedCategory).then((denied) => {
      expect(String(denied.hc_activate)).to.equal(String(before));
    });
  });
});

When('the user posts a delete for the category it was not granted', () => {
  categoryActivation(deniedCategory).then((denied) => {
    postBulk('d', [denied.hc_id]);
  });
});

Then('the denied category still exists', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `SELECT hc_id FROM hostcategories WHERE hc_name = '${deniedCategory}'`
  }).then(([rows]) => {
    expect(rows.length, 'the out-of-scope category must not be deleted').to.eq(
      1
    );
  });
});

When('the user opens the granted category in view mode', () => {
  categoryActivation(grantedCategory).then((granted) => {
    cy.visit(
      `${PAGES.configuration.hostCategoriesLegacy}&o=w&hc_id=${granted.hc_id}`
    );
    cy.wait('@getTimeZone');
  });
});

Then('the view form renders and the severity fields are collapsed', () => {
  // The read-only form freezes the icon field, so #icon_id is absent. If the
  // inline script dereferenced it unguarded it would throw before
  // toggleSeverityFields() runs, leaving the severity rows visible. Their being
  // collapsed proves the icon guard let the script run to completion.
  cy.waitForElementInIframe('#main-content', 'table.formTable');
  cy.getIframeBody()
    .find('#severity_level')
    .should('exist')
    .and('not.be.visible');
  cy.getIframeBody()
    .find('#severity_icon')
    .should('exist')
    .and('not.be.visible');
});
