import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  aclUserPassword,
  buildAclTableQuery,
  buildCentAclCronQuery,
  buildGroupActionsCountQuery,
  buildHostAcknowledgedQuery,
  buildHostIdQuery,
  buildMetricIdQuery,
  buildResourcesAccessChangedQuery,
  dashboardsViewerMenu,
  defaultAclGroup,
  defaultMenuAccess,
  defaultResourcesAccess,
  resourcesStatusMenu,
  uniqueSorted
} from '../common';

interface AclRow {
  host: string;
  service: string | null;
}

interface NamedResource {
  name: string;
}

interface PerformanceMetric {
  metric: string;
  service_name: string;
}

const apiLatest = '/centreon/api/latest';
// Rendered in the iframe of a legacy page the user may not reach
const legacyNotAllowedMessage = 'You are not allowed to reach this page';
const perfdata = 'rbt_value=95;80;90';
// APPLYCFG reloads Broker a few seconds after Engine: a result sent while
// its SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;
const pollingInterval = 5_000;
const acknowledgementTimeout = 60_000;
const centAclCronTimeout = 90_000;

let monitoredHosts: Array<string> = [];
let monitoredServices: Array<string> = [];
let dashboardId = 0;
let dashboardTableHosts: Array<string> = [];

beforeEach(() => {
  cy.startContainers();

  monitoredHosts = [];
  monitoredServices = [];
  dashboardId = 0;
  dashboardTableHosts = [];

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?*`
  }).as('getResources');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('getPerformanceData');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const reloadAcl = (): void => {
  clapi('RELOAD', 'ACL', '');

  // Compute the centreon_acl table now rather than waiting for the cron
  cy.applyAcl();
};

const createUserInGroup = (user: string, aclGroup: string): void => {
  clapi(
    'ADD',
    'CONTACT',
    `${user};${user};${user}@localhost;${aclUserPassword};0;1;en_US;local`
  );
  clapi('ADD', 'ACLGROUP', `${aclGroup};${aclGroup}`);
  clapi('SETCONTACT', 'ACLGROUP', `${aclGroup};${user}`);
};

const grantMenu = (
  menuAccess: string,
  aclGroup: string,
  rule: Array<string>
): void => {
  cy.addACLMenu({ name: menuAccess, rule });
  clapi('ADDMENU', 'ACLGROUP', `${aclGroup};${menuAccess}`);
};

const addPassiveHost = (host: string): void => {
  clapi('ADD', 'HOST', `${host};${host};127.0.0.1;generic-host;central;`);
  clapi('SETPARAM', 'HOST', `${host};active_checks_enabled;0`);
  clapi('SETPARAM', 'HOST', `${host};passive_checks_enabled;1`);
  clapi('SETPARAM', 'HOST', `${host};max_check_attempts;1`);
  monitoredHosts.push(host);
};

const addPassiveService = (host: string, service: string): void => {
  cy.addService({
    activeCheckEnabled: false,
    host,
    maxCheckAttempts: 1,
    name: service,
    template: 'Ping-LAN'
  });
  monitoredServices.push(service);
};

// Export, wait for the resources, then put them in a problem state:
// the Resources Status default filter lists unhandled problems only
const exportAndSetProblems = (): void => {
  cy.applyPollerConfiguration();
  checkHostsAreMonitored(monitoredHosts.map((name) => ({ name })));
  if (monitoredServices.length > 0) {
    checkServicesAreMonitored(monitoredServices.map((name) => ({ name })));
  }
  cy.wait(brokerReloadWaitingTime);

  cy.submitResults(
    monitoredHosts.map((host) => ({ host, output: 'rbt down', status: 'down' }))
  );
  checkHostsAreMonitored(
    monitoredHosts.map((name) => ({ name, status: 'down' }))
  );
};

const loginAs = (user: string): void => {
  cy.clearCookies();
  cy.loginByCredentials({ login: user, password: aclUserPassword });
};

// Names of the resources the session in use can see through the API
const visibleResourceNames = (): Cypress.Chainable<Array<string>> =>
  cy
    .request({
      method: 'GET',
      url: `${apiLatest}/monitoring/resources?limit=100`
    })
    .then(({ body }) =>
      cy.wrap(
        (body.result as Array<NamedResource>).map(({ name }) => name),
        { log: false }
      )
    );

const getHostId = (host: string): Cypress.Chainable<number> =>
  cy
    .requestOnDatabase({ database: 'centreon', query: buildHostIdQuery(host) })
    .then(([rows]) => {
      expect(rows, `host ${host}`).to.have.length(1);

      return cy.wrap(Number(rows[0].host_id), { log: false });
    });

const aclTableOf = (aclGroup: string): Cypress.Chainable<Array<AclRow>> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildAclTableQuery(aclGroup)
    })
    .then(([rows]) => cy.wrap(rows as Array<AclRow>, { log: false }));

const visitResourcesStatus = (): void => {
  cy.visit(PAGES.monitoring.resourcesStatus);
  cy.wait('@getResources').its('response.statusCode').should('eq', 200);
};

const selectResourceRow = (name: string): void => {
  cy.contains(name)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();
};

const acknowledgeHostAs = (host: string): Cypress.Chainable<number> =>
  getHostId(host).then((hostId) =>
    cy
      .request({
        body: {
          comment: 'rbt acl acknowledgement',
          is_notify_contacts: false,
          is_persistent_comment: true,
          is_sticky: true,
          with_services: false
        },
        failOnStatusCode: false,
        method: 'POST',
        url: `${apiLatest}/monitoring/hosts/${hostId}/acknowledgements`
      })
      .then(({ status }) => cy.wrap(status, { log: false }))
  );

const widgetBase = {
  options: {
    description: { content: null, enabled: false },
    refreshInterval: 'default'
  }
};

// The dashboard and its three widgets, created through the API as the admin
const createDashboardWithWidgets = ({
  dashboardName,
  graphHost,
  metricName,
  tableHosts
}: {
  dashboardName: string;
  graphHost: string;
  metricName: string;
  tableHosts: Array<string>;
}): void => {
  const hostResources: Array<{ id: number; name: string }> = [];

  tableHosts.forEach((host) => {
    getHostId(host).then((id) => hostResources.push({ id, name: host }));
  });

  getHostId(graphHost).then((graphHostId) => {
    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildMetricIdQuery(graphHost, metricName)
    }).then(([rows]) => {
      expect(rows, `metric ${metricName} of ${graphHost}`).to.have.length(1);

      const panels = [
        {
          name: 'rbt status grid',
          settings: {
            data: {
              resources: [{ resources: hostResources, resourceType: 'host' }]
            },
            options: {
              ...widgetBase.options,
              resourceType: 'host',
              sortBy: 'status_severity_code',
              states: [],
              statuses: ['success', 'warning', 'problem', 'undefined'],
              tiles: 100,
              viewMode: 'standard'
            }
          },
          type: 'centreon-widget-statusgrid'
        },
        {
          name: 'rbt resource table',
          settings: {
            data: {
              resources: [{ resources: hostResources, resourceType: 'host' }]
            },
            options: {
              ...widgetBase.options,
              displayType: 'all',
              limit: 30,
              states: [],
              statuses: []
            }
          },
          type: 'centreon-widget-resourcestable'
        },
        {
          name: 'rbt metrics graph',
          settings: {
            data: {
              metrics: [
                {
                  criticalHighThreshold: null,
                  criticalLowThreshold: null,
                  id: Number(rows[0].metric_id),
                  name: metricName,
                  unit: '',
                  warningHighThreshold: null,
                  warningLowThreshold: null
                }
              ],
              resources: [
                {
                  resources: [{ id: graphHostId, name: graphHost }],
                  resourceType: 'host'
                }
              ]
            },
            options: {
              ...widgetBase.options,
              threshold: {
                criticalType: 'default',
                customCritical: null,
                customWarning: null,
                enabled: false,
                warningType: 'default'
              },
              timeperiod: { end: null, start: null, timePeriodType: 1 }
            }
          },
          type: 'centreon-widget-graph'
        }
      ];

      cy.request({
        body: { name: dashboardName },
        method: 'POST',
        url: `${apiLatest}/configuration/dashboards`
      }).then(({ body }) => {
        dashboardId = body.id;

        const formData = new FormData();

        panels.forEach(({ name, settings, type }, index) => {
          const prefix = `panels[${index}]`;

          formData.append(`${prefix}[name]`, name);
          formData.append(`${prefix}[widget_type]`, type);
          formData.append(`${prefix}[layout][x]`, '0');
          formData.append(`${prefix}[layout][y]`, String(index * 4));
          formData.append(`${prefix}[layout][width]`, '12');
          formData.append(`${prefix}[layout][height]`, '4');
          formData.append(`${prefix}[layout][min_width]`, '2');
          formData.append(`${prefix}[layout][min_height]`, '2');
          formData.append(
            `${prefix}[widget_settings]`,
            JSON.stringify(settings)
          );
        });

        cy.request({
          body: formData,
          headers: { 'Content-Type': 'multipart/form-data' },
          method: 'POST',
          url: `${apiLatest}/configuration/dashboards/${dashboardId}`
        });
      });
    });
  });
};

// MON-214314

Given(
  'a non-admin contact {word} in the access group {word} with no menu access',
  (user: string, aclGroup: string) => {
    createUserInGroup(user, aclGroup);
  }
);

When(
  'the administrator creates the menu access {word} granting only Monitoring > Resources Status to {word}',
  (menuAccess: string, aclGroup: string) => {
    grantMenu(menuAccess, aclGroup, resourcesStatusMenu);
  }
);

When('the administrator reloads the ACL of {word}', () => {
  reloadAcl();
});

Then(
  '{word} sees only the Monitoring menu and opens Resources Status',
  (user: string) => {
    loginAs(user);

    cy.request({ method: 'GET', url: INTERCEPTORS.api.navigation_list }).then(
      ({ body }) => {
        const labels = Object.values(
          body.result as Record<string, { label: string }>
        ).map(({ label }) => label);

        expect(labels).to.deep.eq(['Monitoring']);
      }
    );

    visitResourcesStatus();
  }
);

Then('the hosts configuration page is refused to {word}', () => {
  cy.request(PAGES.configuration.hostsLegacyFrame)
    .its('body')
    .should('contain', legacyNotAllowedMessage);

  cy.visit(PAGES.configuration.hostsLegacy);
  cy.getIframeBody().should('contain', legacyNotAllowedMessage);
});

Then(
  'the API answers {int} on GET {word} and {int} on GET {word} for {word}',
  (
    refusedStatus: number,
    refusedPath: string,
    allowedStatus: number,
    allowedPath: string
  ) => {
    cy.request({
      failOnStatusCode: false,
      method: 'GET',
      url: `${apiLatest}${refusedPath}`
    })
      .its('status')
      .should('eq', refusedStatus);

    cy.request({
      failOnStatusCode: false,
      method: 'GET',
      url: `${apiLatest}${allowedPath}`
    })
      .its('status')
      .should('eq', allowedStatus);
  }
);

// MON-214315

Given(
  '{word} in {word} with the menu access {word}',
  (user: string, aclGroup: string, menuAccess: string) => {
    createUserInGroup(user, aclGroup);
    grantMenu(menuAccess, aclGroup, resourcesStatusMenu);
  }
);

Given(
  'the monitored hosts {word}, {word}, {word} and {word}',
  (...hosts: Array<string>) => {
    hosts.forEach(addPassiveHost);
  }
);

Given(
  'the host group {word} holding only {word}',
  (hostGroup: string, host: string) => {
    clapi('ADD', 'HG', `${hostGroup};${hostGroup}`);
    clapi('ADDMEMBER', 'HG', `${hostGroup};${host}`);
  }
);

Given(
  'the service group {word} holding the service {word} of {word} but not its service {word}',
  (
    serviceGroup: string,
    grantedService: string,
    host: string,
    hiddenService: string
  ) => {
    addPassiveService(host, grantedService);
    addPassiveService(host, hiddenService);
    clapi('ADD', 'SG', `${serviceGroup};${serviceGroup}`);
    clapi('ADDSERVICE', 'SG', `${serviceGroup};${host},${grantedService}`);

    exportAndSetProblems();

    cy.submitResults(
      [grantedService, hiddenService].map((service) => ({
        host,
        output: 'rbt critical',
        perfdata,
        service,
        status: 'critical'
      }))
    );
    checkServicesAreMonitored(
      [grantedService, hiddenService].map((name) => ({
        name,
        status: 'critical'
      }))
    );
  }
);

When(
  'the administrator creates the resources access {word} granting the host group {word} and the service group {word} to {word}',
  (
    resourcesAccess: string,
    hostGroup: string,
    serviceGroup: string,
    aclGroup: string
  ) => {
    clapi('ADD', 'ACLRESOURCE', `${resourcesAccess};${resourcesAccess}`);
    clapi('GRANT_HOSTGROUP', 'ACLRESOURCE', `${resourcesAccess};${hostGroup}`);
    clapi(
      'GRANT_SERVICEGROUP',
      'ACLRESOURCE',
      `${resourcesAccess};${serviceGroup}`
    );
    clapi('ADDRESOURCE', 'ACLGROUP', `${aclGroup};${resourcesAccess}`);
  }
);

Then(
  'the centreon_acl table of {word} holds {word}, {word} and the service {word} only',
  (
    aclGroup: string,
    firstHost: string,
    secondHost: string,
    service: string
  ) => {
    aclTableOf(aclGroup).then((rows) => {
      expect(uniqueSorted(rows.map(({ host }) => host))).to.deep.eq(
        [firstHost, secondHost].sort()
      );
      expect(uniqueSorted(rows.map(({ service: name }) => name))).to.deep.eq([
        service
      ]);
    });
  }
);

Then(
  '{word} sees {word}, {word} and {word} but not {word}, {word} or {word} in Resources Status and in the resources API',
  (user: string, ...names: Array<string>) => {
    const granted = names.slice(0, 3);
    const hidden = names.slice(3);

    loginAs(user);

    visibleResourceNames().then((visible) => {
      expect(visible).to.include.members(granted);
      hidden.forEach((name) => {
        expect(visible).to.not.include(name);
      });
    });

    visitResourcesStatus();
    granted.forEach((name) => {
      cy.contains(name).should('be.visible');
    });
    hidden.forEach((name) => {
      cy.contains(name).should('not.exist');
    });
  }
);

Then('the administrator sees every rbt-acl resource', () => {
  // Drop the session of the restricted user first
  cy.clearCookies();
  cy.loginAsAdminViaApiV2();

  visibleResourceNames().then((visible) => {
    expect(visible).to.include.members([
      ...monitoredHosts,
      ...monitoredServices
    ]);
  });
});

When(
  'the administrator adds {word} to the host group {word} and reloads the ACL of {word}',
  (host: string, hostGroup: string) => {
    clapi('ADDMEMBER', 'HG', `${hostGroup};${host}`);
    reloadAcl();
  }
);

Then(
  '{word} sees {word} in Resources Status without any export',
  (user: string, host: string) => {
    loginAs(user);

    visibleResourceNames().should('include', host);

    visitResourcesStatus();
    cy.contains(host).should('be.visible');
  }
);

When(
  'the administrator grants the Dashboards Viewer menu to {word}',
  (aclGroup: string) => {
    grantMenu(`${aclGroup}-dashboards`, aclGroup, dashboardsViewerMenu);
    reloadAcl();
  }
);

When(
  'the administrator shares with {word} as Viewer the dashboard {word} whose status grid and resource table show {word} and {word} and whose metrics graph shows {word} of {word}',
  (
    user: string,
    dashboardName: string,
    firstHost: string,
    secondHost: string,
    metricName: string,
    graphHost: string
  ) => {
    cy.clearCookies();
    cy.loginAsAdminViaApiV2();
    dashboardTableHosts = [firstHost, secondHost];

    createDashboardWithWidgets({
      dashboardName,
      graphHost,
      metricName,
      tableHosts: [firstHost, secondHost]
    });

    cy.shareDashboardToUser({ dashboardName, role: 'viewer', userName: user });
  }
);

Then(
  '{word} sees only {word} in the status grid and in the resource table',
  (user: string, allowedHost: string) => {
    // Only the hosts set in these two widgets: the graph legend names its own host
    const hiddenHosts = dashboardTableHosts.filter(
      (host) => host !== allowedHost
    );

    loginAs(user);

    cy.then(() => {
      cy.visit(`${PAGES.monitoring.dashboardsLibrary}/${dashboardId}`);
    });

    // One listing per widget: the status grid and the resource table
    cy.wait(['@getResources', '@getResources']).each((interception) => {
      const { response } = interception as unknown as {
        response: { body: { result: Array<NamedResource> } };
      };
      const names = response.body.result.map(({ name }) => name);

      expect(names).to.include(allowedHost);
      hiddenHosts.forEach((host) => {
        expect(names).to.not.include(host);
      });
    });

    cy.contains(allowedHost).should('be.visible');
    hiddenHosts.forEach((host) => {
      cy.contains(host).should('not.exist');
    });
  }
);

Then(
  'the metrics graph of {word} draws only the {word} curve of {word}',
  (_user: string, metricName: string, service: string) => {
    cy.wait('@getPerformanceData').then(({ response }) => {
      const metrics = response?.body.metrics as Array<PerformanceMetric>;

      expect(metrics.map(({ service_name }) => service_name)).to.deep.eq([
        service
      ]);
      expect(metrics.map(({ metric }) => metric)).to.deep.eq([metricName]);
    });
  }
);

// MON-214316

Given(
  '{word} sees the Down host {word} in Resources Status',
  (user: string, host: string) => {
    createUserInGroup(user, defaultAclGroup);
    grantMenu(defaultMenuAccess, defaultAclGroup, resourcesStatusMenu);

    addPassiveHost(host);
    clapi(
      'ADD',
      'ACLRESOURCE',
      `${defaultResourcesAccess};${defaultResourcesAccess}`
    );
    clapi('GRANT_HOST', 'ACLRESOURCE', `${defaultResourcesAccess};${host}`);
    clapi(
      'ADDRESOURCE',
      'ACLGROUP',
      `${defaultAclGroup};${defaultResourcesAccess}`
    );
    exportAndSetProblems();
    reloadAcl();

    loginAs(user);
    visitResourcesStatus();
    cy.contains(host).should('be.visible');
  }
);

Given('no actions access is linked to {word}', (aclGroup: string) => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildGroupActionsCountQuery(aclGroup)
  }).then(([rows]) => {
    expect(Number(rows[0].count)).to.eq(0);
  });
});

Then(
  'the Acknowledge action is disabled for {word} and the acknowledge API answers {int}',
  (_user: string, status: number) => {
    const [host] = monitoredHosts;

    selectResourceRow(host);
    cy.getByLabel({ label: 'Acknowledge' }).last().should('be.disabled');

    acknowledgeHostAs(host).should('eq', status);
  }
);

When(
  'the administrator creates the actions access {word} granting only Acknowledge a host to {word}',
  (actionsAccess: string, aclGroup: string) => {
    cy.addACLAction({
      actions: ['host_acknowledgement'],
      description: actionsAccess,
      name: actionsAccess
    });
    clapi('ADDACTION', 'ACLGROUP', `${aclGroup};${actionsAccess}`);
  }
);

Then(
  'the acknowledge API answers {int} for {word} and {word} is acknowledged',
  (status: number, user: string, host: string) => {
    // A new session loads the actions granted after the previous login
    loginAs(user);

    acknowledgeHostAs(host).should('eq', status);

    cy.waitUntil(
      () =>
        cy
          .requestOnDatabase({
            database: 'centreon_storage',
            query: buildHostAcknowledgedQuery(host)
          })
          .then(([rows]) => Number(rows[0]?.acknowledged) === 1),
      {
        errorMsg: `${host} is not acknowledged`,
        interval: pollingInterval,
        timeout: acknowledgementTimeout
      }
    );
  }
);

Then('the downtime action stays disabled for {word}', () => {
  const [host] = monitoredHosts;

  visitResourcesStatus();

  // The acknowledged host leaves the unhandled filter: search it explicitly
  cy.get('input[placeholder="Search"]').clear().type(`${host}{enter}`);
  selectResourceRow(host);
  cy.getByLabel({ label: 'Set downtime' }).last().should('be.disabled');
});

// MON-214317

Given(
  '{word} sees {word} and not {word} in Resources Status',
  (user: string, grantedHost: string, hiddenHost: string) => {
    createUserInGroup(user, defaultAclGroup);
    grantMenu(defaultMenuAccess, defaultAclGroup, resourcesStatusMenu);

    addPassiveHost(grantedHost);
    addPassiveHost(hiddenHost);
    clapi(
      'ADD',
      'ACLRESOURCE',
      `${defaultResourcesAccess};${defaultResourcesAccess}`
    );
    clapi(
      'GRANT_HOST',
      'ACLRESOURCE',
      `${defaultResourcesAccess};${grantedHost}`
    );
    clapi(
      'ADDRESOURCE',
      'ACLGROUP',
      `${defaultAclGroup};${defaultResourcesAccess}`
    );
    exportAndSetProblems();
    reloadAcl();

    loginAs(user);
    visibleResourceNames().then((visible) => {
      expect(visible).to.include(grantedHost);
      expect(visible).to.not.include(hiddenHost);
    });
  }
);

Given(
  'the centAcl cron is not running and ran less than two minutes ago',
  () => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildCentAclCronQuery()
    }).then(([rows]) => {
      expect(rows, 'centAcl cron entry').to.have.length(1);
      expect(String(rows[0].running)).to.eq('0');
      expect(Number(rows[0].now) - Number(rows[0].time_launch)).to.be.lessThan(
        120
      );
    });
  }
);

When(
  'the administrator adds {word} to the resources access {word} without reloading the ACL',
  (host: string, resourcesAccess: string) => {
    // Through the form: a CLAPI GRANT_HOST does not flag the access as changed
    cy.clearCookies();
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
    cy.visit(PAGES.configuration.aclResourcesAccessLegacy);
    cy.wait('@getTimeZone');

    cy.getIframeBody().contains('td.ListColLeft > a', resourcesAccess).click();
    cy.wait('@getTimeZone');

    cy.getIframeBody().find('#c2 a').click();
    cy.getIframeBody()
      .find('select[name="acl_hosts-f[]"]')
      .select(host)
      .closest('table')
      .find('input[name="add"]')
      .click();
    cy.getIframeBody().find('input[name="submitC"]').eq(0).click();
    cy.wait('@getTimeZone');
  }
);

Then(
  'the resources access {word} is flagged as changed',
  (resourcesAccess: string) => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildResourcesAccessChangedQuery(resourcesAccess)
    }).then(([rows]) => {
      expect(String(rows[0].changed)).to.eq('1');
    });
  }
);

Then(
  'within ninety seconds the cron has run again and the flag is cleared',
  () => {
    cy.waitUntil(
      () =>
        cy
          .requestOnDatabase({
            database: 'centreon',
            query: buildResourcesAccessChangedQuery(defaultResourcesAccess)
          })
          .then(([rows]) => String(rows[0].changed) === '0'),
      {
        errorMsg: 'The centAcl cron did not apply the change within 90 seconds',
        interval: pollingInterval,
        timeout: centAclCronTimeout
      }
    );
  }
);

Then(
  'the centreon_acl table and Resources Status show both hosts to {word}',
  (user: string) => {
    aclTableOf(defaultAclGroup).then((rows) => {
      expect(rows.map(({ host }) => host)).to.include.members(monitoredHosts);
    });

    loginAs(user);
    visibleResourceNames().then((visible) => {
      expect(visible).to.include.members(monitoredHosts);
    });
  }
);
