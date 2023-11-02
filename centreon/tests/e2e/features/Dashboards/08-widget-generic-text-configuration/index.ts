import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { PatternType } from '../../../support/commands';
import dashboardCreatorUser from '../../../fixtures/users/user-dashboard-creator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-configuration-creator.json'
  );
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?'
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
    url: '/centreon/api/latest/configuration/dashboards?'
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
  cy.visit('/centreon/home/dashboards');
});

after(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.stopWebContainer();
});

Given(
  "a dashboard in the dashboard administrator user's dashboard library",
  () => {
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.default.name)
      .click();
  }
);

When(
  'the dashboard administrator user selects the option to add a new widget',
  () => {
    cy.get('*[class^="react-grid-layout"]').children().should('have.length', 0);
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.getByTestId({ testId: 'AddIcon' }).click();
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
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
  }
);

Then("the same text is displayed in the widget's preview", () => {
  cy.getByLabel({ label: 'RichTextEditor' })
    .eq(1)
    .should('contain.text', genericTextWidgets.default.description);
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
  cy.contains(genericTextWidgets.default.title).should('exist');
  cy.contains(genericTextWidgets.default.description).should('exist');
  cy.getByTestId({ testId: 'save_dashboard' }).click();
  cy.wait('@updateDashboard');
  cy.contains(genericTextWidgets.default.title).should('exist');
  cy.contains(genericTextWidgets.default.description).should('exist');
});

Given('a dashboard featuring a single Generic text widget', () => {
  cy.visit('/centreon/home/dashboards');
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
  cy.get('*[class^="react-grid-layout"]').children().should('have.length', 1);
  cy.contains(genericTextWidgets.default.title).should('exist');
  cy.contains(genericTextWidgets.default.description).should('exist');
  cy.getByTestId({ testId: 'edit_dashboard' }).click();
});

When('the dashboard administrator user duplicates the widget', () => {
  cy.getByTestId({ testId: 'More actions' }).click();
  cy.getByLabel({ label: 'Duplicate' }).click();
  cy.getByLabel({ label: 'Refresh' }).click();
  cy.getByTestId({ testId: 'save_dashboard' }).click();
  cy.wait('@updateDashboard');
});

Then(
  'a second widget with identical content is displayed on the dashboard',
  () => {
    cy.get('*[class^="react-grid-layout"]').children().should('have.length', 2);
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(0)
      .should('contain.text', genericTextWidgets.default.title);
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(0)
      .should('contain.text', genericTextWidgets.default.description);
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(1)
      .should('contain.text', genericTextWidgets.default.title);
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(1)
      .should('contain.text', genericTextWidgets.default.description);
  }
);

Given('a dashboard featuring two Generic text widgets', () => {
  cy.visit('/centreon/home/dashboards');
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
  cy.getByTestId({ testId: 'edit_dashboard' }).click();

  cy.get('*[class^="react-grid-layout"]').children().should('have.length', 2);
});

When(
  'the dashboard administrator user updates the contents of one of these widgets',
  () => {
    cy.getByLabel({ label: 'More actions' }).eq(1).click();
    cy.getByLabel({ label: 'Edit widget' }).click();
    cy.getByLabel({ label: 'Title' }).clear();
    cy.getByLabel({ label: 'Title' }).type(
      `${genericTextWidgets.default.title}-edited`
    );
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .trigger('click', { force: true });
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .clear({ force: true });
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .type(`${genericTextWidgets.default.description}-edited`, {
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
      .should('not.contain.text', `${genericTextWidgets.default.title}-edited`);
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(0)
      .should(
        'not.contain.text',
        `${genericTextWidgets.default.description}-edited`
      );
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(1)
      .should('contain.text', `${genericTextWidgets.default.title}-edited`);
    cy.get('*[class^="react-grid-layout"]')
      .children()
      .eq(1)
      .should(
        'contain.text',
        `${genericTextWidgets.default.description}-edited`
      );
  }
);

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.getByLabel({ label: 'More actions' }).eq(1).click();
  cy.getByLabel({ label: 'Delete widget' }).click();
  cy.getByLabel({ label: 'Delete' }).click();
  cy.getByTestId({ testId: 'save_dashboard' }).click();
  cy.wait('@updateDashboard');
});

Then('only the contents of the other widget are displayed', () => {
  cy.get('*[class^="react-grid-layout"]')
    .children()
    .eq(0)
    .should('not.contain.text', `${genericTextWidgets.default.title}-edited`);
  cy.get('*[class^="react-grid-layout"]')
    .children()
    .eq(0)
    .should(
      'not.contain.text',
      `${genericTextWidgets.default.description}-edited`
    );
});

When(
  'the dashboard administrator user hides the description of the widget',
  () => {
    cy.getByLabel({ label: 'More actions' }).click();
    cy.getByLabel({ label: 'Edit widget' }).click();
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
    .should('contain.text', `${genericTextWidgets.default.title}`);
  cy.get('*[class^="react-grid-layout"]')
    .children()
    .eq(0)
    .should('not.contain.text', `${genericTextWidgets.default.description}`);

  cy.getByTestId({ testId: 'edit_dashboard' }).click();
  cy.getByLabel({ label: 'More actions' }).click({ force: true });
  cy.getByLabel({ label: 'Edit widget' }).click();
  cy.getByLabel({ label: 'Show description' }).click({ force: true });
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.getByTestId({ testId: 'save_dashboard' }).click();
  cy.wait('@updateDashboard');
});

When(
  'the dashboard administrator user adds a clickable link in the contents of the widget',
  () => {
    cy.getByLabel({ label: 'More actions' }).click({ force: true });
    cy.getByLabel({ label: 'Edit widget' }).click();
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .clear({ force: true });
    cy.getByTestId({ testId: 'RichTextEditor' })
      .get('[contenteditable="true"]')
      .type('Link to Centreon website{selectall}', { force: true });
    cy.getByTestId({ testId: 'LinkIcon' }).click({ force: true });
    cy.getByTestId({ testId: 'EditIcon' }).click({ force: true });
    cy.getByTestId({ testId: 'InputLinkField' })
      .eq(1)
      .type('www.centreon.com{enter}', { force: true });
    cy.contains('www.centreon.com').should('be.visible');
    cy.getByTestId({ testId: 'confirm' }).click();
    cy.getByTestId({ testId: 'save_dashboard' }).click();
  }
);

Then(
  'the link is clickable on the dashboard view page and redirects to the proper website',
  () => {
    cy.contains('Link to Centreon website')
      .should('have.attr', 'href')
      .and('equal', 'https://www.centreon.com');
    cy.contains('Link to Centreon website')
      .should('have.attr', 'target')
      .and('equal', '_blank');
    cy.contains('Link to Centreon website').invoke('attr', 'target', '_self');
    cy.contains('Link to Centreon website').click({ force: true });
    cy.url().should('equal', 'https://www.centreon.com/');
  }
);
