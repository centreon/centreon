/* eslint-disable @typescript-eslint/no-namespace */

import './commands/configuration';
import './commands/monitoring';

import installLogsCollector from 'cypress-terminal-report/src/installLogsCollector';

installLogsCollector({ enableExtendedCollector: true });

const apiLoginV2 = '/centreon/authentication/providers/configurations/local';

const artifactIllegalCharactersMatcher = /[,\s/|<>*?:"]/g;

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
  ({ rootItemNumber, subMenu, page }): void => {
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

interface CopyToContainerProps {
  destination: string;
  name?: string;
  source: string;
}

Cypress.Commands.add(
  'copyToContainer',
  (
    { name = 'web', source, destination }: CopyToContainerProps,
    options?: Partial<Cypress.ExecOptions>
  ) => {
    cy.log(`Copy content from ${source} to ${name}:${destination}`);

    return cy.exec(`docker cp ${source} ${name}:${destination}`, options);
  }
);

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
      }
    });
  }
);

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

Cypress.Commands.add('waitForContainerAndSetToken', (): Cypress.Chainable => {
  return cy.setUserTokenApiV1();
});

interface ExecInContainerProps {
  command: string | Array<string>;
  name: string;
}

interface ExecInContainerResult {
  exitCode: number;
  output: string;
}

Cypress.Commands.add(
  'execInContainer',
  ({ command, name }: ExecInContainerProps): Cypress.Chainable => {
    const commands =
      typeof command === 'string' || command instanceof String
        ? [command]
        : command;

    const results = commands.reduce(
      (acc, runCommand) => {
        cy.task<ExecInContainerResult>(
          'execInContainer',
          { command: runCommand, name },
          { timeout: 600000 }
        ).then((result) => {
          if (result.exitCode) {
            // output will not be truncated
            throw new Error(`
Execution of "${runCommand}" failed
Exit code: ${result.exitCode}
Output:\n${result.output}`);
          }

          acc.output = `${acc.output}${result.output}`;
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
  databaseImage?: string;
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
    databaseImage = Cypress.env('DATABASE_IMAGE'),
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

    const slimSuffix = useSlim ? '-slim' : '';

    const webImage = `docker.centreon.com/centreon/centreon-web${slimSuffix}-${webOs}:${webVersion}`;

    return cy
      .task(
        'startContainers',
        { databaseImage, openidImage, profiles, samlImage, webImage },
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

Cypress.Commands.add('stopContainers', (): Cypress.Chainable => {
  cy.log('Stopping containers ...');

  const logDirectory = `results/logs/${Cypress.spec.name.replace(
    artifactIllegalCharactersMatcher,
    '_'
  )}/${Cypress.currentTest.title.replace(
    artifactIllegalCharactersMatcher,
    '_'
  )}`;

  const name = 'web';

  return cy
    .visitEmptyPage()
    .createDirectory(logDirectory)
    .getContainersLogs()
    .then((containersLogs: Array<Array<string>>) => {
      Object.entries(containersLogs).forEach(([containerName, logs]) => {
        cy.writeFile(
          `results/logs/${Cypress.spec.name.replace(
            artifactIllegalCharactersMatcher,
            '_'
          )}/${Cypress.currentTest.title.replace(
            artifactIllegalCharactersMatcher,
            '_'
          )}/container-${containerName}.log`,
          logs
        );
      });
    })
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
    .then(() => {
      if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
        return cy.copyFromContainer({
          destination: `${logDirectory}/php`,
          name,
          source: '/var/log/php-fpm'
        });
      }

      return cy.copyFromContainer(
        {
          destination: `${logDirectory}/php8.1-fpm-centreon-error.log`,
          name,
          source: '/var/log/php8.1-fpm-centreon-error.log'
        },
        { failOnNonZeroExit: false }
      );
    })
    .then(() => {
      if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
        return cy.copyFromContainer({
          destination: `${logDirectory}/httpd`,
          name,
          source: '/var/log/httpd'
        });
      }

      return cy.copyFromContainer(
        {
          destination: `${logDirectory}/apache2`,
          name,
          source: '/var/log/apache2'
        },
        { failOnNonZeroExit: false }
      );
    })
    .exec(`chmod -R 755 "${logDirectory}"`)
    .task(
      'stopContainers',
      {},
      { timeout: 600000 } // 10 minutes because docker pull can be very slow
    );
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

interface PatchDashboardBody {
  panels: Array<{
    layout: {
      height: number;
      min_height: number;
      min_width: number;
      width: number;
      x: number;
      y: number;
    };
    name: string;
    widget_settings: {
      options: {
        description: {
          content: string;
          enabled: boolean;
        };
        name: string;
      };
    };
    widget_type: string;
  }>;
}

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
    .get('header div[data-cy="clock"]', { timeout: 10000 })
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
      createDirectory: (directoryPath: string) => Cypress.Chainable;
      execInContainer: ({
        command,
        name
      }: ExecInContainerProps) => Cypress.Chainable;
      getContainerId: (containerName: string) => Cypress.Chainable;
      getContainerIpAddress: (containerName: string) => Cypress.Chainable;
      getContainersLogs: () => Cypress.Chainable;
      getIframeBody: () => Cypress.Chainable;
      getTimeFromHeader: () => Cypress.Chainable;
      getWebVersion: () => Cypress.Chainable;
      hoverRootMenuItem: (rootItemNumber: number) => Cypress.Chainable;
      insertDashboard: (dashboard: Dashboard) => Cypress.Chainable;
      insertDashboardList: (fixtureFile: string) => Cypress.Chainable;
      insertDashboardWithWidget: (
        dashboard: Dashboard,
        patch: PatchDashboardBody
      ) => Cypress.Chainable;
      loginByTypeOfUser: ({
        jsonName,
        loginViaApi
      }: LoginByTypeOfUserProps) => Cypress.Chainable;
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
        databaseImage,
        openidImage,
        profiles,
        useSlim,
        webOs,
        webVersion
      }?: StartContainersProps) => Cypress.Chainable;
      stopContainer: ({ name }: StopContainerProps) => Cypress.Chainable;
      stopContainers: () => Cypress.Chainable;
      visitEmptyPage: () => Cypress.Chainable;
      waitForContainerAndSetToken: () => Cypress.Chainable;
    }
  }
}

export {};
