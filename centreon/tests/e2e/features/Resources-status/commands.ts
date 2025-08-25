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
  'waitForElementToBeVisible',
  (selector, timeout = 50000, interval = 2000) => {
    cy.waitUntil(
      () =>
        cy.get('body').then(($body) => {
          const element = $body.find(selector);

          return element.length > 0 && element.is(':visible');
        }),
      {
        errorMsg: `The element '${selector}' is not visible`,
        interval,
        timeout
      }
    ).then((isVisible) => {
      if (!isVisible) {
        throw new Error(`The element '${selector}' is not visible`);
      }
    });
  }
);

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

Cypress.Commands.add(
  'waitForElementInIframeToDisappear',
  (iframeSelector, elementSelector) => {
    cy.waitUntil(
      () =>
        cy.get(iframeSelector).then(($iframe) => {
          const iframeBody = ($iframe[0] as HTMLIFrameElement).contentDocument
            ?.body;
          if (iframeBody) {
            const $element = Cypress.$(iframeBody).find(elementSelector);
            const isGone = $element.length === 0 || !$element.is(':visible');
            if (!isGone) {
              cy.exportConfig();
              cy.reload();
              cy.waitForElementInIframe(
                '#main-content',
                'a:contains("Add a downtime")'
              );
            }

            return isGone;
          }

          return false;
        }),
      {
        errorMsg: 'The element is still visible within the iframe',
        interval: 7000,
        timeout: 100000
      }
    ).then((isGone) => {
      if (!isGone) {
        throw new Error('The element is still visible');
      }
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      deleteAllEventViewFilters: () => Cypress.Chainable;
      setPassiveResource: (url: string) => Cypress.Chainable;
      waitForElementToBeVisible(
        selector: string,
        timeout?: number,
        interval?: number
      ): Cypress.Chainable;
      waitForRequestCount(
        alias: string,
        minCount?: number,
        maxRetries?: number,
        retryDelay?: number
      ): Cypress.Chainable;
       waitForElementInIframeToDisappear: (
        iframeSelector: string,
        elementSelector: string
      ) => Cypress.Chainable;
    }
  }
}

export {};