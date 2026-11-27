import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  backupOptionsQuery,
  backupScript,
  buildArchiveListCommand,
  buildBackupFiles,
  buildBackupOptions,
  buildCreateDatabaseQuery,
  buildCronLineRunCommand,
  buildDumpLastLineCommand,
  buildRestoreDumpCommand,
  buildSetOptionQuery,
  centreonConfigurationFiles,
  centreonCronFile,
  configuredHostsQuery,
  dayOfWeekCommand,
  dumpCompletedPattern,
  restoreDatabase,
  todayCommand
} from '../common';

let backupDirectory = '';
let backupFiles: Record<string, string> = {};
let hostName = '';

beforeEach(() => {
  cy.startContainers();

  backupDirectory = '';
  backupFiles = {};
  hostName = '';
});

afterEach(() => {
  cy.stopContainers();
});

const getHostNames = (database: string): Cypress.Chainable<Array<string>> =>
  cy
    .requestOnDatabase({ database, query: configuredHostsQuery })
    .then(([rows]) => rows.map(({ host_name }) => String(host_name)));

Given(
  'a central whose backup is enabled for today with the Dump type and the configuration files',
  () => {
    cy.execInContainer({ command: dayOfWeekCommand, name: 'web' }).then(
      ({ output }) => {
        const options = buildBackupOptions(output.trim());

        Object.entries(options).forEach(([key, value]) => {
          cy.requestOnDatabase({
            database: 'centreon',
            query: buildSetOptionQuery(key, value)
          });
        });

        cy.requestOnDatabase({
          database: 'centreon',
          query: backupOptionsQuery
        }).then(([rows]) => {
          const stored = Object.fromEntries(
            rows.map(({ key, value }) => [String(key), String(value ?? '')])
          );

          Object.entries(options).forEach(([key, value]) => {
            expect(stored[key], key).to.eq(value);
          });

          backupDirectory = stored.backup_backup_directory;
          expect(backupDirectory, 'backup directory').to.not.eq('');
        });
      }
    );
  }
);

Given('the host {word} exists in the configuration', (host: string) => {
  hostName = host;

  cy.addHost({ name: host, template: 'generic-host' });

  getHostNames('centreon').should('include', host);
});

When('the backup script of the nightly cron runs', () => {
  // A non-zero exit code of the cron command fails the step
  cy.execInContainer({
    command: buildCronLineRunCommand(centreonCronFile, backupScript),
    name: 'web'
  });

  cy.execInContainer({ command: todayCommand, name: 'web' }).then(
    ({ output }) => {
      backupFiles = buildBackupFiles(backupDirectory, output.trim());
    }
  );
});

Then(
  "the backup directory holds today's centreon and centreon_storage dumps and the central and centreon-engine archives",
  () => {
    cy.execInContainer({
      command: `ls -1 ${backupDirectory}`,
      name: 'web'
    }).then(({ output }) => {
      const files = output
        .split('\n')
        .map((file) => `${backupDirectory}/${file.trim()}`);

      Object.entries(backupFiles).forEach(([kind, file]) => {
        expect(files, kind).to.include(file);
      });
    });
  }
);

Then(
  'each dump is a complete gzip file ending with the dump completion line',
  () => {
    [backupFiles.centreonDump, backupFiles.centreonStorageDump].forEach(
      (file) => {
        cy.execInContainer({
          command: buildDumpLastLineCommand(file),
          name: 'web'
        }).then(({ output }) => {
          expect(output.trim(), `last line of ${file}`).to.match(
            dumpCompletedPattern
          );
        });
      }
    );
  }
);

Then(
  'the centreon dump restores into an empty database with the same hosts as the platform',
  () => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildCreateDatabaseQuery(restoreDatabase)
    });

    cy.execInContainer({
      command: buildRestoreDumpCommand(
        backupFiles.centreonDump,
        restoreDatabase
      ),
      name: 'web'
    });

    getHostNames('centreon').then((platformHosts) => {
      expect(platformHosts, 'hosts of the platform').to.include(hostName);

      getHostNames(restoreDatabase).then((restoredHosts) => {
        expect(restoredHosts, 'hosts of the restored database').to.deep.eq(
          platformHosts
        );
      });
    });
  }
);

Then('the central archive contains the Centreon configuration files', () => {
  cy.execInContainer({
    command: buildArchiveListCommand(backupFiles.centralArchive),
    name: 'web'
  }).then(({ output }) => {
    const entries = output.split('\n').map((entry) => entry.trim());

    centreonConfigurationFiles.forEach((file) => {
      expect(entries, `${backupFiles.centralArchive} entries`).to.include(file);
    });
  });
});
