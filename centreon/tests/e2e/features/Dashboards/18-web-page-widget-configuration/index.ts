import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import webPageWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithWebPageWidget.json';

const validUrl = 'https://docs.centreon.com/fr/';
const invalidUrl = 'http://docss.Centreon.com/fr/';
const iframeContent = 'Bienvenue dans la Documentation Centreon !';

before(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-metrics-graph.json'
  );
  cy.applyAcl();
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
  'the dashboard administrator user selects the widget type "web page"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Web page').click();
  }
);

Then('configuration properties for the web page widget are displayed', () => {
  cy.getByLabel({ label: 'Title' }).should('be.visible');
  cy.getByLabel({ label: 'RichTextEditor' }).should('be.visible');
  cy.getByLabel({ label: 'URL' }).should('be.visible');
});

When('the dashboard administrator adds a valid URL', () => {
  cy.getByLabel({ label: 'URL' }).type(validUrl);
  cy.get('iframe[data-testid="Webpage Display"]')
    .should('be.visible')
    .and('have.attr', 'src', validUrl);
  cy.get('iframe')
    .its('0.contentDocument.body')
    .should('not.be.empty')
    .then(cy.wrap)

    .find('h1')
    .should('exist');
});

When('the user saves the web page widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click({ force: true });
});

Then("the web page widget is added in the dashboard's layout", () => {
  cy.get('iframe')
    .its('0.contentDocument.body')
    .should('not.be.empty')
    .then(cy.wrap)
    .find('h1')
    .should('exist')
    .and('have.text', iframeContent);
});

Given('a dashboard having a configured web page widget', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    webPageWidget,
    'centreon-widget-webpage',
    '/widgets/webpage'
  );

  cy.editDashboard(dashboards.default.name);
});

When('the dashboard administrator user duplicates the web page widget', () => {
  cy.editDashboard(dashboards.default.name);
  cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
  cy.getByTestId({ testId: 'ContentCopyIcon' }).click({ force: true });
});

Then('a second web page widget is displayed on the dashboard', () => {
  cy.get('iframe')
    .eq(1)
    .its('0.contentDocument.body')
    .should('not.be.empty')
    .then(cy.wrap)
    .find('h1')
    .should('exist')
    .and('have.text', iframeContent);
});

Given('a dashboard featuring two web page widgets', () => {
  cy.insertDashboardWithDoubleWidget(
    dashboards.default,
    webPageWidget,
    webPageWidget,
    'centreon-widget-webpage',
    '/widgets/webpage'
  );
  cy.editDashboard(dashboards.default.name);
});

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.getByTestId({ testId: 'More actions' }).eq(1).click();
  cy.getByTestId({ testId: 'DeleteIcon' }).click({ force: true });
  cy.getByLabel({
    label: 'Delete',
    tag: 'button'
  }).realClick();
});

Then('only the contents of the other widget are displayed', () => {
  cy.get('iframe').eq(1).should('not.exist');
});

When('the dashboard administrator attempts to add an invalid URL', () => {
  cy.editWidget(1);
  cy.getByLabel({ label: 'URL' }).clear().type(invalidUrl);
  cy.getByTestId({ testId: 'confirm' }).click({ force: true });
  cy.get('.MuiAlert-message').should('not.exist');
});

Then(
  'an error message should be displayed, indicating that the URL is invalid',
  () => {
    cy.get('iframe').its('0.contentDocument.body').should('be.empty');
  }
);
