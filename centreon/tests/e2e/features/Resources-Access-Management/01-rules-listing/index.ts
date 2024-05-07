import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

Given('I am logged in as a user with administrator role', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given(
  'I have access to the Administration > ACL > Resource Access Management feature',
  () => {
    cy.enableResourcesAccessManagementFeature();
  }
);

When('I navigate to the Resource Access Management page', () => {
  cy.visit(`centreon/administration/resource-access/rules`);
});

Then(
  'I should see a table with columns: "Name", "Description", "Actions", "Status"',
  () => {
    cy.get('[class$="-table"]').each(($row) => {
      cy.wrap($row).within(() => {
        cy.contains('Name').should('exist');
        cy.contains('Description').should('exist');
        cy.contains('Actions').should('exist');
        cy.contains('Status').should('exist');
      });
    });
  }
);

Then('a button to add a new rule is available', () => {
  cy.getByTestId({ testId: 'createResourceAccessRule' }).should('exist');
});

Then('I should see at least 10 rules registered', () => {
  cy.getWebVersion().then(({ major_version }) => {
    cy.createMultipleResourceAccessRules(15, major_version);
  });
  cy.reload();
  cy.waitUntil(
    () => {
      return cy.get('[class$="-intersectionRow"]').then(($divs) => {
        return $divs.length === 10;
      });
    },
    { interval: 1000, timeout: 10000 }
  );
});

Given('the default pagination should be set to 10 per page', () => {
  cy.get('.MuiTablePagination-input').contains('10');
});

When('I click on the next page button', () => {
  cy.getByLabel({
    label: 'Next page',
    tag: 'button'
  }).click();
});

Then('I should see the next 5 rules displayed', () => {
  cy.get('[class$="-intersectionRow"]').should('have.length', 5);
  cy.get('[class$="-root-cell"]').should('be.visible');
});

When('I click on the previous page button', () => {
  cy.getByLabel({
    label: 'Previous page',
    tag: 'button'
  }).click();
});

Then('I should see the previous first 10 rules displayed', () => {
  cy.get('[class$="-intersectionRow"]').should('have.length', 10);
  cy.get('[class$="-root-cell"]').should('be.visible');
});

When(
  'I enter a search query in the search field for a rule or description',
  () => {
    cy.getWebVersion().then(({ major_version }) => {
      cy.createMultipleResourceAccessRules(4, major_version);
    });
    cy.reload();
    cy.getByTestId({ tag: 'input', testId: 'Search' }).type('Rule2');
  }
);

Then('I should see only the rules that match the search query', () => {
  cy.waitUntil(
    () => {
      return cy.get('[class$="-intersectionRow"]').then(($divs) => {
        return $divs.length === 1;
      });
    },
    { interval: 1000, timeout: 10000 }
  );
  cy.get('[class$="-text-rowNotHovered"]').contains('Rule2');
});

afterEach(() => {
  cy.stopContainers();
});
