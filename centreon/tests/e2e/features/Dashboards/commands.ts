/* eslint-disable @typescript-eslint/no-namespace */
import singleMetricWidget from '../../fixtures/dashboards/creation/widgets/singleWidgetText.json';

Cypress.Commands.add(
  'waitUntilForDashboardRoles',
  (accessRightsTestId, expectedElementCount) => {
    const openModalAndCheck: () => Cypress.Chainable<boolean> = () => {
      cy.getByTestId({ testId: accessRightsTestId }).invoke('show').click();
      cy.getByTestId({ testId: 'role-input' }).eq(1).should('be.visible');

      return cy
        .get('[data-testid="role-input"]')
        .should('be.visible')
        .then(($element) => {
          cy.getByTestId({ testId: 'CloseIcon' }).click();

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

Cypress.Commands.add('verifyGraphContainer', () => {
  cy.get('[class*="graphContainer"]')
    .should('be.visible')
    .within(() => {
      cy.get('[class*="graphText"]')
        .should('be.visible')
        .within(() => {
          cy.get('[class*="MuiTypography-h2"]').should('be.visible');

          cy.get('[class*="MuiTypography-h5"]')
            .eq(0)
            .should('contain', singleMetricWidget.rtaValues.warning);

          cy.get('[class*="MuiTypography-h5"]')
            .eq(1)
            .should('contain', singleMetricWidget.rtaValues.critical);
        });
    });
});

Cypress.Commands.add('verifyDuplicatesGraphContainer', () => {
  cy.get('[class*="graphContainer"]')
    .eq(1)
    .should('be.visible')
    .within(() => {
      cy.get('[class*="graphText"]')
        .should('be.visible')
        .within(() => {
          cy.get('[class*="MuiTypography-h2"]').should('be.visible');

          cy.get('[class*="MuiTypography-h5"]')
            .eq(0)
            .should('contain', singleMetricWidget.plValues.warning);

          cy.get('[class*="MuiTypography-h5"]')
            .eq(1)
            .should('contain', singleMetricWidget.plValues.critical);
        });
    });
});

declare global {
  namespace Cypress {
    interface Chainable {
      verifyDuplicatesGraphContainer: (singleMetricWidget) => Cypress.Chainable;
      verifyGraphContainer: (singleMetricWidget) => Cypress.Chainable;
      waitUntilForDashboardRoles: (
        accessRightsTestId: string,
        expectedElementCount: number
      ) => Cypress.Chainable;
    }
  }
}

export {};
