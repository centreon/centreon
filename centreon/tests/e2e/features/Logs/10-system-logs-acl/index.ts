import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { parseCsv } from '../../../commons';
import {
  buildCentralPollerQuery,
  buildEngineLogOutputs,
  buildInsertEngineLogsQuery,
  buildInsertPollerQuery,
  buildInsertRunningInstanceQuery,
  buildInstanceIdQuery,
  buildPollerIdQuery,
  runQueryButton,
  sortedCopy
} from '../common';

const marker = 'rbt-syslog';
const allowedPoller = 'rbt-syslog-poller';
const logsPerPoller = 3;
const userName = 'rbt-syslog-user';
const userPassword = 'Centreon!2021RbtSyslog';
const aclGroup = `${userName}-group`;
const aclMenu = `${userName}-menus`;
const aclResource = `${userName}-resources`;
// The messages are inserted a few minutes before the search: a relative
// period keeps them in range whatever the timezones of the browser and PHP
const searchPeriodInSeconds = '86400';

// Restrictions of the access group, by their wording in the scenario
const pollerRestrictions: Record<string, boolean> = {
  'an ACL restricted to some pollers': true,
  'no poller restriction in the ACL': false
};

let logsByPoller: Record<string, Array<string>> = {};
let allowedPollerInstanceId = '';
let screenOutputs: Array<string> = [];
let exportedFile = '';

beforeEach(() => {
  cy.startContainers();

  logsByPoller = {};
  allowedPollerInstanceId = '';
  screenOutputs = [];
  exportedFile = '';

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
    pathname: INTERCEPTORS.pages.event_logs_search,
    query: { engine: 'true', output: marker }
  }).as('engineLogsSearch');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const engineLogRows = (): Cypress.Chainable<JQuery<HTMLElement>> =>
  cy.getIframeBody().find('#logView4xml table.ListTable tr:not(.ListHeader)');

// Output column of the engine logs list (date, time, poller, output)
const displayedOutputs = (): Cypress.Chainable<Array<string>> =>
  engineLogRows().then((rows) =>
    cy.wrap(
      rows
        .toArray()
        .map((row) => Cypress.$(row).find('td').eq(3).text().trim()),
      { log: false }
    )
  );

const checkDisplayedOutputs = (expected: Array<string>): void => {
  engineLogRows().should('have.length', expected.length);
  displayedOutputs().then((outputs) => {
    expect(sortedCopy(outputs)).to.deep.eq(sortedCopy(expected));
    screenOutputs = outputs;
  });
};

Given('engine logs exist for several pollers over a given period', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildCentralPollerQuery()
  }).then(([rows]) => {
    expect(rows, 'central poller').to.have.length(1);

    const centralName = String(rows[0].name);

    logsByPoller[centralName] = buildEngineLogOutputs(
      marker,
      centralName,
      logsPerPoller
    );
  });

  // A second poller, known by the configuration and the real-time database
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildInsertPollerQuery(allowedPoller)
  });
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildPollerIdQuery(allowedPoller)
  }).then(([rows]) => {
    expect(rows, `poller ${allowedPoller}`).to.have.length(1);

    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildInsertRunningInstanceQuery(Number(rows[0].id), allowedPoller)
    });
  });
  cy.requestOnDatabase({
    database: 'centreon_storage',
    query: buildInstanceIdQuery(allowedPoller)
  }).then(([rows]) => {
    expect(rows, `instance ${allowedPoller}`).to.have.length(1);

    allowedPollerInstanceId = String(rows[0].instance_id);
  });

  cy.wrap(null).then(() => {
    logsByPoller[allowedPoller] = buildEngineLogOutputs(
      marker,
      allowedPoller,
      logsPerPoller
    );

    Object.entries(logsByPoller).forEach(([instanceName, outputs]) => {
      cy.requestOnDatabase({
        database: 'centreon_storage',
        query: buildInsertEngineLogsQuery(instanceName, outputs)
      });
    });
  });
});

