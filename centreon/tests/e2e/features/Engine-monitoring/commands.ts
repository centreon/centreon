import {
  buildEngineInstanceQuery,
  buildEngineSettingsQuery,
  buildHostStatusQuery,
  buildInstallTestCheckCommand,
  buildLogLineCountCommand,
  buildLogLinesAfterCommand,
  buildServiceStatusQuery,
  buildSetTestCheckBehaviourCommand,
  buildWriteExternalCommand,
  hostCheckCommandName,
  pollerName,
  testCheckScript
} from './common';

interface EngineSettings {
  commandFile: string;
  logFile: string;
  name: string;
  retentionFile: string;
}

interface EngineProcess {
  pid: number;
  running: boolean;
}

type StatusRow = Record<string, string | number | null>;

interface WaitForServiceStatusProps {
  condition: (row: StatusRow) => boolean;
  description: string;
  host: string;
  service: string;
  timeout?: number;
}

interface WaitForHostStatusProps {
  condition: (row: StatusRow) => boolean;
  description: string;
  host: string;
  timeout?: number;
}

interface TestCheckBehaviour {
  exitCode: number;
  name: string;
  sleepSeconds?: number;
}

interface EngineParameter {
  parameter: string;
  value: string;
}

interface EngineLogSearch {
  fromLine: number;
  text: string;
  timeout?: number;
}

const pollingInterval = 5000;

Cypress.Commands.add('getEngineSettings', (): Cypress.Chainable => {
  return cy
    .requestOnDatabase({
      database: 'centreon',
      params: [pollerName],
      query: buildEngineSettingsQuery()
    })
    .then(([rows]) => {
      expect(rows, `Engine configuration of ${pollerName}`).to.have.length(1);

      return {
        commandFile: rows[0].command_file,
        logFile: rows[0].log_file,
        name: rows[0].nagios_name,
        retentionFile: rows[0].state_retention_file
      };
    });
});

Cypress.Commands.add(
  'setEngineParameter',
  ({ parameter, value }: EngineParameter): Cypress.Chainable => {
    return cy.getEngineSettings().then(({ name }: EngineSettings) =>
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'SETPARAM',
          object: 'ENGINECFG',
          values: `${name};${parameter};${value}`
        }
      })
    );
  }
);

// Installs the check script and the host check command that is always Up
Cypress.Commands.add('installTestCheckCommands', (): Cypress.Chainable => {
  return cy
    .execInContainer({ command: buildInstallTestCheckCommand(), name: 'web' })
    .then(() =>
      cy.addCheckCommand({
        command: testCheckScript,
        enableShell: false,
        name: hostCheckCommandName
      })
    );
});

Cypress.Commands.add(
  'addTestCheckCommand',
  (name: string): Cypress.Chainable => {
    return cy.addCheckCommand({
      command: `${testCheckScript} $SERVICEDESC$`,
      enableShell: false,
      name
    });
  }
);

Cypress.Commands.add(
  'setTestCheckBehaviour',
  ({
    exitCode,
    name,
    sleepSeconds = 0
  }: TestCheckBehaviour): Cypress.Chainable => {
    return cy.execInContainer({
      command: buildSetTestCheckBehaviourCommand(name, exitCode, sleepSeconds),
      name: 'web'
    });
  }
);

Cypress.Commands.add(
  'writeEngineExternalCommand',
  (command: string): Cypress.Chainable => {
    return cy.getEngineSettings().then(({ commandFile }: EngineSettings) =>
      cy.execInContainer({
        command: buildWriteExternalCommand(
          commandFile,
          command,
          Math.floor(Date.now() / 1000)
        ),
        name: 'web'
      })
    );
  }
);

Cypress.Commands.add('getEngineLogLineCount', (): Cypress.Chainable => {
  return cy.getEngineSettings().then(({ logFile }: EngineSettings) =>
    cy
      .execInContainer({
        command: buildLogLineCountCommand(logFile),
        name: 'web'
      })
      .then(({ output }) => Number.parseInt(output.trim(), 10))
  );
});

// Lines of centengine.log written after <fromLine> and containing <text>
Cypress.Commands.add(
  'getEngineLogLines',
  ({ fromLine, text }: EngineLogSearch): Cypress.Chainable => {
    return cy.getEngineSettings().then(({ logFile }: EngineSettings) =>
      cy
        .execInContainer({
          command: buildLogLinesAfterCommand(logFile, fromLine, text),
          name: 'web'
        })
        .then(({ output }) =>
          output.split('\n').filter((line: string) => line.trim() !== '')
        )
    );
  }
);

Cypress.Commands.add(
  'waitForEngineLog',
  ({ fromLine, text, timeout = 60000 }: EngineLogSearch): Cypress.Chainable => {
    let lines: Array<string> = [];

    return cy
      .waitUntil(
        () =>
          cy.getEngineLogLines({ fromLine, text }).then((found) => {
            lines = found;

            return found.length > 0;
          }),
        {
          errorMsg: `centengine.log has no line containing "${text}"`,
          interval: pollingInterval,
          timeout
        }
      )
      .then(() => lines);
  }
);

