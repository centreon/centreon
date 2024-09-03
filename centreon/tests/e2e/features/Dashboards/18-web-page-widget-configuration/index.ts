import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import webPageWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithWebPageWidget.json';
import webPageWidgets from '../../../fixtures/dashboards/creation/widgets/dashboardWithTwoWebPageWidget.json';

before(() => {
  // cy.intercept({
  //   method: 'GET',
  //   url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  // }).as('getNavigationList');
  // cy.intercept({
  //   method: 'GET',
  //   url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  // }).as('resourceRequest');
  // cy.startContainers();
  // cy.enableDashboardFeature();
  // cy.executeCommandsViaClapi(
  //   'resources/clapi/config-ACL/dashboard-metrics-graph.json'
  // );
  // cy.applyAcl();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards**'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('updateDashboard');
  cy.intercept({
    method: 'GET',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('getDashboard');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: false
  });
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

after(() => {
  cy.stopContainers();
});

Given(
  "a dashboard in the dashboard administrator user's dashboard library",
  () => {
    cy.insertDashboard({ ...dashboards.default });
    cy.visitDashboard(dashboards.default.name);
  }
);

When(
  'the dashboard administrator user selects the option to add a new widget',
  () => {
    cy.get('*[class^="react-grid-layout"]').children().should('have.length', 0);
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.getByTestId({ testId: 'AddIcon' }).should('have.length', 1).click();
  }
);

When(
  'the dashboard administrator user selects the widget type "Web page"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Web page').click();
  }
);

Then(
  'configuration properties for the Web page widget are displayed',
  () => {
    cy.getByLabel({ label: 'Title' }).should('be.visible');
    cy.getByLabel({ label: 'RichTextEditor' }).should('be.visible');
    cy.getByLabel({ label: 'URL' }).should('be.visible');
  }
)

When('the dashboard administrator adds a valid URL', () => {
cy.getByLabel({ label: 'URL' }).type('https://docs.centreon.com/fr/')
cy.get('iframe[data-testid="Webpage Display"]')
.should('be.visible')
.and('have.attr', 'src', 'https://docs.centreon.com/fr/');
cy.get('iframe')
.its('0.contentDocument.body')
.should('not.be.empty')
.then(cy.wrap)

.find('h1')
.should('exist');
});

When('the user saves the Web page widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click({ force: true });
});

Then("the Web page widget is added in the dashboard's layout", () => {
  cy.get('iframe')
  .its('0.contentDocument.body')
  .should('not.be.empty')
  .then(cy.wrap)
  .find('h1')
  .should('exist')
  .and('have.text', 'Bienvenue dans la Documentation Centreon !');
});

Given('a dashboard having a configured Web page widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, webPageWidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
});

When(
  'the dashboard administrator user duplicates the Web page widget',
  () => {
    cy.editDashboard(dashboards.default.name);
    cy.getByTestId({ testId: 'More actions' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click({ force: true });
  }
);

Then(
  'a second Web page widget is displayed on the dashboard',
  () => {
  cy.get('iframe').eq(1)
  .its('0.contentDocument.body')
  .should('not.be.empty')
  .then(cy.wrap)
  .find('h1')
  .should('exist')
  .and('have.text', 'Bienvenue dans la Documentation Centreon !');
  }
);

Given('a dashboard featuring two Web page widgets', () => {
  cy.insertDashboardWithWidget(dashboards.default, webPageWidgets);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
});

When(
  'the dashboard administrator user deletes one of the widgets',
  () => {
    cy.editDashboard(dashboards.default.name);
    cy.getByTestId({ testId: 'More actions' }).eq(1).click();
    cy.getByTestId({ testId: 'DeleteIcon' }).click({ force: true });
    cy.getByLabel({
      label: 'Delete',
      tag: 'button'
    }).realClick();
  }
);

Then(
  'only the contents of the other widget are displayed',
  () => {
    cy.get('iframe').eq(1).should('not.exist');
  }
);
