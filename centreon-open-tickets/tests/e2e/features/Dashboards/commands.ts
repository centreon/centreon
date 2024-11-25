/* eslint-disable @typescript-eslint/no-namespace */
Cypress.Commands.add(
  'waitUntilForDashboardRoles',
  (accessRightsTestId, expectedElementCount) => {
    const openModalAndCheck: () => Cypress.Chainable<boolean> = () => {
      cy.getByTestId({ testId: accessRightsTestId }).invoke('show').click();
      cy.getByTestId({ testId: 'ArrowDropDownIcon' })
        .eq(1)
        .should('be.visible');

      return cy
        .get('[data-testid="ArrowDropDownIcon"]')
        .should('be.visible')
        .then(($element) => {
          cy.getByLabel({ label: 'close', tag: 'button' }).click();

          return cy.wrap($element.length === expectedElementCount);
        });
    };

    return cy.waitUntil(() => openModalAndCheck(), {
      errorMsg: 'The element does not exist',
      interval: 3000,
      timeout: 30000
    });
  }
);

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

Cypress.Commands.add('grantClipboardPermissions', () => {
  Cypress.automation('remote:debugger:protocol', {
    command: 'Browser.grantPermissions',
    params: {
      permissions: ['clipboardReadWrite', 'clipboardSanitizedWrite'],
      origin: window.location.origin,
    },
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      waitUntilForDashboardRoles: (
        accessRightsTestId: string,
        expectedElementCount: number
      ) => Cypress.Chainable;
      visitDashboards: () => Cypress.Chainable;
      grantClipboardPermissions: () => Cypress.Chainable;
    }
  }
}

export {};
