/* eslint-disable @typescript-eslint/method-signature-style */
/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable newline-before-return */
/* eslint-disable @typescript-eslint/no-namespace */
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
        console.log('Widget added successfully:', patchResponse);
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

      console.log('FormData before POST:', JSON.stringify(dataToLog, null, 2));

      cy.request({
        body: formData,
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        method: 'POST',
        url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`
      }).then((patchResponse) => {
        console.log('Widget added successfully:', patchResponse);
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
  (index, expectedColors, expectedValue) => {
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

              // Get the value of the <p> element next to the <a> tag
              cy.wrap(aTag)
                .next('p')
                .invoke('text')
                .then((text) => {
                  expect(text).to.contain(expectedValue[i]);
                });
            });
          });
      });
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
        is_password: false,
        name: 'MacroName',
        value: 'macroValue'
      }
    ],
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

Cypress.Commands.add('addNewServiceAndReturnId', (hostId: number, serviceData = {}) => {
  const defaultServiceData = {
    name: 'generic-service',
    host_id: hostId,
    geo_coords: '48.10,12.5',
    comment: 'string',
    service_template_id: 5,
    check_command_id: null,
    check_command_args: [],
    max_check_attempts: 1,
  };

  const requestBody = { ...defaultServiceData, ...serviceData, host_id: hostId };

  cy.request({
    body: requestBody,
    method: 'POST',
    url: '/centreon/api/latest/configuration/services'
  }).then((response) => {
    expect(response.status).to.eq(201);
    return response.body.id;
  });
});



Cypress.Commands.add('addMultipleHosts', (numberOfHosts = 20): Cypress.Chainable<{ hostIds: number[]; serviceIds: number[] }> => {
  const hostIds: number[] = [];
  const serviceIds: number[] = [];

  let chain = cy.wrap(null);

  for (let i = 0; i < numberOfHosts; i++) {
    const uniqueHostData = {
      name: `generic-active-host-${i + 1}`,
      alias: `generic-active-host-alias-${i + 1}`,
    };

    const uniqueServiceData = {
      name: `service-${i + 1}`,
      geo_coords: '48.10,12.5',
    };

    chain = chain.then(() => {
      return cy.addNewHostAndReturnId(uniqueHostData)
        .then((hostId: number) => {
          hostIds.push(hostId);

          return cy.addNewServiceAndReturnId(hostId, uniqueServiceData)
            .then((serviceId: number | null) => {
              if (serviceId !== null) {
                serviceIds.push(serviceId);
              }
              return cy.wrap(null);
            });
        });
    });
  }

  return chain.then(() => {
    cy.log('All hosts and services have been created and associated.');
    return cy.wrap({ hostIds, serviceIds });
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
  acknowledgement_timeout: number;
  action_url: string;
  active_check_enabled: number;
  add_inherited_contact: boolean;
  add_inherited_contact_group: boolean;
  address: string;
  alias: string;
  categories: Array<number>;
  check_command_args: Array<string>;
  check_command_id: number;
  check_timeperiod_id: number;
  comment: string;
  event_handler_command_args: Array<string>;
  event_handler_command_id: number;
  event_handler_enabled: number;
  first_notification_delay: number;
  flap_detection_enabled: number;
  freshness_checked: number;
  freshness_threshold: number;
  geo_coords: string;
  groups: Array<number>;
  high_flap_threshold: number;
  icon_alternative: string;
  icon_id: number;
  is_activated: boolean;
  low_flap_threshold: number;
  macros: Array<object>;
  max_check_attempts: number;
  monitoring_server_id: number;
  name: string;
  normal_check_interval: number;
  note: string;
  note_url: string;
  notification_enabled: number;
  notification_interval: number;
  notification_options: number;
  notification_timeperiod_id: number;
  passive_check_enabled: number;
  recovery_notification_delay: number;
  retry_check_interval: number;
  severity_id: number;
  snmp_community: string;
  snmp_version: string;
  templates: Array<number>;
  timezone_id: number;
}

interface ServiceDataType {
  name: string;
  host_id: number;
  geo_coords: string;
  comment: string;
  service_template_id: number | null;
  check_command_id: number | null;
  check_command_args: Array<string>;
  check_timeperiod_id: number | null;
  max_check_attempts: number;
  normal_check_interval: number;
  retry_check_interval: number;
  active_check_enabled: number;
  passive_check_enabled: number;
  volatility_enabled: number;
  notification_enabled: number;
  is_contact_additive_inheritance: boolean;
  is_contact_group_additive_inheritance: boolean;
  notification_interval: number;
  notification_timeperiod_id: number;
  notification_type: number;
  first_notification_delay: number;
  recovery_notification_delay: number;
  acknowledgement_timeout: number;
  freshness_checked: number;
  freshness_threshold: number;
  flap_detection_enabled: number;
  low_flap_threshold: number;
  high_flap_threshold: number;
  event_handler_enabled: number;
  event_handler_command_id: number;
  event_handler_command_args: Array<string>;
  graph_template_id: number | null;
  note: string;
  note_url: string;
  action_url: string;
  icon_id: number | null;
  icon_alternative: string;
  severity_id: number;
  is_activated: boolean;
  service_categories: Array<number>;
  service_groups: Array<number>;
  macros: Array<object>;
}

declare global {
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
        patchBody1: Record<string, any>,
        patchBody2: Record<string, any>,
        widgetName: string,
        widgetType: string
      ) => Cypress.Chainable;
      addMultipleHosts(numberOfHosts?: number): Chainable<{ hostIds: number[]; serviceIds: number[] }>;
      insertDashboardWithWidget: (
        dashboard: Dashboard,
        patchBody: Record<string, any>,
        widgetName: string,
        widgetType: string
      ) => Chainable<any>;
      patchServiceWithHost: (
        hostId: string,
        serviceId: string
      ) => Cypress.Chainable;
      addNewServiceAndReturnId(hostId: number, serviceData?: Partial<ServiceDataType>): Chainable<number>;
      verifyDuplicatesGraphContainer: (metrics) => Cypress.Chainable;
      verifyGraphContainer: (metrics) => Cypress.Chainable;
      verifyLegendItemStyle: (
        index: number,
        expectedColors: Array<string>,
        expectedValue: Array<string>
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

export {};
