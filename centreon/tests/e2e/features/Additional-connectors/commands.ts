Cypress.Commands.add('createAccWithMandatoryFields', () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).type('Connector-001');
    cy.get('#mui-component-select-type').should('have.text', 'VMWare 6/7');
    cy.getByLabel({ label: 'Select poller(s)', tag: 'input' }).click();
    cy.contains('Central').click();
    cy.get('#Usernamevalue').type('admin');
    cy.get('#Passwordvalue').type('Abcde!2021');
    cy.get('#vCenternamevalue').clear().type('vCenter-001');
    cy.get('#URLvalue').clear().type('https://10.0.0.0/sdk');
    cy.get('#Portvalue').should('have.value', '5700');
    cy.getByLabel({ label: 'Save', tag: 'button' }).click();
    cy.wait('@addAdditionalConnector');
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
    const {
      maxAttempts = 5,
      interval = 5000,
    } = options;

    let attempt = 0;

    return cy.waitUntil(() => {
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
        return cy.getByTestId({ testId: 'cancel' }).click().then(() => true);
      }

      if (attempt >= maxAttempts) {
        return false; // max attempts reached
      }

      cy.log(`Attempt ${attempt}: current value = "${currentVal}", expected "${expectedValue}"`);

      // Click cancel and re-click 'VMWare 6/7' before retrying
      cy.getByTestId({ testId: 'cancel' }).click();
      cy.contains('VMWare 6/7').click();

      return false;
    }, {
      errorMsg: `Input did not reach value "${expectedValue}" after ${maxAttempts} attempts.`,
      timeout: maxAttempts * interval,
      interval,
      customMessage: `Waiting for input to have value "${expectedValue}"`,
      verbose: true,
    });
  }
);

  declare global {
    namespace Cypress {
      interface Chainable {
        createAccWithMandatoryFields: () => Cypress.Chainable;
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
