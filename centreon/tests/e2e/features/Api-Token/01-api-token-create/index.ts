import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import '../commands';

beforeEach(() => {
  cy.startContainers();
  cy.enableAPITokensFeature();

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/administration/tokens?*'
  }).as('getTokens');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/users?*'
  }).as('getUsers');
});

afterEach(() => {
  cy.stopContainers();
});

Given('I am logged in as an administrator', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('I am on the API tokens page', () => {
  cy.navigateTo({
    page: 'API Tokens',
    rootItemNumber: 4
  });

  cy.wait('@getTokens');
});

When('I click on the "Create new token" button', () => {
  cy.getByTestId({ testId: 'Create new token' }).click();
});

When('I fill in the following required fields', (dataTable: any) => {
  dataTable.hashes().forEach((element) => {
    const field = element.Field;
    const value = element.Value;

    cy.log(field, value);

    if (field === 'Name') {
      cy.get('#tokenName').type(value);
    }

    if (field === 'User') {
      cy.addContact({
        name: value,
        email: 'email@centreon.com',
        password: 'myPassword@1'
      });

      cy.get('#User').click();
      cy.wait('@getUsers');
      cy.contains(value).click();
    }
  });
});

When('I select the duration as {string}', (duration: string) => {
  cy.get('#Duration').click();
  cy.contains(duration).click();
});

When('I click on the "Generate token" button', () => {
  cy.getByTestId({ testId: 'Confirm' }).click();
});

Then('a new basic API token with hidden display is generated', () => {
  cy.wait('@getTokens');
  cy.getByTestId({ testId: 'tokenInput' }).as('generatedToken').should('exist');
  cy.get('@generatedToken').should('have.attr', 'type', 'password');
});

Given('a basic API token is generated', () => {});

When('I click to reveal the token', () => {});

Then('the token is displayed', () => {});

Then('the "copy to clipboard" button is clicked', () => {});

Then(
  'the "copy to clipboard" button is replaced with the check button',
  () => {}
);

Then('the token is successfully copied', () => {});

When('I click on the "Save" button', () => {});

Then('the token is saved successfully', () => {});

Given(
  'there is an existing basic API token with the following details:',
  (dataTable) => {}
);

When('I click on the token to edit', () => {});

When('I modify the token details as follows:', (dataTable) => {});

Then('the token and its modified details are saved successfully', () => {});

Then(
  'the updated token details are displayed correctly on the API tokens page',
  () => {}
);
