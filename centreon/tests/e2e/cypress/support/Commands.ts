/* eslint-disable @typescript-eslint/no-namespace */

import 'cypress-wait-until';
import { refreshButton } from '../e2e/Resources-status/common';
import { apiActionV1, executeActionViaClapi, ActionClapi } from '../commons';

const apiLogout = '/centreon/api/latest/authentication/logout';
const apiLoginV2 = '/centreon/authentication/providers/configurations/local';

Cypress.Commands.add(
  'getByLabel',
  ({ tag = '', label }: GetByLabelProps): Cypress.Chainable => {
    return cy.get(`${tag}[aria-label="${label}"]`);
  }
);

Cypress.Commands.add(
  'getByTestId',
  ({ tag = '', testId }: GetByTestIdProps): Cypress.Chainable => {
    return cy.get(`${tag}[data-testid="${testId}"]`);
  }
);

Cypress.Commands.add('refreshListing', (): Cypress.Chainable => {
  return cy.get(refreshButton).click();
});

Cypress.Commands.add('removeResourceData', (): Cypress.Chainable => {
  return executeActionViaClapi({
    action: 'DEL',
    object: 'HOST',
    values: 'test_host'
  });
});

Cypress.Commands.add('setUserTokenApiV1', (): Cypress.Chainable => {
  return cy.fixture('users/admin.json').then((userAdmin) => {
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
});

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

Cypress.Commands.add(
  'loginKeycloack',
  (jsonName: string): Cypress.Chainable => {
    return cy
      .fixture(`users/${jsonName}.json`)
      .then((credential) => {
        cy.get('#username').clear().type(credential.login);
        cy.get('#password').clear().type(credential.password);
      })
      .get('#kc-login')
      .click();
  }
);

Cypress.Commands.add(
  'hoverRootMenuItem',
  (rootItemNumber: number): Cypress.Chainable => {
    return cy
      .get('li')
      .eq(rootItemNumber)
      .within(($li) => {
        if ($li) {
          return $li;
        }

        return cy
          .reload()
          .wait('@getNavigationList')
          .hoverRootMenuItem(rootItemNumber);
      })
      .trigger('mouseover');
  }
);

Cypress.Commands.add(
  'executeCommandsViaClapi',
  (fixtureFile: string): Cypress.Chainable => {
    return cy.fixture(fixtureFile).then((listRequestConfig) => {
      cy.wrap(
        Promise.all(
          listRequestConfig.map((request: ActionClapi) =>
            executeActionViaClapi(request)
          )
        )
      );
    });
  }
);

Cypress.Commands.add('getIframeBody', (): Cypress.Chainable => {
  return cy
    .get('iframe#main-content')
    .its('0.contentDocument.body')
    .should('not.be.empty')
    .then(cy.wrap);
});

Cypress.Commands.add(
  'requestOnDatabase',
  ({ database, query }: requestOnDatabaseProps): void => {
    const command = `docker exec -i ${Cypress.env(
      'dockerName'
    )} mysql -ucentreon -pcentreon ${database} <<< "${query}"`;

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
  'isInProfileMenu',
  (targetedMenu: string): Cypress.Chainable => {
    return cy
      .get('header')
      .get('svg[aria-label="Profile"]')
      .click()
      .get('div[role="tooltip"]')
      .contains(targetedMenu);
  }
);

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

Cypress.Commands.add('logout', (): Cypress.Chainable => {
  return cy.request({
    body: {},
    method: 'POST',
    url: apiLogout
  });
});

Cypress.Commands.add('removeACL', (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    executeActionViaClapi({
      action: 'DEL',
      object: 'ACLMENU',
      values: 'acl_menu_test'
    });
    executeActionViaClapi({
      action: 'DEL',
      object: 'ACLGROUP',
      values: 'ACL Group test'
    });
  });
});

Cypress.Commands.add('startOpenIdProviderContainer', (): Cypress.Chainable => {
  return cy.exec(
    'docker run -p 8080:8080 -d --name e2e-tests-openid-centreon docker.centreon.com/centreon/openid:23.04'
  );
});

Cypress.Commands.add('stopOpenIdProviderContainer', (): Cypress.Chainable => {
  return cy.exec(
    'docker stop e2e-tests-openid-centreon && docker rm e2e-tests-openid-centreon'
  );
});

interface GetByLabelProps {
  label: string;
  tag?: string;
}

interface GetByTestIdProps {
  tag?: string;
  testId: string;
}

interface NavigateToProps {
  page: string;
  rootItemNumber: number;
  subMenu?: string;
}

interface LoginByTypeOfUserProps {
  jsonName?: string;
  preserveToken?: boolean;
}

interface requestOnDatabaseProps {
  database: string;
  query: string;
}

declare global {
  namespace Cypress {
    interface Chainable {
      executeCommandsViaClapi: (fixtureFile: string) => Cypress.Chainable;
      getByLabel: ({ tag, label }: GetByLabelProps) => Cypress.Chainable;
      getByTestId: ({ tag, testId }: GetByTestIdProps) => Cypress.Chainable;
      getIframeBody: () => Cypress.Chainable;
      hoverRootMenuItem: (rootItemNumber: number) => Cypress.Chainable;
      isInProfileMenu: (targetedMenu: string) => Cypress.Chainable;
      loginByTypeOfUser: ({
        jsonName = 'admin',
        preserveToken = false
      }: LoginByTypeOfUserProps) => Cypress.Chainable;
      loginKeycloack: (jsonName: string) => Cypress.Chainable;
      logout: () => Cypress.Chainable;
      navigateTo: ({
        page,
        rootItemNumber,
        subMenu
      }: NavigateToProps) => Cypress.Chainable;
      refreshListing: () => Cypress.Chainable;
      removeACL: () => Cypress.Chainable;
      removeResourceData: () => Cypress.Chainable;
      requestOnDatabase: ({
        database,
        query
      }: requestOnDatabaseProps) => Cypress.Chainable;
      setUserTokenApiV1: () => Cypress.Chainable;
      startOpenIdProviderContainer: () => Cypress.Chainable;
      stopOpenIdProviderContainer: () => Cypress.Chainable;
    }
  }
}
