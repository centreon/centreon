import 'cypress-iframe';

interface LinkMeuToGroupProps {
  ACLGroupName: string;
  ACLMenuName: string;
}

Cypress.Commands.add(
  'addACLMenuToACLGroup',
  ({ ACLGroupName, ACLMenuName }: LinkMeuToGroupProps) => {
    return cy.executeActionViaClapi({
      bodyContent: {
        action: 'ADDMENU',
        object: 'ACLGROUP',
        values: `${ACLGroupName};${ACLMenuName}`
      }
    });
  }
);

interface Credentials {
  login: string;
  password: string;
}

Cypress.Commands.add(
  'loginByCredentials',
  ({ login, password }: Credentials) => {
    return cy
      .request({
        body: {
          login: login,
          password: password
        },
        method: 'POST',
        url: '/centreon/authentication/providers/configurations/local'
      })
      .visit(`${Cypress.config().baseUrl}`)
      .wait('@getNavigationList');
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      addACLMenuToACLGroup: (props: LinkMeuToGroupProps) => Cypress.Chainable;
      loginByCredentials: (props: Credentials) => Cypress.Chainable;
    }
  }
}

export {};
