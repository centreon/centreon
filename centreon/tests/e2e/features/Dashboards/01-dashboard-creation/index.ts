import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { last } from 'ramda';

import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import textWidget from '../../../fixtures/dashboards/creation/widgets/textWidget.json';

before(() => {
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-configuration-creator.json'
  );
});

after(() => {
  cy.stopContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');
  cy.loginByTypeOfUser({
    jsonName: 'user-dashboard-creator',
    loginViaApi: false
  });
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

Given(
  'a user with dashboard edition rights on the dashboard listing page',
  () => {
    cy.visitDashboards();
  }
);

When('the user opens the form to create a new dashboard', () => {
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
});

Then(
  'the creation form is displayed and contains the fields to create a dashboard',
  () => {
    cy.contains('Create dashboard').should('be.visible');

    cy.getByLabel({ label: 'Name', tag: 'input' }).should('be.empty');

    cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');

    cy.getByTestId({ testId: 'submit' }).should('be.disabled');

    cy.getByTestId({ testId: 'cancel' }).should('be.enabled');
  }
);

When('the user fills in the required fields', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    dashboards.requiredOnly.name
  );
});

Then(
  'the user is allowed to create the dashboard with the required fields only',
  () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'have.value',
      dashboards.requiredOnly.name
    );

    cy.getByTestId({ testId: 'submit' }).should('be.enabled');
  }
);

When('the user saves the dashboard', () => {
  cy.getByTestId({ testId: 'submit' }).click();

  cy.wait('@createDashboard');
});

Then('the user is redirected to the newly created dashboard', () => {
  cy.location('pathname')
    .should('include', '/dashboards/')
    .invoke('split', '/')
    .should('not.be.empty')
    .then(last)
    .then(Number)
    .should('not.be', 'dashboards')
    .should('be.a', 'number'); // dashboard id
});

Then('the newly created dashboard detail page is in edit mode', () => {
  cy.location('search').should('include', 'edit=true');
});

Then('the newly created dashboard has the required only dashboard data', () => {
  cy.get('@createDashboard')
    .its('response.body.name')
    .should('eq', dashboards.requiredOnly.name);

  cy.getByLabel({ label: 'page header title' }).should(
    'contain.text',
    dashboards.requiredOnly.name
  );
});

Given(
  'a user with dashboard edition rights on the dashboard creation form',
  () => {
    cy.visit('/centreon/home/dashboards');
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
  }
);

When('the user fills in all the fields', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(dashboards.default.name);
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    dashboards.default.description
  );
});

Then(
  'the newly created dashboard has the name and description the user filled in',
  () => {
    cy.get('@createDashboard')
      .its('response.body.name')
      .should('eq', dashboards.default.name);
    cy.get('@createDashboard')
      .its('response.body.description')
      .should('eq', dashboards.default.description);

    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.default.name
    );
    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      dashboards.default.description
    );
  }
);

Given(
  'a user with dashboard edition rights who is about to create a dashboard',
  () => {
    cy.visitDashboards();
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(
      `${dashboards.default.name} to be cancelled`
    );
  }
);

When('the user leaves the creation form without saving the dashboard', () => {
  cy.getByTestId({ testId: 'cancel' }).click();
});

Then('the dashboard has not been created', () => {
  cy.get('@createDashboard').should('be.null');
});

Then('the user is on the dashboards overview page', () => {
  cy.location('pathname')
    .should('include', '/dashboards')
    .invoke('split', '/')
    .should('not.be.empty')
    .then(last)
    .should('eq', 'library'); // dashboards overview
});

When(
  'the user opens the form to create a new dashboard for the second time',
  () => {
    cy.getByLabel({ label: 'create', tag: 'button' }).click();
  }
);

Then('the form fields are empty', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).should('be.empty');
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).should('be.empty');
});

Given(
  "a dashboard with existing widgets in the dashboard administrator user's dashboard library",
  () => {
    cy.insertDashboardWithWidget(
      dashboards.fromDashboardCreatorUser,
      textWidget,
      'centreon-widget-generictext',
      '/widgets/generictext'
    );
    cy.visitDashboards();
  }
);

When('the dashboard administrator user starts to edit the dashboard', () => {
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
  cy.waitForElementToBeVisible('[data-testid="edit_dashboard"]')
  cy.getByTestId({ testId: 'edit_dashboard' }).click();
  cy.location('search').should('include', 'edit=true');
  cy.get('button[type=button]').contains('Add a widget').should('exist');
});

Then("creates a new dashboard on the previous dashboard's edition page", () => {
  cy.get('button[type=button]').contains('Add a widget').should('be.visible');
  cy.getByTestId({ testId: 'MenuIcon' }).click();
  cy.contains('Create a dashboard').click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(dashboards.default.name);
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    dashboards.default.description
  );
  cy.getByLabel({ label: 'Create', tag: 'button' }).click();
  cy.wait('@createDashboard');
});

Then(
  "the user is redirected to the newly created dashboard's edition page",
  () => {
    cy.url().should('match', /\/dashboards\/library\/\d+\?edit=true/);
  }
);

Then('the newly created dashboard is empty', () => {
  cy.get('[class*="addWidgetPanel"]').should('be.visible');
});
