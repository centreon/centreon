interface Acc {
  name: string;
  type: string;
  pollers: Array<string>;
  user: string;
  password: string;
  vCenternames: Array<string>;
  url: string;
  port: string;
}

Cypress.Commands.add('createAccWithMandatoryFields', (body: Acc) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type(body.name);
  cy.get('#mui-component-select-type').should('have.text', body.type);
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.contains(body.pollers[0]).click();
  cy.get('#Usernamevalue').clear().type(body.user);
  cy.get('#Passwordvalue').clear().type(body.password);
  cy.get('#vCenternamevalue').clear().type(body.vCenternames[0]);
  cy.get('#URLvalue').clear().type(body.url);
  cy.get('#Portvalue').should('have.value', body.port);
});

Cypress.Commands.add('updateAcc', (body: Acc) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear().type(body.name);
  cy.get('#mui-component-select-type').should('have.text', body.type);
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
  cy.get('svg[class*="deleteIcon"]').click();
  cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click().click();
  cy.contains(body.pollers[0]).click();
  cy.get('#Usernamevalue').clear().type(body.user);
  // To edit the password now you shoud use the pen edit icon
  cy.editvCenterPassword(body.password);
  cy.get('#vCenternamevalue').clear().type(body.vCenternames[0]);
  cy.get('#URLvalue').clear().type(body.url);
  cy.get('#Portvalue').clear().click().type(body.port);
});

Cypress.Commands.add('editvCenterPassword', (value: string) => {
  cy.getByTestId({ tag: 'button', testId: 'button_edit' }).click();
  cy.get('#Passwordvalue').should('not.be.disabled').and('have.value', '');
  cy.get('#Passwordvalue').clear().type(value);
  cy.getByTestId({ tag: 'button', testId: 'button_save' }).click();
});

Cypress.Commands.add('verifyAccFieldValues', (body: Acc) => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should(
    'have.value',
    body.name
  );
  cy.getByLabel({ label: 'Description', tag: 'input' }).should('be.empty');
  cy.get('#mui-component-select-type').should('have.text', body.type);
  cy.get('*[class^="MuiChip-label MuiChip-labelMedium"]').should(
    'contain',
    body.pollers[0]
  );
  cy.get('#Usernamevalue').should('have.value', body.user);
  // The field password is now disabled and shows only * as  value
  cy.get('#Passwordvalue')
    .should('be.disabled')
    .invoke('val')
    .should((val) => {
      expect(val).to.match(/^\*+$/);
    });
  cy.get('#vCenternamevalue').should('have.value', body.vCenternames[0]);
  cy.get('#URLvalue').should('have.value', body.url);
  cy.get('#Portvalue').should('have.value', body.port);
});

Cypress.Commands.add('saveAcc', () => {
  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
});

type RetryOptions = {
  maxAttempts?: number;
  interval?: number;
};

Cypress.Commands.add(
  'ensureConnectorInputValue',
  (expectedValue: string, options: RetryOptions = {}) => {
    const { maxAttempts = 5, interval = 5000 } = options;

    let attempt = 0;

    return cy.waitUntil(
      () => {
        attempt++;

        // Use Cypress.$ for quick DOM check without failing the test
        const inputEl = Cypress.$('input[aria-label="Name"]');

        if (inputEl.length === 0 || !inputEl.is(':visible')) {
          cy.log(`Attempt ${attempt}: input not visible yet`);

          if (attempt >= maxAttempts) {
            return false;
          }

          cy.getByTestId({ testId: 'cancel' }).click();
          cy.contains('VMWare 6/7').click();

          return false; // retry
        }

        const currentVal = inputEl.val();

        if (currentVal === expectedValue) {
          return cy
            .getByTestId({ testId: 'cancel' })
            .click()
            .then(() => true);
        }

        if (attempt >= maxAttempts) {
          return false; // max attempts reached
        }

        cy.log(
          `Attempt ${attempt}: current value = "${currentVal}", expected "${expectedValue}"`
        );

        // Click cancel and re-click 'VMWare 6/7' before retrying
        cy.getByTestId({ testId: 'cancel' }).click();
        cy.contains('VMWare 6/7').click();

        return false;
      },
      {
        customMessage: `Waiting for input to have value "${expectedValue}"`,
        errorMsg: `Input did not reach value "${expectedValue}" after ${maxAttempts} attempts.`,
        interval,
        timeout: maxAttempts * interval,
        verbose: true
      }
    );
  }
);

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      createAccWithMandatoryFields: (body: Acc) => Cypress.Chainable;
      updateAcc: (body: Acc) => Cypress.Chainable;
      editvCenterPassword: (value: string) => Cypress.Chainable;
      verifyAccFieldValues: (body: Acc) => Cypress.Chainable;
      saveAcc: () => Cypress.Chainable;
      ensureConnectorInputValue: (
        expectedValue: string,
        options?: {
          maxAttempts?: number;
          interval?: number;
        }
      ) => Cypress.Chainable;
    }
  }
}

export {};
