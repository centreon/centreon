import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import clockTimerWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithclockTimerWidget.json';

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
  'the dashboard administrator user selects the widget type "Clock timer"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Clock / Timer').click();
  }
);

Then(
  'configuration properties for the Clock timer widget are displayed',
  () => {
    cy.getByLabel({ label: 'Timer' }).should('exist');
    cy.getByLabel({ label: 'Show time zone' }).should('exist');
    cy.getByLabel({ label: 'Show date' }).should('exist');
    cy.getByLabel({ label: 'Select time zone' }).should('exist');
    cy.getByLabel({ label: 'Select time format' }).should('exist');
    cy.getByLabel({ label: '12 hours' }).should('exist');
    cy.getByLabel({ label: '24 hours' }).should('exist');
    cy.getByTestId({ testId: 'KeyboardArrowDownIcon' }).click();
    cy.get('div[class$="clockInformation"]').should('exist');
    cy.get('div[class$="clockLabel"]').should('exist');
  }
);

When('the user saves the Clock timer widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click({ force: true });
  cy.waitUntil(
    () =>
      cy.get('body').then(($body) => {
        const element = $body.find('div[class^="MuiAlert-message"]');

        return element.length > 0 && element.is(':visible');
      }),
    {
      errorMsg: 'The element is not visible',
      interval: 2000,
      timeout: 50000
    }
  ).then((isVisible) => {
    if (!isVisible) {
      throw new Error('The element is not visible');
    }
  });
});

Then("the Clock timer widget is added in the dashboard's layout", () => {
  cy.get('div[class$="clockInformation"]').should('be.visible');
  cy.get('div[class$="clockLabel"]').should('be.visible');
});

Given('a dashboard with a Clock Timer widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, clockTimerWidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
});

When(
  'the dashboard administrator updates the time format by selecting a new one',
  () => {
    cy.getByTestId({ testId: 'Select time format' }).click();
    cy.contains('French (France) (fr-FR)').click();
  }
);

Then(
  'the time format in the widget should be updated to reflect the new format',
  () => {
    cy.get('div[class$="clockLabel"] p')
      .eq(2)
      .invoke('text')
      .then((clockText) => {
        cy.log(clockText);

        const now = new Date();

        // Add 1 hours to the current time
        now.setHours(now.getHours() + 1);

        // Format the hours and minutes with leading zeros if needed
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');

        const currentTime = `${hours}:${minutes}`;

        expect(clockText.trim()).to.equal(currentTime);
      });
  }
);

When(
  'the dashboard administrator updates the time zone by selecting a new one',
  () => {
    cy.getByTestId({ testId: 'Select time zone' }).click();
    cy.contains('Europe/Monaco').click();
  }
);

Then('timezone should be updating in the widget', () => {
  cy.get('p[class$="timezone"]')
    .eq(1)
    .invoke('text')
    .then((timezoneText) => {
      cy.log('Text inside timezone element:', timezoneText);
      expect(timezoneText.trim()).to.equal('Europe/Monaco');
    });
});

When(
  'the dashboard administrator changes the display setting of the Clock Timer widget from "Clock" to "Timer"',
  () => {
    cy.getByLabel({ label: 'Timer' }).click();
    cy.get('div[class$="clockLabel"] p')
      .eq(2)
      .invoke('text')
      .then((clockText) => {
        console.log(clockText);
        expect(clockText.trim()).to.equal('00:00:00');
      });
  }
);

Then('the countdown input should be displayed', () => {
  cy.getByLabel({ label: 'Timer' }).should('be.visible');
});

When('the dashboard administrator updates the countdown input', () => {
  cy.getByLabel({ label: 'Timer' }).click();
  cy.getByTestId({ testId: 'CalendarIcon' }).click();
  cy.getByLabel({ label: '11 hours' }).click({ force: true });
  cy.getByLabel({ label: '55 minutes' }).click({ force: true });
  cy.contains('OK').click({ force: true });
});

Then('the widget should display the "Timer" format', () => {
  const today = new Date();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  const year = today.getFullYear();
  const formattedDate = `${month}/${day}/${year}`;
  console.log(formattedDate);
  cy.get('p[class$="date"]')
    .eq(1)
    .invoke('text')
    .then((dateText) => {
      console.log('Text inside date element:', dateText);
      expect(dateText.trim()).to.match(
        new RegExp(`Ends at: ${formattedDate} 11:55 (AM|PM)`)
      );
    });
});

When(
  'the dashboard administrator user duplicates the Clock timer widget',
  () => {
    cy.editDashboard(dashboards.default.name);
    cy.get('p[class$="timezone"]').should('be.visible');
    cy.get('div[class$="clockLabel"] p').should('be.visible');
    cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click({ force: true });
  }
);

Then('a second Clock timer widget is displayed on the dashboard', () => {
  cy.get('p[class$="date"]').eq(1).should('be.visible');
  cy.get('div[class$="clockLabel"] p').eq(1).should('be.visible');
});

When(
  'the dashboard administrator updates the background color of the Clock Timer widget',
  () => {
    cy.getByTestId({ testId: 'color selector' }).click();
    cy.getByTestId({ testId: 'color-chip-#076059' }).click();
  }
);

Then(
  'the background color of the Clock Timer widget should reflect the updated color',
  () => {
    cy.get('div[class$="background"]')
      .eq(1)
      .should('have.css', 'background-color', 'rgb(7, 96, 89)');
  }
);
