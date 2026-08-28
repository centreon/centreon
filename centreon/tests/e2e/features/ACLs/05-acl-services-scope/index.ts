import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const grantedService = 'acl-service-granted';
const deniedService = 'acl-service-denied';
const aclCategory = 'acl-service-category';

const toggleUrl =
  '/centreon/include/configuration/configObject/service/ajaxServiceToggle.php';

const serviceIdOf = (description: string): Cypress.Chainable<number> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: `SELECT service_id FROM service WHERE service_description = '${description}' AND service_register = '1'`
    })
    .then(([rows]) => {
      if (rows.length === 0) {
        throw new Error(`Service ${description} not found`);
      }

      return Number(rows[0].service_id);
    });

const activationOf = (description: string): Cypress.Chainable<string> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: `SELECT service_activate FROM service WHERE service_description = '${description}' AND service_register = '1'`
    })
    .then(([rows]) => String(rows[0].service_activate));

// The toggle endpoint takes the token the listing handed out, so every direct
// POST below has to read it from the listing response rather than invent one.
const openListingAndReadToken = (): Cypress.Chainable<string> => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);

  return cy
    .wait('@getServices')
    .then((interception) =>
      String(interception.response?.body?.centreon_token)
    );
};

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/services-acl-user.json'
  );
});

beforeEach(() => {
  // loginByTypeOfUser({ loginViaApi: false }) waits on this alias internally, so
  // it has to be registered before the Background logs in.
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept('GET', '**/ajaxServiceByHostListing.php*').as('getServices');
  cy.intercept('GET', '**/ajaxServiceCategoriesListing.php*').as(
    'getCategories'
  );
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user whose ACL grants a single host', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-non-admin-for-services-acl',
    loginViaApi: false
  });
});

When('the non-admin user opens the services by host listing', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  cy.wait('@getServices');
});

Then('only the service of the granted host is listed', () => {
  cy.getIframeBody().find('#clTableBody').should('contain', grantedService);
  cy.getIframeBody().find('#clTableBody').should('not.contain', deniedService);
});

When(
  'the non-admin user posts a toggle for the service of the denied host',
  () => {
    openListingAndReadToken().then((token) => {
      serviceIdOf(deniedService).then((serviceId) => {
        cy.request({
          body: { action: 'u', centreon_token: token, id: serviceId },
          failOnStatusCode: false,
          form: true,
          method: 'POST',
          url: toggleUrl
        }).as('deniedToggle');
      });
    });
  }
);

Then('the toggle endpoint answers 403', () => {
  cy.get('@deniedToggle').its('status').should('eq', 403);
  // Discriminated: the endpoint answers 403 on five distinct paths. This one has
  // to be the resource check, not a menu-ACL or CSRF refusal, which would make
  // the scenario pass for the wrong reason.
  cy.get('@deniedToggle').its('body.error').should('eq', 'Access denied');
});

Then('the denied service is still enabled in the database', () => {
  activationOf(deniedService).should('eq', '1');
});

Given('the non-admin user has been refused a toggle outside its scope', () => {
  openListingAndReadToken().then((token) => {
    cy.wrap(token).as('listingToken');
    serviceIdOf(deniedService).then((serviceId) => {
      cy.request({
        body: { action: 'u', centreon_token: token, id: serviceId },
        failOnStatusCode: false,
        form: true,
        method: 'POST',
        url: toggleUrl
      })
        .its('status')
        .should('eq', 403);
    });
  });
});

When('the non-admin user toggles the service of the granted host', () => {
  // Same token as the refused call: the endpoint validates CSRF only after the
  // ACL checks, so a rejected request must leave it usable. Reusing it here is
  // the assertion — a 403 that consumed the token would make this one fail too.
  cy.get('@listingToken').then((token) => {
    serviceIdOf(grantedService).then((serviceId) => {
      cy.request({
        body: { action: 'u', centreon_token: String(token), id: serviceId },
        form: true,
        method: 'POST',
        url: toggleUrl
      })
        .its('status')
        .should('eq', 200);
    });
  });
});

Then('the granted service is disabled in the database', () => {
  activationOf(grantedService).should('eq', '0');
  // Restore it: these scenarios share one platform, so a later one must not
  // inherit a disabled service.
  cy.requestOnDatabase({
    database: 'centreon',
    query: `UPDATE service SET service_activate = '1' WHERE service_description = '${grantedService}' AND service_register = '1'`
  });
});

When('the non-admin user posts a toggle with an invalid CSRF token', () => {
  serviceIdOf(grantedService).then((serviceId) => {
    cy.request({
      body: { action: 'u', centreon_token: 'not-a-valid-token', id: serviceId },
      failOnStatusCode: false,
      form: true,
      method: 'POST',
      url: toggleUrl
    }).as('badTokenToggle');
  });
});

Then('the toggle endpoint rejects the invalid token', () => {
  // Without this, a scenario proving the token still works after a refusal would
  // stay green even if the token stopped being required at all.
  cy.get('@badTokenToggle').its('status').should('eq', 403);
  cy.get('@badTokenToggle')
    .its('body.error')
    .should('eq', 'Invalid CSRF token');
  activationOf(grantedService).should('eq', '1');
});

When('the non-admin user opens the service categories listing', () => {
  cy.visit(PAGES.configuration.servicesCategoriesLegacy);
  cy.wait('@getCategories').as('categoriesListing');
});

Then('the service categories listing is not empty', () => {
  // The ACL grants a host but no service category, which leaves
  // getServiceCategoriesString() answering "''". That means "no category
  // restriction", not "nothing granted": scoping on it would empty the page.
  cy.get('@categoriesListing')
    .its('response.body.total')
    .should('be.greaterThan', 0);
  cy.getIframeBody().find('#clTableBody').should('contain', aclCategory);
});
