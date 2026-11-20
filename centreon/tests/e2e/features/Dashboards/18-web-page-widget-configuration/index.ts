import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import webPageWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithWebPageWidget.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';

// The .test top-level domain never resolves: the page only exists through the
// stub below, so the test does not depend on any real website.
const validUrl = 'https://web-page-widget.e2e.test/';
const invalidUrl = 'http://docss.Centreon.com/fr/';
const iframeContent = 'Web page widget e2e content';

const stubWebPage = (): void => {
  cy.intercept(
    { method: 'GET', url: /^https:\/\/web-page-widget\.e2e\.test\// },
    {
      body: `<!doctype html><html><body><h1>${iframeContent}</h1></body></html>`,
      headers: { 'content-type': 'text/html' },
      statusCode: 200
    }
  ).as('getWebPage');
};

// No .then(cy.wrap): keeping a query chain lets Cypress requery the iframe
// when it reloads, instead of failing on a detached element.
const webPageTitle = (iframeIndex = 0): Cypress.Chainable =>
  cy
    .get('iframe')
    .eq(iframeIndex)
    .its('0.contentDocument.body')
    .should('not.be.empty')
    .find('h1');

before(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
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
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.dashboard_configuration}**`
  }).as('listAllDashboards');
  cy.intercept({
    method: 'PATCH',
    url: `${INTERCEPTORS.api.dashboard_configuration}/*`
  }).as('updateDashboard');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.dashboard_configuration}/*`
  }).as('getDashboard');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  stubWebPage();
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
    cy.contains('div[class*="-addWidgetPanel"] h5', 'Add a widget').click();
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
  webPageTitle().should('exist');
});

When('the user saves the web page widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click({ force: true });
});

Then("the web page widget is added in the dashboard's layout", () => {
  webPageTitle().should('have.text', iframeContent);
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
  cy.getByTestId({ testId: 'More actions' }).click();
  cy.getByTestId({ testId: 'Duplicate' }).click({ force: true });
});

Then('a second web page widget is displayed on the dashboard', () => {
  webPageTitle(1).should('have.text', iframeContent);
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
  cy.getByTestId({ testId: 'Delete widget' }).click();
  cy.getByTestId({ testId: 'confirm' }).click();
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
