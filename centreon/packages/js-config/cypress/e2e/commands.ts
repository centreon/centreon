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

interface CopyFromContainerProps {
  destination: string;
  name?: string;
  source: string;
}

Cypress.Commands.add(
  'copyFromContainer',
  (
    {
      name = Cypress.env('dockerName'),
      source,
      destination
    }: CopyFromContainerProps,
    options?: Partial<Cypress.ExecOptions>
  ) => {
    return cy.exec(`docker cp ${name}:${source} "${destination}"`, options);
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
    {
      name = Cypress.env('dockerName'),
      source,
      destination
    }: CopyToContainerProps,
    options?: Partial<Cypress.ExecOptions>
  ) => {
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

    return cy
      .get('.SnackbarContent-root > .MuiPaper-root')
      .then(($snackbar) => {
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
  command: string;
  name: string;
}

Cypress.Commands.add(
  'execInContainer',
  ({ command, name }: ExecInContainerProps): Cypress.Chainable => {
    return cy
      .exec(`docker exec -i ${name} ${command}`, { failOnNonZeroExit: false })
      .then((result) => {
        if (result.code) {
          // output will not be truncated
          throw new Error(`
            Execution of "${command}" failed
            Exit code: ${result.code}
            Stdout:\n${result.stdout}
            Stderr:\n${result.stderr}`);
        }

        return cy.wrap(result);
      });
  }
);

interface PortBinding {
  destination: number;
  source: number;
}

interface StartContainerProps {
  image: string;
  name: string;
  portBindings: Array<PortBinding>;
}

Cypress.Commands.add(
  'startContainer',
  ({ name, image, portBindings }: StartContainerProps): Cypress.Chainable => {
    cy.log(`Starting container ${name} from image ${image}`);

    return cy.task(
      'startContainer',
      { image, name, portBindings },
      { timeout: 600000 } // 10 minutes because docker pull can be very slow
    );
  }
);

Cypress.Commands.add(
  'createDirectory',
  (directoryPath: string): Cypress.Chainable => {
    return cy.task('createDirectory', directoryPath);
  }
);

interface StartWebContainerProps {
  name?: string;
  os?: string;
  useSlim?: boolean;
  version?: string;
}

Cypress.Commands.add(
  'startWebContainer',
  ({
    name = Cypress.env('dockerName'),
    os = Cypress.env('WEB_IMAGE_OS'),
    useSlim = true,
    version = Cypress.env('WEB_IMAGE_VERSION')
  }: StartWebContainerProps = {}): Cypress.Chainable => {
    const slimSuffix = useSlim ? '-slim' : '';

    const image = `docker.centreon.com/centreon/centreon-web${slimSuffix}-${os}:${version}`;

    return cy
      .startContainer({
        image,
        name,
        portBindings: [{ destination: 4000, source: 80 }]
      })
      .then(() => {
        const baseUrl = 'http://127.0.0.1:4000';

        Cypress.config('baseUrl', baseUrl);

        return cy.task(
          'waitOn',
          `${baseUrl}/centreon/api/latest/platform/installation/status`
        );
      })
      .visit('/') // this is necessary to refresh browser cause baseUrl has changed (flash appears in video)
      .setUserTokenApiV1();
  }
);

interface StopWebContainerProps {
  name?: string;
}

Cypress.Commands.add(
  'stopWebContainer',
  ({
    name = Cypress.env('dockerName')
  }: StopWebContainerProps = {}): Cypress.Chainable => {
    const logDirectory = `results/logs/${Cypress.spec.name.replace(
      artifactIllegalCharactersMatcher,
      '_'
    )}/${Cypress.currentTest.title.replace(
      artifactIllegalCharactersMatcher,
      '_'
    )}`;

    return cy
      .visitEmptyPage()
      .createDirectory(logDirectory)
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
      .exec(`chmod -R 755 "${logDirectory}"`)
      .stopContainer({ name });
  }
);

interface StopContainerProps {
  name: string;
}

Cypress.Commands.add(
  'stopContainer',
  ({ name }: StopContainerProps): Cypress.Chainable => {
    cy.log(`Stopping container ${name}`);

    cy.exec(`docker logs ${name}`).then(({ stdout }) => {
      cy.writeFile(
        `cypress/results/logs/${Cypress.spec.name.replace(
          artifactIllegalCharactersMatcher,
          '_'
        )}/${Cypress.currentTest.title.replace(
          artifactIllegalCharactersMatcher,
          '_'
        )}/container-${name}.log`,
        stdout
      );
    });

    return cy.task('stopContainer', { name });
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

declare global {
  namespace Cypress {
    interface Chainable {
      clickSubRootMenuItem: (page: string) => Cypress.Chainable;
      loginAsAdminViaApiV2: () => Cypress.Chainable;
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
      getIframeBody: () => Cypress.Chainable;
      getTimeFromHeader: () => Cypress.Chainable;
      getWebVersion: () => Cypress.Chainable;
      hoverRootMenuItem: (rootItemNumber: number) => Cypress.Chainable;
      insertDashboard: (dashboard: Dashboard) => Cypress.Chainable;
      insertDashboardList: (fixtureFile: string) => Cypress.Chainable;
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
      shareDashboardToUser: ({
        dashboardName,
        userName,
        role
      }: ShareDashboardToUserProps) => Cypress.Chainable;
      startContainer: ({
        name,
        image
      }: StartContainerProps) => Cypress.Chainable;
      startWebContainer: ({
        name,
        os,
        useSlim,
        version
      }?: StartWebContainerProps) => Cypress.Chainable;
      stopContainer: ({ name }: StopContainerProps) => Cypress.Chainable;
      stopWebContainer: ({ name }?: StopWebContainerProps) => Cypress.Chainable;
      visitEmptyPage: () => Cypress.Chainable;
      waitForContainerAndSetToken: () => Cypress.Chainable;
    }
  }
}

export {};
