import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { checkIfConfigurationIsExported } from '../../../commons';
import {
  breakSomePollers,
  checkIfConfigurationIsNotExported,
  checkIfMethodIsAppliedToPollers,
  clearCentengineLogs,
  getPoller,
  insertHost,
  insertPollerConfigUserAcl,
  removeFixtures,
  testHostName,
  waitPollerListToLoad
} from '../common';

let dateBeforeLogin: Date;

beforeEach(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-1.json'
  );
  cy.addCheckCommand({
    command: 'echo "Post command"',
    enableShell: true,
    name: 'post_command'
  });
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.generate_reload_pollers
  }).as('generateAndReloadPollers');
});

Given(
  'I am granted the rights to access the poller page and export the configuration',
  () => {
    dateBeforeLogin = new Date();

    clearCentengineLogs().then(() => {
      insertPollerConfigUserAcl();
    });
  }
);

Given('I am logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'user', loginViaApi: true });
});

Given('the platform is configured with some resources', () => {
  insertHost();
});

Given('some pollers are created', () => {
  getPoller('Central')
    .as('pollerId')
    .then(() => {
      cy.get('@pollerId').should('be.greaterThan', 0);
    });
});

Given('some post-generation commands are configured for each poller', () => {
  cy.get('@pollerId').then((pollerId) => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: 'DELETE FROM poller_command_relations'
    }).requestOnDatabase({
      database: 'centreon',
      query: `INSERT INTO poller_command_relations (poller_id, command_id, command_order) SELECT ${pollerId},c.command_id,1 FROM command c WHERE c.command_name = 'post_command'`
    });
  });
});

When('I visit the export configuration page', () => {
  cy.visit(PAGES.configuration.pollersLegacy)
    .wait('@getTimeZone')
    .then(() => {
      cy.url().should('include', '/centreon/main.php?p=60901');
    });
});

Then(
  'there is an indication that the configuration have changed on the listed pollers',
  () => {
    cy.wait(waitPollerListToLoad);

    cy.getIframeBody().find('form .list_one>td').eq(5).contains('Yes');
  }
);

When('I select some pollers', () => {
  cy.getIframeBody()
    .find('form input[name="select[1]"]')
    .check({ force: true });

  cy.getIframeBody()
    .find('form .list_one>td')
    .eq(1)
    .then((text) => cy.wrap(text.text()).as('pollerName'));
});

When('I click on the Export configuration button', () => {
  cy.getIframeBody().find('#exportConfigurationLink').click({ force: true });
});

Then('I am redirected to generate page', () => {
  cy.url().should('include', '/centreon/main.php?p=60902&poller=');
});

Then('the selected poller names are displayed', () => {
  cy.reload();
  cy.get<string>('@pollerName').then((pollerName) => {
    cy.getIframeBody()
      .find('form span[class="selection"]')
      .eq(0)
      .contains(pollerName);
  });
});

When('I select all action checkboxes', () => {
  // forced check because legacy checkbox are hidden
  cy.getIframeBody()
    .find('form input[name="gen"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="debug"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="move"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="restart"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="postcmd"]')
    .eq(0)
    .check({ force: true });
});

When('I select the {string} export method', (method: string) => {
  cy.getIframeBody()
    .find('form select[name="restart_mode"]')
    .eq(0)
    .select(method);
});

When('I click on the export button', () => {
  clearCentengineLogs()
    .getIframeBody()
    .find('form input[name="submit"]')
    .eq(0)
    .click();
});

Then('the configuration is generated on selected pollers', () => {
  cy.waitUntil(
    () => {
      return cy
        .get('iframe#main-content')
        .its('0.contentDocument.body')
        .find('div#console')
        .then((el) => {
          return el.find('label#progressPct:contains("100%")').length > 0;
        });
    },
    { timeout: 10000 }
  );

  checkIfConfigurationIsExported({ dateBeforeLogin, hostName: testHostName });
});

Then('the selected pollers are {string}', (pollerAction: string) => {
  checkIfMethodIsAppliedToPollers(pollerAction);

  cy.logout();

  cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

  removeFixtures();
});

Then('no poller names are displayed', () => {
  cy.get('iframe#main-content')
    .its('0.contentDocument.body')
    .find('form span[class="selection"]')
    .eq(0)
    .should('have.value', '');
});

Then(
  'an error message is displayed to inform that no poller is selected',
  () => {
    cy.getIframeBody()
      .find('form i[id="noSelectedPoller"]')
      .eq(0)
      .should('be.visible')
      .and('contain', 'Compulsory Poller');

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    removeFixtures();
  }
);

When('I click on the export configuration action and confirm', () => {
  cy.get('header').getByLabel({ label: 'Pollers', tag: 'button' }).click();

  cy.get('button[data-testid="Export configuration"]').click();

  cy.getByLabel({ label: 'Export & reload', tag: 'button' }).click();
});

Then('a success message is displayed', () => {
  cy.wait('@generateAndReloadPollers').then(() => {
    cy.contains('Configuration exported and reloaded').should('have.length', 1);
  });
});

Then('the configuration is generated on all pollers', () => {
  checkIfConfigurationIsExported({ dateBeforeLogin, hostName: testHostName });

  cy.logout();

  cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

  removeFixtures();
});

Given('broken pollers', () => {
  breakSomePollers();
});

Then('the configuration is not generated on selected pollers', () => {
  checkIfConfigurationIsNotExported();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.logoutViaAPI();
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('a remote poller is configured', () => {
  cy.visit(PAGES.configuration.pollersLegacy);
  cy.wait('@getNavigationList');
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('td', 'Poller-1');
});

When('the user duplicates the configured poller', () => {
  cy.getIframeBody()
    .contains('tr', 'Poller-1')
    .find('div.md-checkbox.md-checkbox-inline')
    .click();
  cy.getIframeBody()
    .find('button[name="duplicate_action"]')
    .invoke('attr', 'onclick', "javascript: { setO('m'); submit(); }");
  cy.getIframeBody().find('button[name="duplicate_action"]').click();
  cy.wait('@getTimeZone');
});

Then('a new disabled poller is created with identical properties', () => {
  cy.getIframeBody()
    .find('table tbody tr.row_disabled')
    .within(() => {
      cy.contains('td', 'Poller-1_1').should('exist');
      cy.contains('td', '10.30.2.55').should('exist');
    });
});

When('the user exports the configuration', () => {
  cy.exportConfig();
});

afterEach(() => {
  cy.stopContainers();
});