Cypress.Commands.add('getEngineProcess', (): Cypress.Chainable => {
  return cy
    .requestOnDatabase({
      database: 'centreon_storage',
      params: [pollerName],
      query: buildEngineInstanceQuery()
    })
    .then(([rows]) => {
      expect(rows, `Engine instance ${pollerName}`).to.have.length(1);

      return {
        pid: Number(rows[0].pid),
        running: Number(rows[0].running) === 1
      };
    });
});

// The Engine process of the poller is alive in the container
Cypress.Commands.add(
  'checkEngineProcessIsAlive',
  (pid: number): Cypress.Chainable => {
    return cy
      .execInContainer({ command: `cat /proc/${pid}/comm`, name: 'web' })
      .then(({ output }) => {
        expect(output.trim(), `process ${pid}`).to.eq('centengine');
      });
  }
);

// Restarts Engine and waits for Broker to receive the new instance
Cypress.Commands.add('restartEngine', (): Cypress.Chainable => {
  return cy.getEngineProcess().then(({ pid }: EngineProcess) =>
    cy
      .execInContainer({ command: 'systemctl restart centengine', name: 'web' })
      .then(() =>
        cy.waitUntil(
          () =>
            cy
              .getEngineProcess()
              .then(
                (current: EngineProcess) =>
                  current.running && current.pid !== pid
              ),
          {
            errorMsg: 'Engine did not start again after its restart',
            interval: pollingInterval,
            timeout: 120000
          }
        )
      )
  );
});

// Generates and deploys the configuration without reloading, then restarts
Cypress.Commands.add(
  'exportConfigurationAndRestartEngine',
  (): Cypress.Chainable => {
    return cy
      .executeActionViaClapi({
        bodyContent: { action: 'POLLERGENERATE', values: pollerName }
      })
      .executeActionViaClapi({
        bodyContent: { action: 'POLLERTEST', values: pollerName }
      })
      .executeActionViaClapi({
        bodyContent: { action: 'CFGMOVE', values: pollerName }
      })
      .restartEngine();
  }
);

Cypress.Commands.add(
  'getServiceStatus',
  (host: string, service: string): Cypress.Chainable => {
    return cy
      .requestOnDatabase({
        database: 'centreon_storage',
        params: [host, service],
        query: buildServiceStatusQuery()
      })
      .then(([rows]) => (rows.length > 0 ? rows[0] : null));
  }
);

Cypress.Commands.add(
  'waitForServiceStatus',
  ({
    condition,
    description,
    host,
    service,
    timeout = 120000
  }: WaitForServiceStatusProps): Cypress.Chainable => {
    let status: StatusRow | null = null;

    return cy
      .waitUntil(
        () =>
          cy.getServiceStatus(host, service).then((row: StatusRow | null) => {
            status = row;

            return row !== null && condition(row);
          }),
        {
          errorMsg: () =>
            `${host} / ${service} is not ${description}: ${JSON.stringify(status)}`,
          interval: pollingInterval,
          timeout
        }
      )
      .then(() => status);
  }
);

Cypress.Commands.add(
  'waitForHostStatus',
  ({
    condition,
    description,
    host,
    timeout = 120000
  }: WaitForHostStatusProps): Cypress.Chainable => {
    let status: StatusRow | null = null;

    return cy
      .waitUntil(
        () =>
          cy
            .requestOnDatabase({
              database: 'centreon_storage',
              params: [host],
              query: buildHostStatusQuery()
            })
            .then(([rows]) => {
              status = rows.length > 0 ? rows[0] : null;

              return status !== null && condition(status);
            }),
        {
          errorMsg: () =>
            `${host} is not ${description}: ${JSON.stringify(status)}`,
          interval: pollingInterval,
          timeout
        }
      )
      .then(() => status);
  }
);

declare global {
  // biome-ignore lint/style/noNamespace: required for Cypress augmentation
  namespace Cypress {
    interface Chainable {
      addTestCheckCommand: (name: string) => Cypress.Chainable;
      checkEngineProcessIsAlive: (pid: number) => Cypress.Chainable;
      exportConfigurationAndRestartEngine: () => Cypress.Chainable;
      getEngineLogLineCount: () => Cypress.Chainable<number>;
      getEngineLogLines: (
        props: EngineLogSearch
      ) => Cypress.Chainable<Array<string>>;
      getEngineProcess: () => Cypress.Chainable<EngineProcess>;
      getEngineSettings: () => Cypress.Chainable<EngineSettings>;
      getServiceStatus: (
        host: string,
        service: string
      ) => Cypress.Chainable<StatusRow | null>;
      installTestCheckCommands: () => Cypress.Chainable;
      restartEngine: () => Cypress.Chainable;
      setEngineParameter: (props: EngineParameter) => Cypress.Chainable;
      setTestCheckBehaviour: (props: TestCheckBehaviour) => Cypress.Chainable;
      waitForEngineLog: (
        props: EngineLogSearch
      ) => Cypress.Chainable<Array<string>>;
      waitForHostStatus: (
        props: WaitForHostStatusProps
      ) => Cypress.Chainable<StatusRow>;
      waitForServiceStatus: (
        props: WaitForServiceStatusProps
      ) => Cypress.Chainable<StatusRow>;
      writeEngineExternalCommand: (command: string) => Cypress.Chainable;
    }
  }
}
