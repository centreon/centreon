import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { PatternType } from '@centreon/js-config/cypress/e2e/commands';

import dashboardCreatorUser from '../../../fixtures/users/user-dashboard-creator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidget from '../../../fixtures/dashboards/creation/widgets/genericText.json';

before(() => {
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-configuration-creator.json'
  );
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards**'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.default });
  cy.logoutViaAPI();
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
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.intercept({
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('updateDashboard');
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: false
  });
});

after(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.stopContainers();
});

Given(
  "a dashboard in the dashboard administrator user's dashboard library",
  () => {
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

When('selects the widget type "Generic text"', () => {
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Generic text').click();
});

Then(
  'configuration properties for the Generic text widget are displayed',
  () => {
    cy.contains('Widget properties').should('exist');
    cy.getByLabel({ label: 'Title' }).should('exist');
    cy.getByLabel({ label: 'RichTextEditor' }).should('exist');
  }
);

When(
  "the dashboard administrator user gives a title to the widget and types some text in the properties' description field",
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidget.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidget.default.description);
  }
);

Then("the same text is displayed in the widget's preview", () => {
  cy.getByLabel({ label: 'RichTextEditor' })
    .eq(1)
    .should('contain.text', genericTextWidget.default.description);
});

When('the user saves the widget containing the Generic text', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Generic text widget is added in the dashboard's layout", () => {
  cy.get('*[class^="react-grid-layout"]').children().should('have.length', 1);
  cy.contains('Your widget has been created successfully!').should('exist');
  cy.getByTestId({
    patternType: PatternType.startsWith,
    testId: 'panel_/widgets/generictext'
  }).should('exist');
});

Then('its title and description are displayed', () => {
  cy.contains(genericTextWidget.default.title).should('exist');
  cy.contains(genericTextWidget.default.description).should('exist');
  cy.getByTestId({ testId: 'save_dashboard' }).click();
  cy.wait('@updateDashboard');
  cy.contains(genericTextWidget.default.title).should('exist');
  cy.contains(genericTextWidget.default.description).should('exist');
});

Given('a dashboard featuring a single Generic text widget', () => {
  cy.visitDashboards();
  cy.contains(dashboards.default.name).click();
  cy.get('*[class^="react-grid-layout"]').children().should('have.length', 1);
  cy.contains(genericTextWidget.default.title).should('exist');
  cy.contains(genericTextWidget.default.description).should('exist');
  cy.getByTestId({ testId: 'edit_dashboard' }).click();
});

When('the dashboard administrator user duplicates the widget', () => {
  cy.getByLabel({ label: 'More actions' }).eq(0).click();
  cy.getByLabel({ label: 'Duplicate' }).eq(0).click();
  cy.get('*[class^="react-grid-layout"]')
    .should('exist')
    .children()
    .should('have.length', 2);
  cy.getByTestId({ testId: 'save_dashboard' }).click({ force: true });
  cy.wait('@updateDashboard');
});

Then(
  'a second widget with identical content is displayed on the dashboard',
  () => {
    cy.get('*[class^="react-grid-layout"]').children().should('have.length', 2);
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(0)
      .should('contain.text', genericTextWidget.default.title)
      .should('contain.text', genericTextWidget.default.description);
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(1)
      .should('contain.text', genericTextWidget.default.title)
      .should('contain.text', genericTextWidget.default.description);
  }
);

Given('a dashboard featuring two Generic text widgets', () => {
  cy.editDashboard(dashboards.default.name);

  cy.get('*[class^="react-grid-layout"]').children().should('have.length', 2);
});

When(
  'the dashboard administrator user updates the contents of one of these widgets',
  () => {
    cy.editWidget(2);
    cy.getByLabel({ label: 'Title' }).clear();
    cy.getByLabel({ label: 'Title' }).type(
      `${genericTextWidget.default.title}-edited`
    );
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .click();
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .clear({ force: true });
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .type(`${genericTextWidget.default.description}-edited`, {
        force: true
      });
    cy.getByTestId({ testId: 'confirm' }).click();
    cy.getByTestId({ testId: 'save_dashboard' }).click();
    cy.wait('@updateDashboard');
  }
);

Then(
  'the updated contents of the widget are displayed instead of the original ones',
  () => {
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(0)
      .should('not.contain.text', `${genericTextWidget.default.title}-edited`)
      .should(
        'not.contain.text',
        `${genericTextWidget.default.description}-edited`
      );
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(1)
      .should('contain.text', `${genericTextWidget.default.title}-edited`)
      .should(
        'contain.text',
        `${genericTextWidget.default.description}-edited`
      );
  }
);

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.getByLabel({ label: 'More actions' }).eq(1).click();
  cy.getByLabel({ label: 'Delete widget' }).click();
  cy.getByLabel({ label: 'Delete' }).click();
  cy.getByTestId({ testId: 'save_dashboard' }).click();
  cy.wait('@updateDashboard');
  cy.getByTestId({ testId: 'RefreshIcon' }).click();
});

Then('only the contents of the other widget are displayed', () => {
  cy.get('*[class^="react-grid-layout"]')
    .children()
    .eq(0)
    .should('not.contain.text', `${genericTextWidget.default.title}-edited`)
    .should(
      'not.contain.text',
      `${genericTextWidget.default.description}-edited`
    );
});

When(
  'the dashboard administrator user hides the description of the widget',
  () => {
    cy.editWidget(1);
    cy.getByLabel({ label: 'Show description' }).click({ force: true });
    cy.getByTestId({ testId: 'confirm' }).click();
    cy.getByTestId({ testId: 'save_dashboard' }).click();
    cy.wait('@updateDashboard');
  }
);

Then('the description is hidden and only the title is displayed', () => {
  cy.get('*[class^="react-grid-layout"]')
    .children()
    .eq(0)
    .should('contain.text', `${genericTextWidget.default.title}`)
    .should('not.contain.text', `${genericTextWidget.default.description}`);

  cy.getByTestId({ testId: 'edit_dashboard' }).click();
  cy.editWidget(1);
  cy.getByLabel({ label: 'Show description' }).click({ force: true });
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.getByTestId({ testId: 'save_dashboard' }).click();
  cy.wait('@updateDashboard');
});

When(
  'the dashboard administrator user adds a clickable link in the contents of the widget',
  () => {
    cy.editWidget(1);
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .clear({ force: true });
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .type('Link to google website{selectall}', { force: true });
    cy.getByTestId({ testId: 'LinkIcon' }).click({ force: true });
    cy.getByTestId({ testId: 'EditIcon' }).click({ force: true });
    cy.getByTestId({ testId: 'InputLinkField' })
      .eq(1)
      .type('www.google.com{enter}', { force: true });
    cy.contains('www.google.com').should('be.visible');
    cy.getByTestId({ testId: 'confirm' }).click();
    cy.getByTestId({ testId: 'save_dashboard' }).click();
  }
);

Then(
  'the link is clickable on the dashboard view page and redirects to the proper website',
  () => {
    cy.contains('Link to google website')
      .should('have.attr', 'href')
      .and('equal', 'https://www.google.com');
    cy.contains('Link to google website')
      .should('have.attr', 'target')
      .and('equal', '_blank');
    cy.contains('Link to google website').invoke('attr', 'target', '_self');
    cy.contains('Link to google website').click({ force: true });
    cy.url().should('equal', 'https://www.google.com/');
  }
);
