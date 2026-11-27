import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  buildEngineLogLineRegExp,
  buildEngineLogOptionsCommand,
  buildStartEngineWithLogFileCommand,
  engineArgumentLogFile,
  engineDefaultLogFile,
  parseEngineOptions
} from '../common';

// Message of the config logger (info) once the Engine runs its event loop
const engineStartedMessage = 'Event loop start';
const pollingOptions = { interval: 5_000, timeout: 120_000 };

let engineOptions: Record<string, string> = {};

beforeEach(() => {
  cy.startContainers();

  engineOptions = {};
});

afterEach(() => {
  cy.stopContainers();
});

const readArgumentLogFile = (): Cypress.Chainable<string> =>
  cy
    .execInContainer(
      {
        command: `cat ${engineArgumentLogFile} 2> /dev/null || true`,
        name: 'web'
      },
      { log: false }
    )
    .then(({ output }) => cy.wrap(String(output), { log: false }));

const enginePid = (): Cypress.Chainable<number> =>
  cy
    .execInContainer({ command: 'pgrep -x centengine', name: 'web' })
    .then(({ output }) => {
      const pids = output.trim().split('\n');

      expect(pids, 'centengine processes').to.have.length(1);

      return cy.wrap(Number.parseInt(pids[0], 10), { log: false });
    });

Given('a poller configured with the default log configuration', () => {
  // The Central, as installed and exported: logs v2 in a file, with the pid
  cy.execInContainer({
    command: buildEngineLogOptionsCommand(),
    name: 'web'
  }).then(({ output }) => {
    engineOptions = parseEngineOptions(output);

    expect(engineOptions.log_v2_enabled, 'log_v2_enabled').to.eq('1');
    expect(engineOptions.log_v2_logger, 'log_v2_logger').to.eq('file');
    expect(engineOptions.log_file, 'log_file').to.eq(engineDefaultLogFile);
    expect(engineOptions.log_pid, 'log_pid').to.match(/^[01]$/);
  });
});

When('the poller is started with a log file path given as an argument', () => {
  cy.execInContainer({
    command: buildStartEngineWithLogFileCommand(),
    name: 'web'
  });
});

Then('the poller starts with no error', () => {
  cy.waitUntil(
    () =>
      readArgumentLogFile().then((log) => log.includes(engineStartedMessage)),
    {
      ...pollingOptions,
      errorMsg: `"${engineStartedMessage}" is not logged in ${engineArgumentLogFile}`
    }
  );

  enginePid();
  readArgumentLogFile().then((log) => {
    expect(log, 'configuration and process errors').to.not.match(
      /\] \[(config|process)\] \[(error|critical)\]/
    );
  });
});

Then('the log file is written at the given path', () => {
  cy.execInContainer({
    command: `stat -c '%U %s' ${engineArgumentLogFile}`,
    name: 'web'
  }).then(({ output }) => {
    const [owner, size] = output.trim().split(' ');

    expect(owner, 'owner of the log file').to.eq('centreon-engine');
    expect(Number(size), 'size of the log file').to.be.greaterThan(0);
  });
});

Then('the log file options of the configuration are applied', () => {
  enginePid().then((pid) => {
    const lineFormat = buildEngineLogLineRegExp({
      filePosition: engineOptions.log_file_line === '1',
      pid: engineOptions.log_pid === '1' ? pid : null
    });

    readArgumentLogFile().then((log) => {
      // A message may span several lines: check the lines that start one
      const entries = log.split('\n').filter((line) => line.startsWith('['));

      expect(entries.length, 'log entries').to.be.greaterThan(0);
      expect(
        entries.filter((entry) => !lineFormat.test(entry)),
        `log entries not matching ${lineFormat}`
      ).to.deep.eq([]);
    });
  });
});
