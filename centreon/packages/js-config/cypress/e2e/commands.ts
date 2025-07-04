/* eslint-disable @typescript-eslint/no-namespace */
import path from 'path';
import 'cypress-wait-until';

import './commands/configuration';
import './commands/monitoring';

import installLogsCollector from 'cypress-terminal-report/src/installLogsCollector';

installLogsCollector({
  commandTimings: 'seconds',
  enableExtendedCollector: true
});

const apiBase = '/centreon/api';
const apiActionV1 = `${apiBase}/index.php`;
const apiLoginV2 = '/centreon/authentication/providers/configurations/local';

const artifactIllegalCharactersMatcher = /[,\s/|<>*?:"]/g;

export enum PatternType {
  contains = '*',
  endsWith = '$',
  equals = '',
  startsWith = '^'
}

interface GetByLabelProps {
  label: string;
  patternType?: PatternType;
  tag?: string;
}

Cypress.Commands.add(
  'getByLabel',
  ({
    tag = '',
    patternType = PatternType.equals,
    label
  }: GetByLabelProps): Cypress.Chainable => {
    return cy.get(`${tag}[aria-label${patternType}="${label}"]`);
  }
);

interface GetByTestIdProps {
  patternType?: PatternType;
  tag?: string;
  testId: string;
}

Cypress.Commands.add(
  'getByTestId',
  ({
    tag = '',
    patternType = PatternType.equals,
    testId
  }: GetByTestIdProps): Cypress.Chainable => {
    return cy.get(`${tag}[data-testid${patternType}="${testId}"]`);
  }
);

Cypress.Commands.add('getWebVersion', (): Cypress.Chainable => {
  return cy
    .exec(
      `bash -c "grep version ../../www/install/insertBaseConf.sql | cut -d \\' -f 4 | awk 'NR==2'"`
    )
    .then(({ stdout }) => {
      const found = stdout.match(/(\d+\.\d+)\.(\d+)/);
      if (found) {
        return cy.wrap({ major_version: found[1], minor_version: found[2] });
      }

      throw new Error('Current web version cannot be parsed.');
    });
});

Cypress.Commands.add('getIframeBody', (): Cypress.Chainable => {
  return cy
    .get('iframe#main-content', { timeout: 10000 })
    .its('0.contentDocument.body')
    .should('not.be.empty')
    .then(cy.wrap);
});

Cypress.Commands.add(
  'hoverRootMenuItem',
  (rootItemNumber: number): Cypress.Chainable => {
    return cy
      .get('div[data-testid="sidebar"] li')
      .eq(rootItemNumber)
      .trigger('mouseover');
  }
);

Cypress.Commands.add(
  'clickSubRootMenuItem',
  (page: string): Cypress.Chainable => {
    return cy.get('div[data-cy="collapse"]').eq(1).contains(page).click();
  }
);

interface NavigateToProps {
  page: string;
  rootItemNumber: number;
  subMenu?: string;
}

Cypress.Commands.add(
  'navigateTo',
  ({ rootItemNumber, subMenu, page }: NavigateToProps): void => {
    if (subMenu) {
      cy.hoverRootMenuItem(rootItemNumber)
        .contains(subMenu)
        .trigger('mouseover')
        .get('.MuiCollapse-wrapper')
        .find('div[data-cy="collapse"]')
        .should('be.visible')
        .and('contain', page);

      cy.clickSubRootMenuItem(page);

      return;
    }
    cy.hoverRootMenuItem(rootItemNumber).contains(page).click({ force: true });
  }
);

Cypress.Commands.add(
  'moveSortableElement',
  {
    prevSubject: 'element'
  },
  (subject, direction): void => {
    const key = `{${direction}arrow}`;

    cy.wrap(subject)
      .type(' ', {
        force: true,
        scrollBehavior: false
      })
      .closest('body')
      .type(key, {
        scrollBehavior: false
      })
      .type(' ', {
        scrollBehavior: false
      });
  }
);

Cypress.Commands.add('getContainerId', (containerName: string) => {
  cy.log(`Getting container id of ${containerName}`);

  return cy.task('getContainerId', containerName);
});

Cypress.Commands.add('getContainerIpAddress', (containerName: string) => {
  cy.log(`Getting container ip address of ${containerName}`);

  return cy.task('getContainerIpAddress', containerName);
});

Cypress.Commands.add('getContainersLogs', () => {
  cy.log('Getting containers logs');

  return cy.task('getContainersLogs');
});

Cypress.Commands.add('getContainerMappedPort', (containerName: string, containerPort: number) => {
  cy.log(`Getting mapped port ${containerPort} of container ${containerName}`);

  return cy.task('getContainerMappedPort', { containerName, containerPort });
});

interface CopyFromContainerProps {
  destination: string;
  name?: string;
  source: string;
}

Cypress.Commands.add(
  'copyFromContainer',
  ({ name = 'web', source, destination }: CopyFromContainerProps) => {
    cy.log(`Copy content from ${name}:${source} to ${destination}`);

    return cy.task('copyFromContainer', {
      destination,
      serviceName: name,
      source
    });
  }
);

export enum CopyToContainerContentType {
  Directory = 'directory',
  File = 'file'
}

interface CopyToContainerProps {
  destination: string;
  name?: string;
  source: string;
  type: CopyToContainerContentType;
}

Cypress.Commands.add(
  'copyToContainer',
  ({ name = 'web', source, destination, type }: CopyToContainerProps) => {
    cy.log(`Copy content from ${source} to ${name}:${destination}`);

    return cy.task('copyToContainer', {
      destination,
      serviceName: name,
      source,
      type
    });
  }
);

Cypress.Commands.add('loginAsAdminViaApiV2', (): Cypress.Chainable => {
  return cy.request({
    body: {
      login: 'admin',
      password: 'Centreon!2021'
    },
    method: 'POST',
    url: apiLoginV2
  });
});

interface LoginByTypeOfUserProps {
  jsonName?: string;
  loginViaApi?: boolean;
}

Cypress.Commands.add(
  'loginByTypeOfUser',
  ({ jsonName = 'admin', loginViaApi = false }): Cypress.Chainable => {
    if (loginViaApi) {
      return cy
        .fixture(`users/${jsonName}.json`)
        .then((user) => {
          return cy.request({
            body: {
              login: user.login,
              password: user.password
            },
            method: 'POST',
            url: apiLoginV2
          });
        })
        .visit(`${Cypress.config().baseUrl}`)
        .wait('@getNavigationList');
    }

    cy.visit(`${Cypress.config().baseUrl}`)
      .fixture(`users/${jsonName}.json`)
      .then((credential) => {
        cy.getByLabel({ label: 'Alias', tag: 'input' }).type(
          `{selectAll}{backspace}${credential.login}`
        );
        cy.getByLabel({ label: 'Password', tag: 'input' }).type(
          `{selectAll}{backspace}${credential.password}`
        );
      })
      .getByLabel({ label: 'Connect', tag: 'button' })
      .click();

    return cy.get('.MuiAlert-message').then(($snackbar) => {
      if ($snackbar.text().includes('Login succeeded')) {
        cy.wait('@getNavigationList');
        cy.get('.MuiAlert-message').should('not.be.visible');
      }
    });
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

  cy.waitUntil(() =>
    cy.wait('@logout').then((interception) => {
      return interception?.response?.statusCode === 302;
    }),
    {
      errorMsg: 'Logout did not complete successfully',
      timeout: 30000,
      interval: 2000
    }
  );

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

Cypress.Commands.add(
  'visitEmptyPage',
  (): Cypress.Chainable =>
    cy
      .intercept('/waiting-page', {
        headers: { 'content-type': 'text/html' },
        statusCode: 200
      })
      .visit('/waiting-page')
);

interface ExecInContainerProps {
  command: string | Array<string>;
  name: string;
}

interface ExecInContainerOptions {
  log: boolean;
}

interface ExecInContainerResult {
  exitCode: number;
  output: string;
}

Cypress.Commands.add(
  'execInContainer',
  ({ command, name }, { log = true } = { log: true }): Cypress.Chainable => {
    const commands =
      typeof command === 'string' || command instanceof String
        ? [command]
        : command;

    const results = commands.reduce(
      (acc, runCommand) => {
        cy.task<ExecInContainerResult>(
          'execInContainer',
          { command: runCommand, name },
          { log, timeout: 600000 }
        ).then((result) => {
          const displayedOutput = log ? result.output : 'hidden command output';
          const displayedRunCommand = log ? runCommand : 'hidden run command';

          if (result.exitCode) {
            cy.log(displayedOutput);

            // output will not be truncated
            throw new Error(`
Execution of "${displayedRunCommand}" failed
Exit code: ${result.exitCode}
Output:\n${displayedOutput}`);
          }

          acc.output = `${acc.output}${displayedOutput}`;
        });

        return acc;
      },
      { exitCode: 0, output: '' }
    );

    return cy.wrap(results);
  }
);

interface RequestOnDatabaseProps {
  database: string;
  query: string;
}

Cypress.Commands.add(
  'requestOnDatabase',
  ({ database, query }: RequestOnDatabaseProps): Cypress.Chainable => {
    return cy.task('requestOnDatabase', { database, query });
  }
);

interface SetUserTokenApiV1Props {
  login?: string;
  password?: string;
}

Cypress.Commands.add(
  'setUserTokenApiV1',
  ({
    login = 'admin',
    password = 'Centreon!2021'
  }: SetUserTokenApiV1Props = {}): Cypress.Chainable => {
    return cy
      .request({
        body: {
          password,
          username: login
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
  }
);

interface PortBinding {
  destination: number;
  source: number;
}

interface StartContainerProps {
  command?: string;
  image: string;
  name: string;
  portBindings: Array<PortBinding>;
}

Cypress.Commands.add(
  'startContainer',
  ({
    command,
    name,
    image,
    portBindings
  }: StartContainerProps): Cypress.Chainable => {
    cy.log(`Starting container ${name} from image ${image}`);

    return cy.task(
      'startContainer',
      { command, image, name, portBindings },
      { timeout: 600000 } // 10 minutes because docker pull can be very slow
    );
  }
);

interface StartContainersProps {
  composeFile?: string;
  databaseImage?: string;
  moduleName?: string;
  openidImage?: string;
  profiles?: Array<string>;
  samlImage?: string;
  useSlim?: boolean;
  webOs?: string;
  webVersion?: string;
}

Cypress.Commands.add(
  'startContainers',
  ({
    composeFile,
    databaseImage = Cypress.env('DATABASE_IMAGE'),
    moduleName = 'centreon-web',
    openidImage = `docker.centreon.com/centreon/keycloak:${Cypress.env(
      'OPENID_IMAGE_VERSION'
    )}`,
    profiles = [],
    samlImage = `docker.centreon.com/centreon/keycloak:${Cypress.env(
      'SAML_IMAGE_VERSION'
    )}`,
    useSlim = true,
    webOs = Cypress.env('WEB_IMAGE_OS'),
    webVersion = Cypress.env('WEB_IMAGE_VERSION')
  }: StartContainersProps = {}): Cypress.Chainable => {
    cy.log('Starting containers ...');

    let composeFilePath = composeFile;
    if (!composeFile) {
      const cypressDir = path.dirname(Cypress.config('configFile'));
      composeFilePath = `${cypressDir}/../../../.github/docker/docker-compose.yml`;
    }

    const slimSuffix = useSlim ? '-slim' : '';

    const webImage = `docker.centreon.com/centreon/${moduleName}${slimSuffix}-${webOs}:${webVersion}`;

    return cy
      .task(
        'startContainers',
        {
          composeFile: composeFilePath,
          databaseImage,
          openidImage,
          profiles,
          samlImage,
          webImage
        },
        { timeout: 600000 } // 10 minutes because docker pull can be very slow
      )
      .then(() => {
        const baseUrl = 'http://127.0.0.1:4000';

        Cypress.config('baseUrl', baseUrl);

        return cy.wrap(null);
      })
      .visit('/') // this is necessary to refresh browser cause baseUrl has changed (flash appears in video)
      .setUserTokenApiV1();
  }
);

interface StopContainerProps {
  name: string;
}

Cypress.Commands.add(
  'stopContainer',
  ({ name }: StopContainerProps): Cypress.Chainable => {
    cy.log(`Stopping container ${name}`);

    return cy.task('stopContainer', { name });
  }
);

Cypress.Commands.add(
  'getLogDirectory',
  (): Cypress.Chainable => {
    const logDirectory = `results/logs/${Cypress.spec.name.replace(
        artifactIllegalCharactersMatcher,
        '_'
      )}/${Cypress.currentTest.title.replace(
        artifactIllegalCharactersMatcher,
        '_'
      )}`;

    return cy
      .createDirectory(logDirectory)
      .exec(`chmod -R 755 "${logDirectory}"`)
      .wrap(logDirectory);
  }
);

interface CopyWebContainerLogsProps {
  name: string;
}

Cypress.Commands.add(
  'copyWebContainerLogs',
  ({ name }: CopyWebContainerLogsProps): Cypress.Chainable => {
    cy.log(`Getting logs from container ${name} ...`);

    return cy.getLogDirectory().then((logDirectory) => {
      let sourcePhpLogs = '/var/log/php8.1-fpm-centreon-error.log';
      let targetPhpLogs = `${logDirectory}/php8.1-fpm-centreon-error.log`;
      let sourceApacheLogs = '/var/log/apache2';
      let targetApacheLogs = `${logDirectory}/apache2`;
      if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
        sourcePhpLogs = '/var/log/php-fpm';
        targetPhpLogs = `${logDirectory}/php`;
        sourceApacheLogs = '/var/log/httpd';
        targetApacheLogs = `${logDirectory}/httpd`;
      }

      return cy
        .copyFromContainer({
          destination: `${logDirectory}/broker`,
          name,
          source: '/var/log/centreon-broker'
        })
        .copyFromContainer({
          destination: `${logDirectory}/engine`,
          name,
          source: '/var/log/centreon-engine'
        })
        .copyFromContainer({
          destination: `${logDirectory}/centreon`,
          name,
          source: '/var/log/centreon'
        })
        .copyFromContainer({
          destination: `${logDirectory}/centreon-gorgone`,
          name,
          source: '/var/log/centreon-gorgone'
        })
        .copyFromContainer({
          destination: targetPhpLogs,
          name,
          source: sourcePhpLogs,
        })
        .copyFromContainer({
          destination: targetApacheLogs,
          name,
          source: sourceApacheLogs,
        })
        .exec(`chmod -R 755 "${logDirectory}"`);
      });
});

Cypress.Commands.add('stopContainers', (): Cypress.Chainable => {
  cy.log('Stopping containers ...');

  const name = 'web';

  return cy
    .visitEmptyPage()
    .getLogDirectory()
    .then((logDirectory) => {
      return cy
        .getContainersLogs()
        .then((containersLogs: Array<Array<string>>) => {
          if (!containersLogs) {
            return;
          }

          Object.entries(containersLogs).forEach(([containerName, logs]) => {
            cy.writeFile(
              `${logDirectory}/container-${containerName}.log`,
              logs
            );
          });
        })
        .copyWebContainerLogs({ name })
        .exec(`chmod -R 755 "${logDirectory}"`)
        .task(
          'stopContainers',
          {},
          { timeout: 600000 } // 10 minutes because docker pull can be very slow
        );
    });
});

Cypress.Commands.add(
  'createDirectory',
  (directoryPath: string): Cypress.Chainable => {
    return cy.task('createDirectory', directoryPath);
  }
);

interface Dashboard {
  description?: string;
  name: string;
}

Cypress.Commands.add(
  'insertDashboardList',
  (fixtureFile: string): Cypress.Chainable => {
    return cy.fixture(fixtureFile).then((dashboardList) => {
      cy.wrap(
        Promise.all(
          dashboardList.map((dashboardBody: Dashboard) =>
            cy.insertDashboard({ ...dashboardBody })
          )
        )
      );
    });
  }
);

Cypress.Commands.add(
  'insertDashboard',
  (dashboardBody: Dashboard): Cypress.Chainable => {
    return cy.request({
      body: {
        ...dashboardBody
      },
      method: 'POST',
      url: '/centreon/api/latest/configuration/dashboards'
    });
  }
);

Cypress.Commands.add('insertDashboardWithWidget', (dashboardBody, patchBody, widgetName, widgetType) => {
  cy.request({
    body: { ...dashboardBody },
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).then((response) => {
    const dashboardId = response.body.id;

    cy.waitUntil(
      () => {
        return cy.request({
          method: 'GET',
          url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`
        }).then((getResponse) => {
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
      method: 'POST',
      url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`,
      body: formData,
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    }).then((patchResponse) => {
      console.log('Widget added successfully:', patchResponse);
    });
  });
});

interface ShareDashboardToUserProps {
  dashboardName: string;
  role: string;
  userName: string;
}

interface ListingRequestResult {
  body: {
    result: Array<{
      id: number;
    }>;
  };
}

type PatchDashboardBody = {
  widget_settings: {
      data: {
          resources: Array<{
              resourceType: string;
              resources: Array<{
                  id: number;
                  name: string;
              }>;
          }>;
          metrics: Array<{
              criticalHighThreshold: number;
              criticalLowThreshold: number;
              id: number;
              name: string;
              unit: string;
              warningHighThreshold: number;
              warningLowThreshold: number;
          }>;
      };
      options: {
          timeperiod: {
              start: any;
              end: any;
              timePeriodType: number;
          };
          threshold: {
              enabled: boolean;
              customCritical: any;
              criticalType: string;
              customWarning: any;
              warningType: string;
          };
          refreshInterval: string;
          curveType: string;
          description: {
              content: string;
              enabled: boolean;
          };
      };
  };
};

Cypress.Commands.add(
  'shareDashboardToUser',
  ({ dashboardName, userName, role }: ShareDashboardToUserProps): void => {
    Promise.all([
      cy.request({
        method: 'GET',
        url: `/centreon/api/latest/configuration/users?search={"name":"${userName}"}`
      }),
      cy.request({
        method: 'GET',
        url: `/centreon/api/latest/configuration/dashboards?search={"name":"${dashboardName}"}`
      })
    ]).then(
      ([retrievedUser, retrievedDashboard]: [
        ListingRequestResult,
        ListingRequestResult
      ]) => {
        const userId = retrievedUser.body.result[0].id;
        const dashboardId = retrievedDashboard.body.result[0].id;

        cy.request({
          body: {
            id: userId,
            role: `${role}`
          },
          method: 'POST',
          url: `/centreon/api/latest/configuration/dashboards/${dashboardId}/access_rights/contacts`
        });
      }
    );
  }
);

Cypress.Commands.add('getTimeFromHeader', (): Cypress.Chainable => {
  return cy
    .get('header div[data-cy="clock"]', { timeout: 20000 })
    .should('be.visible')
    .then(($time) => {
      const headerTime = $time.children()[1].textContent;
      if (headerTime?.match(/\d+:\d+/)) {
        cy.log(`header time is : ${headerTime}`);

        return cy.wrap(headerTime);
      }

      throw new Error(`header time is not displayed`);
    });
});

Cypress.Commands.add('insertDashboardWithDoubleWidget', (dashboardBody, patchBody1, patchBody2, widgetName, widgetType) => {
  cy.request({
    body: { ...dashboardBody },
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).then((response) => {
    const dashboardId = response.body.id;

    cy.waitUntil(
      () => {
        return cy.request({
          method: 'GET',
          url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`
        }).then((getResponse) => {
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
      method: 'POST',
      url: `/centreon/api/latest/configuration/dashboards/${dashboardId}`,
      body: formData,
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    }).then((patchResponse) => {
      console.log('Widget added successfully:', patchResponse);
    });
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      clickSubRootMenuItem: (page: string) => Cypress.Chainable;
      copyFromContainer: (
        props: CopyFromContainerProps,
        options?: Partial<Cypress.ExecOptions>
      ) => Cypress.Chainable;
      copyToContainer: (
        props: CopyToContainerProps,
        options?: Partial<Cypress.ExecOptions>
      ) => Cypress.Chainable;
      copyWebContainerLogs: (props: CopyWebContainerLogsProps) => Cypress.Chainable;
      createDirectory: (directoryPath: string) => Cypress.Chainable;
      execInContainer: (
        props: ExecInContainerProps,
        options?: ExecInContainerOptions
      ) => Cypress.Chainable;
      getByLabel: ({
        patternType,
        tag,
        label
      }: GetByLabelProps) => Cypress.Chainable;
      getByTestId: ({
        patternType,
        tag,
        testId
      }: GetByTestIdProps) => Cypress.Chainable;
      getContainerId: (containerName: string) => Cypress.Chainable;
      getContainerIpAddress: (containerName: string) => Cypress.Chainable;
      getContainersLogs: () => Cypress.Chainable;
      getContainerMappedPort: (containerName: string, containerPort: number) => Cypress.Chainable;
      getIframeBody: () => Cypress.Chainable;
      getLogDirectory: () => Cypress.Chainable;
      getTimeFromHeader: () => Cypress.Chainable;
      getWebVersion: () => Cypress.Chainable;
      hoverRootMenuItem: (rootItemNumber: number) => Cypress.Chainable;
      insertDashboard: (dashboard: Dashboard) => Cypress.Chainable;
      insertDashboardList: (fixtureFile: string) => Cypress.Chainable;
      insertDashboardWithWidget: (
        dashboard: Dashboard,
        patchBody:  Record<string, any>,
        widgetName:string,
        widgetType:string
      ) => Cypress.Chainable;
      insertDashboardWithDoubleWidget: (
        dashboard: Dashboard,
        patchBody1: Record<string, any>,
        patchBody2: Record<string, any>,
        widgetName: string,
        widgetType: string
      ) => Cypress.Chainable;
      loginAsAdminViaApiV2: () => Cypress.Chainable;
      loginByTypeOfUser: ({
        jsonName,
        loginViaApi
      }: LoginByTypeOfUserProps) => Cypress.Chainable;
      logout: () => void;
      logoutViaAPI: () => Cypress.Chainable;
      moveSortableElement: (direction: string) => Cypress.Chainable;
      navigateTo: ({
        page,
        rootItemNumber,
        subMenu
      }: NavigateToProps) => Cypress.Chainable;
      requestOnDatabase: ({
        database,
        query
      }: RequestOnDatabaseProps) => Cypress.Chainable;
      setUserTokenApiV1: ({
        login,
        password
      }?: SetUserTokenApiV1Props) => Cypress.Chainable;
      shareDashboardToUser: ({
        dashboardName,
        userName,
        role
      }: ShareDashboardToUserProps) => Cypress.Chainable;
      startContainer: ({
        command,
        name,
        image,
        portBindings
      }: StartContainerProps) => Cypress.Chainable;
      startContainers: ({
        composeFile,
        databaseImage,
        moduleName,
        openidImage,
        profiles,
        useSlim,
        webOs,
        webVersion
      }?: StartContainersProps) => Cypress.Chainable;
      stopContainer: ({ name }: StopContainerProps) => Cypress.Chainable;
      stopContainers: () => Cypress.Chainable;
      visitEmptyPage: () => Cypress.Chainable;
    }
  }
}

export {};