Given(
  /^a non-admin user with menu access to Event Logs and System Logs and (.+)$/,
  (restriction: string) => {
    const restricted = pollerRestrictions[restriction];

    if (restricted === undefined) {
      throw new Error(`Unknown poller restriction "${restriction}"`);
    }

    clapi(
      'ADD',
      'CONTACT',
      `${userName};${userName};${userName}@localhost;${userPassword};0;1;en_US;local`
    );
    clapi('ADD', 'ACLGROUP', `${aclGroup};${aclGroup}`);
    clapi('SETCONTACT', 'ACLGROUP', `${aclGroup};${userName}`);

    // Monitoring > Event Logs with its children: Event Logs and System Logs
    cy.addACLMenu({ name: aclMenu, rule: ['Monitoring', 'Event Logs'] });
    clapi('ADDMENU', 'ACLGROUP', `${aclGroup};${aclMenu}`);

    // The poller rights are read from the resources accesses of the user
    clapi('ADD', 'ACLRESOURCE', `${aclResource};${aclResource}`);
    clapi('GRANT_HOST', 'ACLRESOURCE', `${aclResource};*`);
    if (restricted) {
      clapi(
        'ADDFILTER_INSTANCE',
        'ACLRESOURCE',
        `${aclResource};${allowedPoller}`
      );
    }
    clapi('ADDRESOURCE', 'ACLGROUP', `${aclGroup};${aclResource}`);
    clapi('RELOAD', 'ACL', '');

    // Compute the centreon_acl table now rather than waiting for the cron
    cy.applyAcl();
  }
);

Given('the user is logged in', () => {
  cy.clearCookies();
  cy.loginByCredentials({ login: userName, password: userPassword });
});

When(
  'the user opens the System Logs page on a period containing engine logs',
  () => {
    cy.visit(PAGES.monitoring.systemLogsLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', '#output');

    cy.getIframeBody()
      .find('select[name="period"]')
      .select(searchPeriodInSeconds);
    cy.getIframeBody().find('#output').clear().type(marker);
    runQueryButton().click();
    cy.wait('@engineLogsSearch');
  }
);

Then('the engine logs of {} are listed', (visiblePollers: string) => {
  const expectedPollers: Record<string, Array<string>> = {
    'all pollers': Object.keys(logsByPoller),
    'the allowed pollers': [allowedPoller]
  };

  if (expectedPollers[visiblePollers] === undefined) {
    throw new Error(`Unknown visible pollers "${visiblePollers}"`);
  }

  checkDisplayedOutputs(
    expectedPollers[visiblePollers].flatMap((poller) => logsByPoller[poller])
  );
});

When('the user selects an allowed poller in the poller tree', () => {
  cy.intercept({
    method: 'GET',
    pathname: INTERCEPTORS.pages.event_logs_search,
    query: { engine: 'true', id: allowedPollerInstanceId, output: marker }
  }).as('pollerLogsSearch');

  cy.getIframeBody()
    .find('select#poller_filter')
    .siblings('span.select2-container')
    .click();
  cy.getIframeBody()
    .find('.select2-results__option')
    .contains(allowedPoller)
    .click();
  cy.wait('@pollerLogsSearch');
});

Then('only the logs of that poller are listed', () => {
  checkDisplayedOutputs(logsByPoller[allowedPoller]);
});

When('the user exports the logs to CSV', () => {
  // The export opens a download in place of the page: keep the real file for
  // the checks, and send an empty one to the browser
  cy.intercept(
    {
      method: 'GET',
      pathname: INTERCEPTORS.pages.event_logs_export,
      query: { export: '1' }
    },
    (request) => {
      request.continue((response) => {
        exportedFile = String(response.body);
        response.send({ body: '' });
      });
    }
  ).as('exportRequest');

  cy.getIframeBody().find(`#logView4xml a[href*="'CSV'"]`).first().click();
  cy.wait('@exportRequest');
});

Then('the export contains the same rows as the screen', () => {
  cy.wrap(null).then(() => {
    // Metadata lines, then the header, then one line per message: the
    // messages are the lines holding the marker of the inserted outputs
    const exportedOutputs = parseCsv(exportedFile)
      .map((fields) => fields.find((field) => field.startsWith(`${marker} `)))
      .filter((field): field is string => field !== undefined);

    expect(screenOutputs).to.not.be.empty;
    expect(sortedCopy(exportedOutputs)).to.deep.eq(sortedCopy(screenOutputs));
  });
});
