import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  assertPollerIsRunning,
  brokerLoggerLevel,
  buildBrokerLogLevelQuery,
  buildBrokerModuleQuery,
  buildEngineConfigurationQuery,
  buildInstanceProcessQuery,
  getPoller,
  pidColumnIndex,
  waitPollerListToLoad
} from '../common';

const pollerName = 'Central';
// Notice of the pollers list about the "Conf Changed" column
const confChangedNotice =
  'Only services, servicegroups, hosts and hostgroups are taken in account in order to calculate this status.';
const exportTimeout = 120_000;
const restartTimeout = 120_000;
const pollingInterval = 5_000;
// APPLYCFG reloads Broker a few seconds after Engine
const brokerReloadWaitingTime = 15_000;

let pollerId = 0;
let initialPid = 0;
let newPid = 0;
let engineConfiguration = { file: '', id: 0 };
let brokerModule = { file: '', id: 0 };

beforeEach(() => {
  cy.startContainers();

  pollerId = 0;
  initialPid = 0;
  newPid = 0;
  engineConfiguration = { file: '', id: 0 };
  brokerModule = { file: '', id: 0 };

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

// Process id of a running Engine, 0 while it is not running
const runningPid = (): Cypress.Chainable<number> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildInstanceProcessQuery(pollerName)
    })
    .then(([rows]) =>
      cy.wrap(
        rows.length === 1 && Number(rows[0].running) === 1
          ? Number(rows[0].pid)
          : 0,
        { log: false }
      )
    );

const visitPollersList = (): void => {
  cy.visit(PAGES.configuration.pollersLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', `td:contains("${pollerName}")`);
};

Given('an administrator is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given('a poller is declared, exported and running', () => {
  getPoller(pollerName).then((id) => {
    pollerId = Number(id);
  });

  cy.applyPollerConfiguration(pollerName);
  cy.wait(brokerReloadWaitingTime);

  cy.waitUntil(() => runningPid().then((pid) => pid > 0), {
    errorMsg: `${pollerName} is not running`,
    interval: pollingInterval,
    timeout: restartTimeout
  });
  runningPid().then((pid) => {
    initialPid = pid;
  });
});

