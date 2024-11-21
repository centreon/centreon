/* eslint-disable @typescript-eslint/no-namespace */

import 'cypress-wait-until';
import '@centreon/js-config/cypress/e2e/commands';
import { refreshButton } from '../features/Resources-status/common';
import { apiActionV1 } from '../commons';
import '../features/Dashboards/commands';
import metricsGraphWidget from '../fixtures/dashboards/creation/widgets/metricsGraphWidget.json';
import statusGridWidget from '../fixtures/dashboards/creation/widgets/status-grid-widget.json';

Cypress.Commands.add(
  'getByLabel',
  ({ tag = '', patternInfo, label }: GetByLabelProps): Cypress.Chainable => {
    return cy.get(`${tag}[aria-label${patternInfo || ''}="${label}"]`);
  }
);

Cypress.Commands.add(
  'getByTestId',
  ({ tag = '', patternInfo, testId }: GetByTestIdProps): Cypress.Chainable => {
    return cy.get(`${tag}[data-testid${patternInfo || ''}="${testId}"]`);
  }
);

Cypress.Commands.add('refreshListing', (): Cypress.Chainable => {
  return cy.get(refreshButton).click();
});

Cypress.Commands.add('disableListingAutoRefresh', (): Cypress.Chainable => {
  return cy.getByTestId({ testId: 'Disable autorefresh' }).click();
});

Cypress.Commands.add('removeResourceData', (): Cypress.Chainable => {
  return cy.executeActionViaClapi({
    bodyContent: {
      action: 'DEL',
      object: 'HOST',
      values: 'test_host'
    }
  });
});

Cypress.Commands.add(
  'setUserTokenApiV1',
  (fixtureFile = 'admin'): Cypress.Chainable => {
    return cy.fixture(`users/${fixtureFile}.json`).then((userAdmin) => {
      return cy
        .request({
          body: {
            password: userAdmin.password,
            username: userAdmin.login
          },
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          method: 'POST',
          url: `${apiActionV1}?action=authenticate`
        })
        .then(({ body }) =>
          window.localStorage.setItem('userTokenApiV1', body.authToken)
        );
    });
  }
);

Cypress.Commands.add('loginKeycloak', (jsonName: string): Cypress.Chainable => {
  cy.fixture(`users/${jsonName}.json`).then((credential) => {
    cy.get('#username').type(`{selectall}{backspace}${credential.login}`);
    cy.get('#password').type(`{selectall}{backspace}${credential.password}`);
  });

  return cy.get('#kc-login').click();
});

Cypress.Commands.add(
  'requestOnDatabase',
  ({ database, query }: requestOnDatabaseProps): void => {
    const command = `docker exec -i ${Cypress.env(
      'dockerName'
    )} mysql -ucentreon -pcentreon ${database} -e "${query}"`;

    cy.exec(command, { failOnNonZeroExit: true, log: true }).then(
      ({ code, stdout, stderr }) => {
        if (!stderr && code === 0) {
          cy.log('Request on database done');

          return cy.wrap(parseInt(stdout.split('\n')[1], 10) || true);
        }
        cy.log("Can't execute command on database : ", stderr);

        return cy.wrap(false);
      }
    );
  }
);

Cypress.Commands.add(
  'fetchHostData',
  ({ database, query }: requestOnDatabaseProps): void => {
    const command = `docker exec -i ${Cypress.env(
      'dockerName'
    )} mysql -ucentreon -pcentreon ${database} -e "${query}"`;

    cy.exec(command, { failOnNonZeroExit: true, log: true }).then(
      ({ code, stdout, stderr }) => {
        if (!stderr && code === 0) {
          cy.log('Request on database done');

          const outputLines = stdout.split('\n').filter(Boolean);
          cy.log('Output Lines:', outputLines);

          if (outputLines.length < 2) {
            return cy.wrap([]);
          }

          const columns = outputLines[0].split('\t');
          const rows = outputLines.slice(1).map((line) => {
            const values = line.split('\t');
            return columns.reduce((acc, col, idx) => {
              acc[col] = values[idx];
              return acc;
            }, {});
          });

          cy.log('Query Result:', JSON.stringify(rows));

          return cy.wrap(rows);
        }
        cy.log("Can't execute command on database : ", stderr);

        return cy.wrap(false);
      }
    );
  }
);

Cypress.Commands.add(
  'isInProfileMenu',
  (targetedMenu: string): Cypress.Chainable => {
    cy.get('header svg[aria-label="Profile"]').click();

    return cy.get('div[role="tooltip"]').contains(targetedMenu);
  }
);

