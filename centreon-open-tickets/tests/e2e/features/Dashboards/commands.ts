/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add('visitDashboards', () => {
  cy.intercept({
    method: 'GET',
    times: 1,
    url: '/centreon/api/latest/configuration/dashboards*'
  }).as('listAllDashboards');

  const dashboardsUrl = '/centreon/home/dashboards/library';
  cy.url().then((url) =>
    url.includes(dashboardsUrl)
      ? cy.visit(dashboardsUrl)
      : cy.navigateTo({ page: 'Dashboards', rootItemNumber: 0 })
  );

  cy.wait('@listAllDashboards');
});

Cypress.Commands.add('visitDashboard', (name) => {
  cy.visitDashboards();
  cy.contains(name).click();

  cy.url().should('match', /\/home\/dashboards\/library\/\d+$/);
});

Cypress.Commands.add(
  'waitForElementInIframe',
  (iframeSelector, elementSelector) => {
    cy.waitUntil(
      () =>
        cy.get(iframeSelector).then(($iframe) => {
          const iframeBody = $iframe[0].contentDocument.body;
          if (iframeBody) {
            const $element = Cypress.$(iframeBody).find(elementSelector);

            return $element.length > 0 && $element.is(':visible');
          }

          return false;
        }),
      {
        errorMsg: 'The element is not visible within the iframe',
        interval: 5000,
        timeout: 100000
      }
    ).then((isVisible) => {
      if (!isVisible) {
        throw new Error('The element is not visible');
      }
    });
  }
);

Cypress.Commands.add('enterIframe', (iframeSelector) => {
  cy.get(iframeSelector)
    .its('0.contentDocument')
    .should('exist')
    .its('body')
    .should('not.be.undefined')
    .then(cy.wrap);
});


declare global {
  namespace Cypress {
    interface Chainable {
      waitUntilForDashboardRoles: (
        accessRightsTestId: string,
        expectedElementCount: number
      ) => Cypress.Chainable;
      visitDashboards: () => Cypress.Chainable;
      visitDashboard: (name: string) => Cypress.Chainable;
      waitForElementInIframe: (
        iframeSelector: string,
        elementSelector: string
      ) => Cypress.Chainable;
      enterIframe: (iframeSelector: string) => Cypress.Chainable;
    }
  }
}

export {};