When(
  "the user sets the service check timeout of the poller's Engine configuration to {int}",
  (timeout: number) => {
    cy.then(() =>
      cy
        .requestOnDatabase({
          database: 'centreon',
          query: buildEngineConfigurationQuery(pollerId)
        })
        .then(([rows]) => {
          expect(rows, `Engine configuration of ${pollerName}`).to.have.length(
            1
          );

          engineConfiguration = {
            file: `${String(rows[0].cfg_dir).replace(/\/$/, '')}/${rows[0].cfg_file}`,
            id: Number(rows[0].nagios_id)
          };
        })
    );

    cy.then(() => {
      cy.visit(
        `${PAGES.configuration.engineConfigurationLegacy}&o=c&nagios_id=${engineConfiguration.id}`
      );
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="nagios_name"]');
    // The field sits in a tab that is not displayed
    cy.getIframeBody()
      .find('input[name="service_check_timeout"]')
      .clear({ force: true })
      .type(String(timeout), { force: true });
    cy.getIframeBody()
      .find('input[name="submitC"]')
      .eq(0)
      .click({ force: true });
    cy.wait('@getTimeZone');

    cy.then(() =>
      cy
        .requestOnDatabase({
          database: 'centreon',
          query: buildEngineConfigurationQuery(pollerId)
        })
        .then(([rows]) => {
          expect(Number(rows[0].service_check_timeout), 'saved timeout').to.eq(
            timeout
          );
        })
    );
  }
);

When(
  "the user sets the {string} log level of the poller's Broker module to {string}",
  (logger: string, level: string) => {
    cy.then(() =>
      cy
        .requestOnDatabase({
          database: 'centreon',
          query: buildBrokerModuleQuery(pollerId)
        })
        .then(([rows]) => {
          expect(rows, `Broker module of ${pollerName}`).to.have.length(1);

          brokerModule = {
            file: `${String(rows[0].cfg_path).replace(/\/$/, '')}/${rows[0].config_filename}`,
            id: Number(rows[0].config_id)
          };
        })
    );

    cy.then(() => {
      cy.visit(
        `${PAGES.configuration.brokerConfigurationLegacy}&o=c&id=${brokerModule.id}`
      );
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="name"]');
    cy.getIframeBody()
      .find(`select[name="log_${logger}"]`)
      .select(level, { force: true });
    cy.getIframeBody()
      .find('input[name="submitC"]')
      .eq(0)
      .click({ force: true });
    cy.wait('@getTimeZone');

    cy.then(() =>
      cy
        .requestOnDatabase({
          database: 'centreon',
          query: buildBrokerLogLevelQuery(brokerModule.id, logger)
        })
        .then(([rows]) => {
          expect(rows, `saved ${logger} log level`).to.have.length(1);
          expect(rows[0].level).to.eq(level);
        })
    );
  }
);

Then(
  'the pollers list states that only hosts, services and their groups raise the "Conf changed" flag',
  () => {
    visitPollersList();
    cy.getIframeBody().contains('td', 'Conf Changed').should('exist');
    cy.getIframeBody().contains(confChangedNotice).should('exist');
  }
);

When(
  'the user exports the poller configuration with the generate, debug, move and restart steps',
  () => {
    cy.then(() => {
      cy.visit(
        `${PAGES.configuration.exportConfigurationLegacy}&poller=${pollerId}`
      );
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'form input[name="submit"]');
    cy.getIframeBody()
      .find('form span[class="selection"]')
      .eq(0)
      .should('contain', pollerName);

    ['gen', 'debug', 'move', 'restart'].forEach((step) => {
      cy.getIframeBody()
        .find(`form input[name="${step}"]`)
        .eq(0)
        .check({ force: true });
    });
    cy.getIframeBody()
      .find('form input[name="postcmd"]')
      .then((postcmd) => {
        if (postcmd.length > 0) {
          cy.wrap(postcmd.eq(0)).uncheck({ force: true });
        }
      });
    cy.getIframeBody()
      .find('form select[name="restart_mode"]')
      .eq(0)
      .select('Restart');
    cy.getIframeBody().find('form input[name="submit"]').eq(0).click();
  }
);

Then('the export log reports no error', () => {
  cy.waitUntil(
    () =>
      cy
        .getIframeBody()
        .find('div#console')
        .then(
          (exportConsole) =>
            exportConsole.find('label#progressPct:contains("100%")').length > 0
        ),
    {
      errorMsg: 'The export did not reach 100%',
      interval: 1_000,
      timeout: exportTimeout
    }
  );

  cy.getIframeBody()
    .find('#consoleContent')
    .invoke('text')
    .then((text) => {
      expect(text, 'export steps').to.contain('OK');
      expect(text, 'export steps').not.to.contain('NOK');
    });
  cy.getIframeBody().find('#error_log').children().should('have.length', 0);
});

Then(
  "the poller's centengine.cfg contains the service check timeout {int}",
  (timeout: number) => {
    cy.then(() =>
      cy
        .execInContainer({
          command: `cat ${engineConfiguration.file}`,
          name: 'web'
        })
        .then(({ output }) => {
          const lines = output.split('\n').map((line) => line.trim());

          expect(
            lines.filter((line) => line.startsWith('service_check_timeout=')),
            `service_check_timeout of ${engineConfiguration.file}`
          ).to.deep.eq([`service_check_timeout=${timeout}`]);
        })
    );
  }
);

Then(
  "the poller's Broker module file contains the {string} log level {string}",
  (logger: string, level: string) => {
    cy.then(() =>
      cy
        .execInContainer({ command: `cat ${brokerModule.file}`, name: 'web' })
        .then(({ output }) => {
          expect(
            brokerLoggerLevel(output, logger),
            `${logger} logger of ${brokerModule.file}`
          ).to.eq(level);
        })
    );
  }
);

Then('the poller is displayed as running with a new PID', () => {
  cy.waitUntil(
    () => runningPid().then((pid) => pid > 0 && pid !== initialPid),
    {
      errorMsg: `${pollerName} was not restarted`,
      interval: pollingInterval,
      timeout: restartTimeout
    }
  );
  runningPid().then((pid) => {
    newPid = pid;
  });

  visitPollersList();
  assertPollerIsRunning(pollerName);
  cy.wait(waitPollerListToLoad);
  cy.then(() => {
    cy.getIframeBody()
      .contains('td', pollerName)
      .parent('tr')
      .find('td')
      .eq(pidColumnIndex)
      .invoke('text')
      .then((text) => {
        expect(Number(text.trim()), 'displayed PID').to.eq(newPid);
        expect(Number(text.trim()), 'displayed PID').not.to.eq(initialPid);
      });
  });
});
