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

Cypress.Commands.add(
  'executeActionOnIframe',
  (
    textToFind: string,
    action: (body: JQuery<HTMLElement>) => void,
    retryAttempts: number,
    retryDelay: number
  ) => {
    const attempt = ($iframe) => {
      return new Cypress.Promise((resolve) => {
        const $body = $iframe.contents().find('body');
        const containsText = $body.text().includes(textToFind);
        if (containsText) {
          action($body);
          resolve(true);
        } else {
          resolve(false);
        }
      });
    };

    const attemptWithRetry = (attemptNumber) => {
      cy.wrap(`Attempt number ${attemptNumber}`);
      if (attemptNumber > retryAttempts) {
        throw new Error(`The ${textToFind} not found in the iframe body`);
      }

      return cy.get('iframe#main-content').then(($iframe) => {
        return attempt($iframe).then((found) => {
          if (!found) {
            return new Cypress.Promise((resolve) => {
              setTimeout(() => {
                resolve(attemptWithRetry(attemptNumber + 1));
              }, retryDelay);
            });
          }
        });
      });
    };

    cy.wrap(null).then(() => attemptWithRetry(1));
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      addACLMenuToACLGroup: (props: LinkMeuToGroupProps) => Cypress.Chainable;
      executeActionOnIframe: (
        textToFind: string,
        action: (body: JQuery<HTMLElement>) => void,
        retryAttempts: number,
        retryDelay: number
      ) => Cypress.Chainable;
      loginByCredentials: (props: Credentials) => Cypress.Chainable;
    }
  }
}

export {};
