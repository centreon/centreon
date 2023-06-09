import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { deleteAllDashboards, insertDashboardList } from '../common';
import { loginAsAdminViaApiV2 } from '../../../commons';

before(() => {
  cy.startWebContainer();
});

beforeEach(() => {
  loginAsAdminViaApiV2();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?page=1&limit=100'
  }).as('listAllDashboardsOnFirstPage');
});
Given('a user with access to the dashboards library', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/monitoring/resources`);
});

When(
  'they access the dashboard listing page on a platform with no dashboards',
  () => {
    cy.navigateTo({
      page: 'Dashboard (beta)',
      rootItemNumber: 0
    });
  }
);

Then(
  'a special message and a button to create a new dashboard are displayed instead of the dashboards',
  () => {
    cy.get('body').should('contain.text', 'No dashboards found');
    cy.getByLabel({ label: 'view', tag: 'button' }).should('not.exist');
    cy.getByLabel({ label: 'create', tag: 'button' }).should('exist');
  }
);

Given('a non-empty list of dashboards that fits on one page', () => {
  insertDashboardList('dashboards/navigation/01-onepage.json');
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

When('the user clicks on the dashboard they want to select', () => {
  cy.getByLabel({ label: 'view', tag: 'button' })
    .contains('dashboard-to-locate')
    .click();
});

Then(
  'they are redirected to the information page describing that dashboard',
  () => {
    cy.url().should(
      'not.eq',
      `${Cypress.config().baseUrl}/centreon/home/dashboards`
    );
    cy.getByLabel({ label: 'Breadcrumb' }).contains('Dashboard (beta)').click();

    deleteAllDashboards();
  }
);

Given('a non-empty library of dashboards that does not fit on one page', () => {
  insertDashboardList('dashboards/navigation/02-morethanonepage.json');
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

When(
  'the user scrolls down on the page to look for a dashboard at the end of the dashboards library',
  () => {
    cy.getByLabel({ label: 'view', tag: 'button' })
      .contains('dashboard-to-locate')
      .should('not.exist');

    cy.get('[data-variant="grid"]').parent().parent().scrollTo('bottom');

    cy.request(
      'GET',
      '/centreon/api/latest/configuration/dashboards?page=2&limit=100'
    );
  }
);

Then(
  'the elements of the library displayed on the screen progressively change and the dashboard to locate ends up appearing',
  () => {
    cy.getByLabel({ label: 'view', tag: 'button' })
      .contains('dashboard-name-0')
      .should('not.be.visible');

    cy.getByLabel({ label: 'view', tag: 'button' })
      .contains('dashboard-to-locate')
      .should('exist');
  }
);

When(
  'the user clicks on the dashboard they wanted to find at the bottom of the library',
  () => {
    cy.getByLabel({ label: 'view', tag: 'button' })
      .contains('dashboard-to-locate')
      .click();
  }
);

Then('they are redirected to the page describing that dashboard', () => {
  cy.url().should(
    'not.eq',
    `${Cypress.config().baseUrl}/centreon/home/dashboards`
  );
  cy.getByLabel({ label: 'Breadcrumb' }).contains('Dashboard (beta)').click();
  cy.request(
    'GET',
    '/centreon/api/latest/configuration/dashboards?page=2&limit=100'
  );
  cy.getByLabel({ label: 'delete', tag: 'button' }).click();
  cy.getByLabel({ label: 'confirm', tag: 'button' }).click();
  cy.request(
    'GET',
    '/centreon/api/latest/configuration/dashboards?page=1&limit=100'
  );
  deleteAllDashboards();
});
