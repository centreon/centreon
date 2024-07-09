before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

after(() => {
  cy.stopContainers();
});

Given('the administrator is logged in', () => {
  cy.getByLabel({ label: 'Alias', tag: 'input' })
});

When('the admin user visits dashboard page', () => {
});

Then('the admin user could create a new dashboard', () => {
});