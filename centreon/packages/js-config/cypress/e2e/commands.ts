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
    .get('iframe#main-content')
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
        .trigger('mouseover', { force: true });
      cy.contains(page).click({ force: true });

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
  source: string;
}

Cypress.Commands.add(
  'copyFromContainer',
  ({ source, destination }: CopyFromContainerProps) => {
    return cy.exec(
      `docker cp ${Cypress.env('dockerName')}:${source} "${destination}"`
    );
  }
);

interface CopyToContainerProps {
  destination: string;
  source: string;
}

Cypress.Commands.add(
  'copyToContainer',
  ({ source, destination }: CopyToContainerProps) => {
    return cy.exec(
      `docker cp ${source} ${Cypress.env('dockerName')}:${destination}`
    );
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
    return cy
      .exec('docker image list --format "{{.Repository}}:{{.Tag}}"')
      .then(({ stdout }) => {
        if (
          stdout.match(
            new RegExp(
              `^${image.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&')}`,
              'm'
            )
          )
        ) {
          cy.log(`Local docker image found : ${image}`);

          return cy.wrap(image);
        }

        cy.log(`Pulling remote docker image : ${image}`);

        return cy.exec(`docker pull ${image}`).then(() => cy.wrap(image));
      })
      .then((imageName) =>
        cy.task('startContainer', { image: imageName, name, portBindings })
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
    os = 'alma9',
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
        source: '/var/log/centreon-broker'
      })
      .copyFromContainer({
        destination: `${logDirectory}/engine`,
        source: '/var/log/centreon-engine'
      })
      .execInContainer({
        command: `bash -e <<EOF
        chmod 777 /var/log/centreon/centreon-web.log > /dev/null 2>&1 || :
EOF`,
        name
      })
      .copyFromContainer({
        destination: `${logDirectory}/centreon`,
        source: '/var/log/centreon'
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

Cypress.Commands.add(
  'waitForElementInIframe',
  (iframeSelector, elementSelector) => {
    cy.waitUntil(
      () =>
        cy.get(iframeSelector).then(($iframe) => {
          const iframeBody = $iframe[0].contentDocument.body;
          if (iframeBody) {
            const $element = Cypress.$(iframeBody).find(elementSelector);

            return $element.length > 0 && $element.is(':visible');
          }

          return false;
        }),
      {
        errorMsg: 'The element is not visible within the iframe',
        interval: 5000,
        timeout: 100000
      }
    ).then((isVisible) => {
      if (!isVisible) {
        throw new Error('The element is not visible');
      }
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      copyFromContainer: (props: CopyFromContainerProps) => Cypress.Chainable;
      copyToContainer: (props: CopyToContainerProps) => Cypress.Chainable;
      execInContainer: ({
        command,
        name
      }: ExecInContainerProps) => Cypress.Chainable;
      getIframeBody: () => Cypress.Chainable;
      getWebVersion: () => Cypress.Chainable;
      hoverRootMenuItem: (rootItemNumber: number) => Cypress.Chainable;
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
      waitForElementInIframe: (
        iframeSelector: string,
        elementSelector: string
      ) => Cypress.Chainable;
      stopContainer: ({ name }: StopContainerProps) => Cypress.Chainable;
      stopWebContainer: ({ name }?: StopWebContainerProps) => Cypress.Chainable;
      visitEmptyPage: () => Cypress.Chainable;
      waitForContainerAndSetToken: () => Cypress.Chainable;
    }
  }
}

export {};
