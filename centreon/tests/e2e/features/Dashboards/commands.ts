import { PAGES } from 'fixtures/shared/constants/pages';
import metrics from '../../fixtures/dashboards/creation/widgets/metrics.json';

Cypress.Commands.add('enableDashboardFeature', () => {
  cy.execInContainer({
    command: `sed -i 's@"dashboard": [0-3]@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
});

Cypress.Commands.add('visitDashboards', () => {
  cy.intercept({
    method: 'GET',
    times: 1,
    url: '/centreon/api/latest/configuration/dashboards*'
  }).as('listAllDashboards');

  const dashboardsUrl = PAGES.monitoring.dashboardsLibrary;
  cy.url().then((url) =>
    url.includes(dashboardsUrl)
      ? cy.visit(dashboardsUrl)
      : cy.visit(PAGES.monitoring.dashboards)
  );

  cy.wait('@listAllDashboards');
});

Cypress.Commands.add('visitDashboard', (name) => {
  cy.visitDashboards();
  cy.contains(name).click();

  cy.url().should('match', /\/home\/dashboards\/library\/\d+$/);
});

Cypress.Commands.add('editDashboard', (name) => {
  cy.visitDashboard(name);

  cy.getByLabel({
    label: 'Edit dashboard',
    tag: 'button'
  }).click();

  cy.url().should('match', /\/home\/dashboards\/library\/\d+\?edit=true/);

  cy.getByLabel({
    label: 'Save',
    tag: 'button'
  }).should('be.visible');
});

Cypress.Commands.add('editWidget', (nameOrPosition) => {
  if (typeof nameOrPosition === 'string') {
    cy.contains('div.react-grid-item', nameOrPosition).as('widgetItem');
  } else {
    cy.get('div.react-grid-item')
      .eq(nameOrPosition - 1)
      .as('widgetItem');
  }

  cy.get('@widgetItem').within(() => {
    cy.getByTestId({ testId: 'More actions' }).should('be.visible').click();
  });

  cy.getByLabel({
    label: 'Edit widget',
    tag: 'li'
  })
    .should('exist') // do not check with "be.visible" because it can be hidden by the tooltip of "more actions" button
    .click();

  cy.contains('Widget properties').should('be.visible');
});

Cypress.Commands.add(
  'waitUntilForDashboardRoles',
  (accessRightsTestId, expectedElementCount, closeIconIndex) => {
    const openModalAndCheck: () => Cypress.Chainable<boolean> = () => {
      cy.getByTestId({ testId: accessRightsTestId }).invoke('show').click();
      cy.get('.MuiSelect-select').should('be.visible');

      return cy
        .get('.MuiSelect-select')
        .should('be.visible')
        .then(($element) => {
          if (closeIconIndex !== undefined) {
            cy.getByTestId({ testId: 'CloseIcon' }).eq(closeIconIndex).click();
          } else {
            cy.getByTestId({ testId: 'CloseIcon' }).click();
          }

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
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/services\/names.*$/
  }).as('servicesRequest');

  return cy.waitUntil(
    () => {
      cy.getByTestId({ testId: 'Select resource' }).eq(0).realClick();
      cy.contains('Centreon-Server').realClick();
      cy.getByTestId({ testId: 'Select resource' }).eq(1).realClick();

      return cy.wait('@servicesRequest').then((interception) => {
        if (interception?.response) {
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
  (dashboardBody, patchBody, widgetName, widgetType) => {
    cy.request({
      body: { ...dashboardBody },
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
        { timeout: 10000 }
      );

      const formData = new FormData();

      formData.append('panels[0][name]', widgetName);
      formData.append('panels[0][widget_type]', widgetType);

      formData.append('panels[0][layout][x]', '0');
      formData.append('panels[0][layout][y]', '0');
      formData.append('panels[0][layout][width]', '12');
      formData.append('panels[0][layout][height]', '3');
      formData.append('panels[0][layout][min_width]', '2');
      formData.append('panels[0][layout][min_height]', '2');

      formData.append('panels[0][widget_settings]', JSON.stringify(patchBody));

      cy.request({
        body: formData,
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        method: 'POST',
        url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`
      }).then((patchResponse) => {
        cy.log('Widget added successfully:', patchResponse);
      });
    });
  }
);

