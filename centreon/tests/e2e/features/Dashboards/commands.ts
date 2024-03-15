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

Cypress.Commands.add('getCellContent', (rowIndex, columnIndex) => {
  cy.waitUntil(
    () =>
      cy
        .get(
          `.MuiTable-root:eq(1) .MuiTableRow-root:nth-child(${rowIndex}) .MuiTableCell-root:nth-child(${columnIndex})`
        )
        .should('be.visible')
        .then(() => true),
    { interval: 1000, timeout: 10000 }
  );

  return cy
    .get(
      `.MuiTable-root:eq(1) .MuiTableRow-root:nth-child(${rowIndex}) .MuiTableCell-root:nth-child(${columnIndex})`
    )
    .invoke('text')
    .then((content) => {
      const columnContents = content ? content.match(/\w+/g) || [] : [];
      cy.log(
        `Column contents (${rowIndex}, ${columnIndex}): ${columnContents
          .join(',')
          .trim()}`
      );

      return cy.wrap(columnContents);
    });
});

Cypress.Commands.add('applyAcl', () => {
  const apacheUser = Cypress.env('WEB_IMAGE_OS').includes('alma')
    ? 'apache'
    : 'www-data';

  cy.execInContainer({
    command: `su -s /bin/sh ${apacheUser} -c "/usr/bin/env php -q /usr/share/centreon/cron/centAcl.php"`,
    name: 'web'
  });
});

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
      applyAcl: () => Cypress.Chainable;
      enableDashboardFeature: () => Cypress.Chainable;
      getCellContent: (rowIndex: number, colIndex: number) => Cypress.Chainable;
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
    }
  }
}

export {};
