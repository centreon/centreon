/* eslint-disable @typescript-eslint/no-namespace */

const apiBase = `${Cypress.config().baseUrl}/centreon/api`;
const apiActionV1 = `${apiBase}/index.php`;
const apiLoginV2 = '/centreon/authentication/providers/configurations/local';

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
    return cy.get('li').eq(rootItemNumber).trigger('mouseover');
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

interface CopyOntoContainerProps {
  destPath: string;
  srcPath: string;
}

Cypress.Commands.add(
  'copyOntoContainer',
  ({ srcPath, destPath }: CopyOntoContainerProps) => {
    return cy.exec(
      `docker cp ${srcPath} ${Cypress.env('dockerName')}:${destPath}`
    );
  }
);

interface LoginByTypeOfUserProps {
  jsonName?: string;
  preserveToken?: boolean;
}

Cypress.Commands.add(
  'loginByTypeOfUser',
  ({ jsonName, preserveToken }): Cypress.Chainable => {
    if (preserveToken) {
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
        .then(() => {
          Cypress.Cookies.defaults({
            preserve: 'PHPSESSID'
          });
        })
        .then(() => {
          cy.visit(`${Cypress.config().baseUrl}`);
        });
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

interface ActionClapi {
  action: string;
  object?: string;
  values: string;
}

interface ExecuteActionViaClapiProps {
  bodyContent: ActionClapi;
  method?: string;
}

Cypress.Commands.add(
  'executeActionViaClapi',
  ({
    bodyContent,
    method = 'POST'
  }: ExecuteActionViaClapiProps): Cypress.Chainable => {
    return cy.request({
      body: bodyContent,
      headers: {
        'Content-Type': 'application/json',
        'centreon-auth-token': window.localStorage.getItem('userTokenApiV1')
      },
      method,
      url: `${apiActionV1}?action=action&object=centreon_clapi`
    });
  }
);

Cypress.Commands.add(
  'executeCommandsViaClapi',
  (fixtureFile: string): Cypress.Chainable => {
    return cy.fixture(fixtureFile).then((listRequestConfig) => {
      cy.wrap(
        Promise.all(
          listRequestConfig.map((request: ActionClapi) =>
            cy.executeActionViaClapi({ bodyContent: request })
          )
        )
      );
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      copyOntoContainer: (props: CopyOntoContainerProps) => Cypress.Chainable;
      executeActionViaClapi: (
        props: ExecuteActionViaClapiProps
      ) => Cypress.Chainable;
      executeCommandsViaClapi: (fixtureFile: string) => Cypress.Chainable;
      getIframeBody: () => Cypress.Chainable;
      hoverRootMenuItem: (rootItemNumber: number) => Cypress.Chainable;
      loginByTypeOfUser: ({
        jsonName = 'admin',
        preserveToken = false
      }: LoginByTypeOfUserProps) => Cypress.Chainable;
      moveSortableElement: (direction: string) => Cypress.Chainable;
      navigateTo: ({
        page,
        rootItemNumber,
        subMenu
      }: NavigateToProps) => Cypress.Chainable;
    }
  }
}
