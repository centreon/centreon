/* eslint-disable @typescript-eslint/no-namespace */

import './commands/configuration';

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
  ({
    name = Cypress.env('dockerName'),
    source,
    destination
  }: CopyFromContainerProps) => {
    return cy.exec(`docker cp ${name}:${source} "${destination}"`);
  }
);

interface CopyToContainerProps {
  destination: string;
  name?: string;
  source: string;
}

Cypress.Commands.add(
  'copyToContainer',
  ({
    name = Cypress.env('dockerName'),
    source,
    destination
  }: CopyToContainerProps) => {
    return cy.exec(`docker cp ${source} ${name}:${destination}`);
  }
);

interface LoginByTypeOfUserProps {
  jsonName?: string;
  loginViaApi?: boolean;
}

Cypress.Commands.add(
  'loginByTypeOfUser',
  ({ jsonName, loginViaApi }): Cypress.Chainable => {
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
        cy.getByLabel({ label: 'Alias', tag: 'input' }).type(credential.login);
        cy.getByLabel({ label: 'Password', tag: 'input' }).type(
          credential.password
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
    return cy.exec(`docker exec -i ${name} ${command}`);
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
    return cy
      .exec('docker image list --format "{{.Repository}}:{{.Tag}}"')
      .then(({ stdout }) => {
        const found = image.match(/([a-z0-9._-]+):([a-z0-9._-]+)/);
        if (
          found &&
          stdout.match(
            new RegExp(
              `^${found[1].replace(
                /[-[\]{}()*+?.,\\^$|#\s]/g,
                '\\$&'
              )}:${found[2].replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&')}`,
              'm'
            )
          )
        ) {
          cy.log(`Local docker image found : ${found[1]}:${found[2]}`);

          return cy.wrap(`${found[1]}:${found[2]}`);
        }

        if (
          stdout.match(
            new RegExp(
              `^${image.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&')}`,
              'm'
            )
          )
        ) {
          cy.log(`Pulled remote docker image found : ${image}`);

          return cy.wrap(image);
        }

        cy.log(`Pulling remote docker image : ${image}`);

        return cy.exec(`docker pull ${image}`).then(() => cy.wrap(image));
      })
      .then((imageName) =>
        cy.task('startContainer', { imageName, name, portBindings })
      );
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
        const baseUrl = 'http://0.0.0.0:4000';

        Cypress.config('baseUrl', baseUrl);

        return cy.exec(
          `npx wait-on ${baseUrl}/centreon/api/latest/platform/installation/status`
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
    const logDirectory = `cypress/results/logs/${Cypress.spec.name.replace(
      artifactIllegalCharactersMatcher,
      '_'
    )}/${Cypress.currentTest.title.replace(
      artifactIllegalCharactersMatcher,
      '_'
    )}`;

    return cy
      .visitEmptyPage()
      .exec(`mkdir -p "${logDirectory}"`)
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

        return cy.copyFromContainer({
          destination: `${logDirectory}/php8.1-fpm-centreon-error.log`,
          name,
          source: '/var/log/php8.1-fpm-centreon-error.log'
        });
      })
      .stopContainer({ name });
  }
);

interface StopContainerProps {
  name: string;
}

Cypress.Commands.add(
  'stopContainer',
  ({ name }: StopContainerProps): Cypress.Chainable => {
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

Cypress.Commands.add('getTimeFromHeader', (): Cypress.Chainable => {
  return cy.waitUntil(() => {
    return cy.get('header div[data-cy="clock"]').then(($time) => {
      const headerTime = $time.children()[1].textContent;
      if (headerTime?.match(/\d+:\d+/)) {
        return headerTime;
      }

      return false;
    });
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      clickSubRootMenuItem: (page: string) => Cypress.Chainable;
      copyFromContainer: (props: CopyFromContainerProps) => Cypress.Chainable;
      copyToContainer: (props: CopyToContainerProps) => Cypress.Chainable;
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
        jsonName = 'admin',
        loginViaApi = false
      }: LoginByTypeOfUserProps) => Cypress.Chainable;
      moveSortableElement: (direction: string) => Cypress.Chainable;
      navigateTo: ({
        page,
        rootItemNumber,
        subMenu
      }: NavigateToProps) => Cypress.Chainable;
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
