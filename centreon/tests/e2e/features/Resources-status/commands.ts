/* eslint-disable @typescript-eslint/method-signature-style */
/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable newline-before-return */
/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add('deleteAllEventViewFilters', () => {
  const baseUrl = '/centreon/api/latest/users/filters/events-view';
  cy.request({
    method: 'GET',
    url: `${baseUrl}?page=1&limit=100`,
  }).then((response) => {
    expect(response.status).to.eq(200);

    const result = response.body.result;
    if (Array.isArray(result)) {
      const ids = result.map(item => item.id);

      // Perform DELETE requests for each ID
      ids.forEach(id => {
        cy.request({
          method: 'DELETE',
          url: `${baseUrl}/${id}`,
        }).then((deleteResponse) => {
          expect(deleteResponse.status).to.eq(204);
          cy.log(`Deleted ID: ${id}`);
        });
      });
    } else {
      cy.log('No IDs found in the result.');
    }
  });
});

Cypress.Commands.add('setPassiveResource', (url_string) => {
  const payload = {
    active_check_enabled: 0,
    passive_check_enabled: 1
  };
  cy.request({
    body: payload,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'PATCH',
    url: url_string
  }).then((response) => {
    expect(response.status).to.eq(204);
  });
});

Cypress.Commands.add(
  'waitForRequestCount',
  (alias, minCount = 2, maxRetries = 10, retryDelay = 5000) => {
    let requestCount = 0;
    let responseCount = 0;

    // Intercept the request
    cy.intercept(
      'GET',
      '/centreon/api/internal.php?object=centreon_topcounter&action=servicesStatus',
      (req) => {
        requestCount++;
        req.on('response', () => {
          responseCount++;
        });
      }
    ).as(alias);

    const checkRequestCondition = () => {
      return cy.get(`@${alias}.all`).then((interceptions) => {
        requestCount = interceptions.length;
        cy.log(`Request count: ${requestCount}`);
        cy.log(`Response count: ${responseCount}`);

        return cy.wrap(responseCount >= minCount);
      });
    };

    const retryUntilConditionMet = (retriesLeft) => {
      return checkRequestCondition().then((conditionMet) => {
        if (conditionMet) {
          return true;
        }
        if (retriesLeft > 0) {
          return cy
            .wait(retryDelay)
            .then(() => retryUntilConditionMet(retriesLeft - 1));
        }
        throw new Error(
          `Timed out after ${maxRetries * retryDelay}ms and ${maxRetries} retries`
        );
      });
    };

    return retryUntilConditionMet(maxRetries);
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      deleteAllEventViewFilters: () => Cypress.Chainable;
      setPassiveResource: (url: string) => Cypress.Chainable;
      waitForRequestCount(
        alias: string,
        minCount?: number,
        maxRetries?: number,
        retryDelay?: number
      ): Cypress.Chainable;
    }
  }
}

export {};