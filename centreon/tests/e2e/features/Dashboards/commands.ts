/* eslint-disable newline-before-return */
/* eslint-disable @typescript-eslint/no-namespace */
import metrics from '../../fixtures/dashboards/creation/widgets/metrics.json';
import singleMetricPayloadPl from '../../fixtures/dashboards/creation/widgets/singleMetricPayloadPl.json';
import singleMetricPayloadRta from '../../fixtures/dashboards/creation/widgets/singleMetricPayloadRta.json';
import singleMetricDoubleWidgets from '../../fixtures/dashboards/creation/widgets/dashboardWithTwoWidgets.json';
import metricsGraphWidget from '../../fixtures/dashboards/creation/widgets/metricsGraphWidget.json';
import statusGridWidget from '../../fixtures/dashboards/creation/widgets/status-grid-widget.json';
import textWidget from '../../fixtures/dashboards/creation/widgets/textWidget.json';
import topBottomWidget from '../../fixtures/dashboards/creation/widgets/dashboardWithTopBottomWidget.json';

Cypress.Commands.add('enableDashboardFeature', () => {
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
});

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
            .should('contain', metrics.rtaValues.warning);

          cy.get('[class*="MuiTypography-h5"]')
            .eq(1)
            .should('contain', metrics.rtaValues.critical);
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
            .should('contain', metrics.plValues.warning);

          cy.get('[class*="MuiTypography-h5"]')
            .eq(1)
            .should('contain', metrics.plValues.critical);
        });
    });
});

Cypress.Commands.add('waitUntilPingExists', () => {
  cy.getByTestId({ testId: 'Select resource' }).eq(1).click();

  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');

  return cy.waitUntil(
    () => {
      cy.getByTestId({ testId: 'Select resource' }).eq(0).realClick();
      cy.contains('Centreon-Server').realClick();
      cy.getByTestId({ testId: 'Select resource' }).eq(1).realClick();

      return cy.wait('@resourceRequest').then((interception) => {
        if (interception && interception.response) {
          cy.log('Response Body:', interception.response.body);
          const responseBody = interception.response.body;
          if (
            Array.isArray(responseBody.result) &&
            responseBody.result.length > 0
          ) {
            const pingFound = responseBody.result.some(
              (result) => result.name === 'Ping'
            );

            if (pingFound) {
              cy.contains('Ping').click();
              return cy.wrap(true);
            }
            cy.log('Ping not found in the API response');

            return cy.wrap(false);
          }
          cy.log('Response is not an array or is empty');
          return cy.wrap(false);
        }
        cy.log('Interception or response is undefined');
        return cy.wrap(false);
      });
    },
    {
      errorMsg: 'Timed out waiting for Ping to exist',
      interval: 3000,
      timeout: 60000
    }
  );
});
Cypress.Commands.add(
  'insertDashboardWithWidget',
  (dashboardBody, patchBody) => {
    cy.request({
      body: {
        ...dashboardBody
      },
      method: 'POST',
      url: '/centreon/api/latest/configuration/dashboards'
    }).then((response) => {
      const dashboardId = response.body.id;
      cy.waitUntil(
        () => {
          return cy
            .request({
              method: 'GET',
              url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`
            })
            .then((getResponse) => {
              return getResponse.body && getResponse.body.id === dashboardId;
            });
        },
        {
          timeout: 10000
        }
      );
      cy.request({
        body: patchBody,
        method: 'PATCH',
        url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`
      });
    });
  }
);

interface Dashboard {
  description?: string;
  name: string;
}

type metricsGraphWidgetJSONData = typeof metricsGraphWidget;
type statusGridWidgetJSONData = typeof statusGridWidget;
type singleMetricPayloadPlJSONData = typeof singleMetricPayloadPl;
type singleMetricPayloadRtaJSONData = typeof singleMetricPayloadRta;
type singleMetricDoubleWidgetsJSONData = typeof singleMetricDoubleWidgets;
type textWidgetJSONData = typeof textWidget;
type topBottomWidgetJSONData = typeof topBottomWidget;
type widgetJSONData =
  | singleMetricPayloadPlJSONData
  | singleMetricPayloadRtaJSONData
  | singleMetricDoubleWidgetsJSONData
  | metricsGraphWidgetJSONData
  | statusGridWidgetJSONData
  | textWidgetJSONData
  | topBottomWidgetJSONData;

declare global {
  namespace Cypress {
    interface Chainable {
      enableDashboardFeature: () => Cypress.Chainable;
      insertDashboardWithWidget: (
        dashboard: Dashboard,
        patch: widgetJSONData
      ) => Cypress.Chainable;
      verifyDuplicatesGraphContainer: (metrics) => Cypress.Chainable;
      verifyGraphContainer: (metrics) => Cypress.Chainable;
      waitUntilForDashboardRoles: (
        accessRightsTestId: string,
        expectedElementCount: number
      ) => Cypress.Chainable;
      waitUntilPingExists: () => Cypress.Chainable;
    }
  }
}

export {};