Cypress.Commands.add(
  'insertDashboardWithDoubleWidget',
  (dashboardBody, patchBody1, patchBody2, widgetName, widgetType) => {
    cy.request({
      body: { ...dashboardBody },
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
        { timeout: 10000 }
      );

      const formData = new FormData();

      // Panel 1
      formData.append('panels[0][name]', widgetName);
      formData.append('panels[0][widget_type]', widgetType);
      formData.append('panels[0][layout][x]', '0');
      formData.append('panels[0][layout][y]', '0');
      formData.append('panels[0][layout][width]', '12');
      formData.append('panels[0][layout][height]', '3');
      formData.append('panels[0][layout][min_width]', '2');
      formData.append('panels[0][layout][min_height]', '2');
      formData.append('panels[0][widget_settings]', JSON.stringify(patchBody1));

      // Panel 2
      formData.append('panels[1][name]', widgetName);
      formData.append('panels[1][widget_type]', widgetType);
      formData.append('panels[1][layout][x]', '0');
      formData.append('panels[1][layout][y]', '3');
      formData.append('panels[1][layout][width]', '12');
      formData.append('panels[1][layout][height]', '3');
      formData.append('panels[1][layout][min_width]', '2');
      formData.append('panels[1][layout][min_height]', '2');
      formData.append('panels[1][widget_settings]', JSON.stringify(patchBody2));

      // Log form data
      const dataToLog = {};
      formData.forEach((value, key) => {
        dataToLog[key] = value;
      });

      cy.request({
        body: formData,
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        method: 'POST',
        url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`
      }).then((patchResponse) => {
        cy.log('Widget added successfully:', patchResponse);
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
      const columnContents = content ? content.match(/[A-Z][a-z]*/g) || [] : [];
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

Cypress.Commands.add(
  'verifyLegendItemStyle',
  (
    index: number,
    expectedColors: Array<string>,
    expectedValues: Array<string>,
    alternativeValues: Array<string> = []
  ): Cypress.Chainable => {
    cy.get('[data-testid="Legend"] > *')
      .eq(index)
      .each(($legendItem) => {
        cy.wrap($legendItem)
          .find('[class*=legendItem] a')
          .then(($aTags) => {
            $aTags.each((i, aTag) => {
              cy.wrap(aTag)
                .find('div')
                .invoke('attr', 'style')
                .then((style) => {
                  expect(style).to.contain(expectedColors[i]);
                });

              cy.wrap(aTag)
                .next('p')
                .invoke('text')
                .then((text) => {
                  const possibleValues = [expectedValues[i]];

                  if (alternativeValues[i]) {
                    possibleValues.push(alternativeValues[i]);
                  }

                  expect(text.trim()).to.match(
                    new RegExp(possibleValues.join('|'))
                  );
                });
            });
          });
      });

    return cy;
  }
);

Cypress.Commands.add('addNewHostAndReturnId', (hostData = {}) => {
  const defaultHostData = {
    address: '127.0.0.1',
    alias: 'generic-active-host',
    groups: [53],
    macros: [
      {
        description: 'Some text to describe the macro',
        // biome-ignore lint/style/useNamingConvention: <explanation>
        is_password: false,
        name: 'MacroName',
        value: 'macroValue'
      }
    ],
    // biome-ignore lint/style/useNamingConvention: <explanation>
    monitoring_server_id: 1,
    name: 'generic-active-host',
    templates: [2]
  };

  const requestBody = { ...defaultHostData, ...hostData };

  cy.request({
    body: requestBody,
    method: 'POST',
    url: '/centreon/api/latest/configuration/hosts'
  }).then((response) => {
    expect(response.status).to.eq(201);
    return response.body.id;
  });
});

Cypress.Commands.add('getServiceIdByName', (serviceName) => {
  return cy
    .request({
      method: 'GET',
      url: '/centreon/api/latest/monitoring/services'
    })
    .then((response) => {
      const service = response.body.result.find(
        (s) => s.display_name === serviceName
      );
      if (service) {
        return service.id;
      }
      throw new Error(`Service with name ${serviceName} not found`);
    });
});

Cypress.Commands.add('patchServiceWithHost', (hostId, serviceId) => {
  const patchData = {
    // biome-ignore lint/style/useNamingConvention: <explanation>
    host_id: hostId
  };
  cy.request({
    body: patchData,
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/services/${serviceId}`
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

interface Dashboard {
  description?: string;
  name: string;
}

interface HostDataType {
  // biome-ignore lint/style/useNamingConvention: <explanation>
  acknowledgement_timeout: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  action_url: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  active_check_enabled: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  add_inherited_contact: boolean;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  add_inherited_contact_group: boolean;
  address: string;
  alias: string;
  categories: Array<number>;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  check_command_args: Array<string>;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  check_command_id: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  check_timeperiod_id: number;
  comment: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  event_handler_command_args: Array<string>;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  event_handler_command_id: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  event_handler_enabled: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  first_notification_delay: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  flap_detection_enabled: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  freshness_checked: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  freshness_threshold: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  geo_coords: string;
  groups: Array<number>;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  high_flap_threshold: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  icon_alternative: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  icon_id: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  is_activated: boolean;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  low_flap_threshold: number;
  macros: Array<object>;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  max_check_attempts: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  monitoring_server_id: number;
  name: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  normal_check_interval: number;
  note: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  note_url: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  notification_enabled: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  notification_interval: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  notification_options: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  notification_timeperiod_id: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  passive_check_enabled: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  recovery_notification_delay: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  retry_check_interval: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  severity_id: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  snmp_community: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  snmp_version: string;
  templates: Array<number>;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  timezone_id: number;
}

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
  namespace Cypress {
    interface Chainable {
      addNewHostAndReturnId: (
        hostData?: Partial<HostDataType>
      ) => Cypress.Chainable;
      applyAcl: () => Cypress.Chainable;
      editDashboard: (name: string) => Cypress.Chainable;
      editWidget: (nameOrPosition: string | number) => Cypress.Chainable;
      enableDashboardFeature: () => Cypress.Chainable;
      getCellContent: (rowIndex: number, colIndex: number) => Cypress.Chainable;
      getServiceIdByName: (serviceName: string) => Cypress.Chainable;
      insertDashboardWithDoubleWidget: (
        dashboard: Dashboard,
        // biome-ignore lint/suspicious/noExplicitAny: <explanation>
        patchBody1: Record<string, any>,
        // biome-ignore lint/suspicious/noExplicitAny: <explanation>
        patchBody2: Record<string, any>,
        widgetName: string,
        widgetType: string
      ) => Cypress.Chainable;
      insertDashboardWithWidget: (
        dashboard: Dashboard,
        // biome-ignore lint/suspicious/noExplicitAny: <explanation>
        patchBody: Record<string, any>,
        widgetName: string,
        widgetType: string
        // biome-ignore lint/suspicious/noExplicitAny: <explanation>
      ) => Chainable<any>;
      patchServiceWithHost: (
        hostId: string,
        serviceId: string
      ) => Cypress.Chainable;
      verifyDuplicatesGraphContainer: (metrics) => Cypress.Chainable;
      verifyGraphContainer: (metrics) => Cypress.Chainable;
      verifyLegendItemStyle: (
        index: number,
        expectedColors: Array<string>,
        expectedValue: Array<string>,
        alternativeValues?: Array<string>
      ) => Cypress.Chainable;
      visitDashboard: (name: string) => Cypress.Chainable;
      visitDashboards: () => Cypress.Chainable;
      waitForElementToBeVisible(
        selector: string,
        timeout?: number,
        interval?: number
      ): Cypress.Chainable;
      waitUntilForDashboardRoles: (
        accessRightsTestId: string,
        expectedElementCount: number,
        closeIconIndex?: number
      ) => Cypress.Chainable;
      waitUntilPingExists: () => Cypress.Chainable;
    }
  }
}