Cypress.Commands.add('logout', (): void => {
  cy.getByLabel({ label: 'Profile' }).should('exist').click();

  cy.intercept({
    method: 'GET',
    times: 1,
    url: '/centreon/api/latest/authentication/logout'
  }).as('logout');

  cy.contains(/^Logout$/).click();

  cy.wait('@logout').its('response.statusCode').should('eq', 302);

  // https://github.com/cypress-io/cypress/issues/25841
  cy.clearAllCookies();
});

Cypress.Commands.add('logoutViaAPI', (): Cypress.Chainable => {
  return cy
    .request({
      method: 'GET',
      url: '/centreon/authentication/logout'
    })
    .visit('/')
    .getByLabel({ label: 'Alias', tag: 'input' });
});

Cypress.Commands.add('removeACL', (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'ACLMENU',
        values: 'acl_menu_test'
      }
    });
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'ACLGROUP',
        values: 'ACL Group test'
      }
    });
  });
});

Cypress.Commands.add('startOpenIdProviderContainer', (): Cypress.Chainable => {
  return cy
    .startContainer({
      image: `docker.centreon.com/centreon/keycloak:${Cypress.env(
        'OPENID_IMAGE_VERSION'
      )}`,
      name: 'e2e-tests-openid-centreon',
      portBindings: [
        {
          destination: 8080,
          source: 8080
        }
      ]
    })
    .then({ timeout: 30000 }, () => {
      return cy.task('waitOn', 'http://127.0.0.1:8080/health/ready');
    })
    .then(() => {
      cy.exec(
        'docker inspect -f "{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}" e2e-tests-openid-centreon'
      ).then(({ stdout }) => {
        cy.log(stdout);
      });
    });
});

Cypress.Commands.add('stopOpenIdProviderContainer', (): Cypress.Chainable => {
  return cy.stopContainer({ name: 'e2e-tests-openid-centreon' });
});

Cypress.Commands.add('executeSqlRequestInContainer', (request) => {
  return cy.exec(
    `docker exec ${Cypress.env(
      'dockerName'
    )} /bin/sh -c "mysql centreon -e \\"${request}\\""`
  );
});

Cypress.Commands.add(
  'insertDashboardWithMetricsGraphWidget',
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

Cypress.Commands.add(
  'insertDashboardWithSingleMetricWidget',
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

Cypress.Commands.add(
  'insertDashboardWithStatusGridWidget',
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

Cypress.Commands.add('enableDashboardFeature', () => {
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
});

export enum PatternType {
  contains = '*',
  endsWith = '$',
  startsWith = '^'
}
interface Dashboard {
  description?: string;
  name: string;
}

interface GetByLabelProps {
  label: string;
  patternInfo?: PatternType;
  tag?: string;
}

interface GetByTestIdProps {
  patternInfo?: PatternType;
  tag?: string;
  testId: string;
}

interface requestOnDatabaseProps {
  database: string;
  query: string;
}

type metricsGraphWidgetJSONData = typeof metricsGraphWidget;
type statusGridWidget = typeof statusGridWidget;

declare global {
  namespace Cypress {
    interface Chainable {
      disableListingAutoRefresh: () => Cypress.Chainable;
      executeSqlRequestInContainer: (request: string) => Cypress.Chainable;
      getByLabel: ({
        patternInfo,
        tag,
        label
      }: GetByLabelProps) => Cypress.Chainable;
      getByTestId: ({
        patternInfo,
        tag,
        testId
      }: GetByTestIdProps) => Cypress.Chainable;
      insertDashboardWithMetricsGraphWidget: (
        dashboard: Dashboard,
        patch: string
      ) => Cypress.Chainable;
      insertDashboardWithSingleMetricWidget: (
        dashboard: Dashboard,
        patch: string
      ) => Cypress.Chainable;
      insertDashboardWithStatusGridWidget: (
        dashboard: Dashboard,
        patch: statusGridWidget
      ) => Cypress.Chainable;
      isInProfileMenu: (targetedMenu: string) => Cypress.Chainable;
      loginKeycloak: (jsonName: string) => Cypress.Chainable;
      logout: () => void;
      logoutViaAPI: () => Cypress.Chainable;
      refreshListing: () => Cypress.Chainable;
      removeACL: () => Cypress.Chainable;
      removeResourceData: () => Cypress.Chainable;
      requestOnDatabase: ({
        database,
        query
      }: requestOnDatabaseProps) => Cypress.Chainable;
      fetchHostData: ({
        database,
        query
      }: requestOnDatabaseProps) => Cypress.Chainable;
      setUserTokenApiV1: (fixtureFile?: string) => Cypress.Chainable;
      startOpenIdProviderContainer: () => Cypress.Chainable;
      stopOpenIdProviderContainer: () => Cypress.Chainable;
      enableDashboardFeature: () => Cypress.Chainable;
    }
  }
}
