/* eslint-disable @typescript-eslint/no-namespace */

import 'cypress-wait-until';
import '@centreon/js-config/cypress/e2e/commands';
import { refreshButton } from '../e2e/Resources-status/common';
import { apiActionV1 } from '../commons';

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

Cypress.Commands.add(
  'loginKeycloack',
  (jsonName: string): Cypress.Chainable => {
    cy.fixture(`users/${jsonName}.json`).then((credential) => {
      cy.get('#username').clear().type(credential.login);
      cy.get('#password').clear().type(credential.password);
    });

    return cy.get('#kc-login').click();
  }
);

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
    cy.get('header svg[aria-label="Profile"]').click();

    return cy.get('div[role="tooltip"]').contains(targetedMenu);
  }
);

Cypress.Commands.add('logout', (): Cypress.Chainable => {
  cy.getByLabel({ label: 'Profile' }).click();

  return cy.contains('Logout').click();
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
      image: `docker.centreon.com/centreon/openid:${Cypress.env(
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
    .then(() => {
      return cy.exec('npx wait-on http://0.0.0.0:8080/health/ready');
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

interface GetByLabelProps {
  label: string;
  tag?: string;
}

interface GetByTestIdProps {
  tag?: string;
  testId: string;
}

interface requestOnDatabaseProps {
  database: string;
  query: string;
}

declare global {
  namespace Cypress {
    interface Chainable {
      executeSqlRequestInContainer: (request: string) => Cypress.Chainable;
      getByLabel: ({ tag, label }: GetByLabelProps) => Cypress.Chainable;
      getByTestId: ({ tag, testId }: GetByTestIdProps) => Cypress.Chainable;
      isInProfileMenu: (targetedMenu: string) => Cypress.Chainable;
      loginKeycloack: (jsonName: string) => Cypress.Chainable;
      logout: () => Cypress.Chainable;
      refreshListing: () => Cypress.Chainable;
      removeACL: () => Cypress.Chainable;
      removeResourceData: () => Cypress.Chainable;
      requestOnDatabase: ({
        database,
        query
      }: requestOnDatabaseProps) => Cypress.Chainable;
      setUserTokenApiV1: (fixtureFile?: string) => Cypress.Chainable;
      startOpenIdProviderContainer: () => Cypress.Chainable;
      stopOpenIdProviderContainer: () => Cypress.Chainable;
    }
  }
}
