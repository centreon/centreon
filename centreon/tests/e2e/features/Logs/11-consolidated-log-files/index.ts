import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { apiActionV1 } from '../../../commons';
import {
  buildFilesContainingValuesCommand,
  buildReadChannelFilesCommand,
  buildTruncateLogFilesCommand,
  buildWrittenLegacyArtifactsCommand,
  centreonLogDirectory,
  channelFileNamePattern,
  correlationIdPattern,
  logLinePattern,
  parseChannelFiles
} from '../common';

interface Credentials {
  login: string;
  password: string;
}

// APP_ENV of the web image
const environment = 'prod';
const accessLogFile = `${centreonLogDirectory}/${environment}.access.log`;
const failedLoginMessage = 'Authentication failed for';
// Request paths of the two login entry points, kept by the "url" extra field
const legacyLoginPath = 'api/index.php';
const kernelLoginPath = 'authentication/providers/configurations/local';

let admin: Credentials = { login: '', password: '' };
let wrongPassword = '';

beforeEach(() => {
  cy.startContainers();

  admin = { login: '', password: '' };
  wrongPassword = `Rbt-Wrong-${Date.now()}`;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');

  cy.fixture('users/admin.json').then((credentials: Credentials) => {
    admin = credentials;
  });
});

afterEach(() => {
  cy.stopContainers();
});

const readChannelFiles = () =>
  cy
    .execInContainer({
      command: buildReadChannelFilesCommand(environment),
      name: 'web'
    })
    .then(({ output }) =>
      cy.wrap(parseChannelFiles(String(output)), { log: false })
    );

const failedLoginLines = (): Cypress.Chainable<Array<string>> =>
  readChannelFiles().then((files) => {
    const accessLog = files.find(({ file }) => file === accessLogFile);

    expect(accessLog, accessLogFile).to.not.eq(undefined);

    return cy.wrap(
      (accessLog?.lines ?? []).filter((line) =>
        line.includes(failedLoginMessage)
      ),
      { log: false }
    );
  });

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('the log directory of the central is empty of previous entries', () => {
  cy.execInContainer({ command: buildTruncateLogFilesCommand(), name: 'web' });
});

Given('the administrator has opened a legacy configuration page', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
});

Given('the administrator has opened a page served by the new kernel', () => {
  cy.visit(PAGES.configuration.authentication);
  cy.get('div[role="tablist"]').should('be.visible');
});

When('a user fails to log in with a wrong password', () => {
  // Login form of the web interface, handled by the new kernel
  cy.request({
    body: { login: admin.login, password: wrongPassword },
    failOnStatusCode: false,
    log: false,
    method: 'POST',
    url: INTERCEPTORS.api.local_authentication
  })
    .its('status')
    .should('eq', 401);

  // API v1 entry point, a legacy script
  cy.request({
    body: { password: wrongPassword, username: admin.login },
    failOnStatusCode: false,
    form: true,
    log: false,
    method: 'POST',
    url: `${apiActionV1}?action=authenticate`
  })
    .its('status')
    .should('eq', 401);
});

Then(
  'the log files are located in the Centreon log directory with the environment and channel in their names',
  () => {
    readChannelFiles().then((files) => {
      expect(files.map(({ file }) => file)).to.include(accessLogFile);

      files.forEach(({ file }) => {
        const name = file.replace(`${centreonLogDirectory}/`, '');

        expect(name, 'channel file name').to.match(channelFileNamePattern);
        expect(name.startsWith(`${environment}.`), name).to.eq(true);
      });
    });
  }
);

Then(
  'no legacy error log file nor new-kernel log subdirectory is written',
  () => {
    cy.execInContainer({
      command: buildWrittenLegacyArtifactsCommand(),
      name: 'web'
    }).then(({ output }) => {
      expect(String(output).trim(), 'written legacy log artifacts').to.eq('');
    });
  }
);

Then(
  'the legacy and new-kernel entries share the same line format and RFC3339 timestamps',
  () => {
    failedLoginLines().then((lines) => {
      expect(
        lines.some((line) => line.includes(legacyLoginPath)),
        'failed login entry of the legacy entry point'
      ).to.eq(true);
      expect(
        lines.some((line) => line.includes(kernelLoginPath)),
        'failed login entry of the new kernel'
      ).to.eq(true);
    });

    readChannelFiles().then((files) => {
      const lines = files.flatMap(({ lines: fileLines }) => fileLines);

      expect(lines).to.not.be.empty;
      lines.forEach((line) => {
        expect(line).to.match(logLinePattern);
      });
    });
  }
);

Then('the failed login is logged as a warning in the access log', () => {
  failedLoginLines().then((lines) => {
    expect(lines.length).to.be.at.least(2);
    lines.forEach((line) => {
      expect(line).to.match(/\] authentication\.WARNING: /);
      expect(line).to.contain(`'${admin.login}'`);
    });
  });
});

Then('each entry carries a correlation id', () => {
  readChannelFiles().then((files) => {
    const lines = files.flatMap(({ lines: fileLines }) => fileLines);

    expect(lines).to.not.be.empty;
    lines.forEach((line) => {
      expect(line).to.match(correlationIdPattern);
    });
  });
});

Then('no sensitive value appears in clear text in the log entries', () => {
  // Positive control: the failed logins are in the files searched below
  failedLoginLines().should('not.be.empty');

  // The command output is read: execInContainer hides it when not logged
  cy.execInContainer({
    command: buildFilesContainingValuesCommand([wrongPassword, admin.password]),
    name: 'web'
  }).then(({ output }) => {
    expect(String(output).trim(), 'files holding a password').to.eq('');
  });
});
