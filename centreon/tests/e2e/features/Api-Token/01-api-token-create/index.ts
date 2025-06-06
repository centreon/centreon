import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

const token = {
  duration: '7 days',
  name: 'myToken',
  user: 'Guest'
};

beforeEach(() => {
  cy.startContainers();

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
  cy.get('.MuiAlert-message').should('not.exist');
});

Given('I am on the Authentication tokens page', () => {
  cy.visitApiTokens();
});

When('I click on the "Add" button', () => {
  cy.getByTestId({ testId: 'Add' }).click();
});

When('I fill in the following required fields', (dataTable: any) => {
  dataTable.hashes().forEach((element) => {
    const field = element.Field;
    const value = element.Value;

    if (field === 'Name') {
      cy.get('#Name').type(value);
    }

    if (field === 'User') {
      cy.addContact({
        email: 'email@centreon.com',
        name: value,
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
  cy.getByTestId({ testId: 'submit' }).click();
});

Then('a new basic Authentication token with hidden display is generated', () => {
  cy.wait('@getTokens');
  cy.getByTestId({ testId: 'tokenInput' }).as('generatedToken').should('exist');
  cy.get('@generatedToken').should('have.attr', 'type', 'password');
});

Given('a basic Authentication token is generated', () => {
  cy.getByTestId({ testId: 'Add' }).click();

  cy.get('#Name').type(token.name);

  cy.get('#User').click();
  cy.wait('@getUsers');
  cy.contains(token.user).click();

  cy.get('#Duration').click();
  cy.contains(token.duration).click();

  cy.getByTestId({ testId: 'submit' }).click();

  cy.wait('@getTokens');
});

When('I click to reveal the token', () => {
  cy.getByLabel({ label: 'toggle password visibility', tag: 'button' }).click();
});

Then('the token is displayed', () => {
  cy.getByTestId({ testId: 'tokenInput' }).should('have.attr', 'type', 'text');
});

Then('the "copy to clipboard" button is clicked', () => {
  cy.getByTestId({ testId: 'clipboard' }).realClick();
});

Then('the token is successfully copied', () => {
  cy.get('.MuiAlert-message').contains(
    'Authentication token copied to the clipboard'
  );
});
