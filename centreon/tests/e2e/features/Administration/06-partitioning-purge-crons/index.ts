import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  applyConfigurationViaClapi,
  checkHostsAreMonitored
} from '../../../commons';
import {
  buildCronLineRunCommand,
  buildInsertLogLineQuery,
  buildLineCountCommand,
  buildLinesAfterCommand,
  buildLogLineCountQuery,
  buildPartitioningOptionsQueries,
  buildPartitionRowCountQuery,
  buildPartitionsQuery,
  buildPrependPartitionQuery,
  forwardPartitionNameCommand,
  logsRetentionCheckQuery,
  partitionedTables,
  partitioningLog,
  partitioningOptionsCheckQuery,
  partitioningScript,
  purgeLog,
  purgeScript,
  retentionLimitCommand,
  storageDatabase
} from '../common';

interface Partition {
  description: string;
  name: string;
}

// 2025-01-01 00:00:00 Europe/Paris, the bound the product gives to p20250101
const oldPartitionBound = 1735686000;
// 2024-12-31 13:00:00 UTC, a log line stored in that old partition
const oldLogLineCtime = 1735650000;

let forwardDays = 0;
let partitioningLogLineCount = 0;
let purgeLogLineCount = 0;

beforeEach(() => {
  cy.startContainers();

  forwardDays = 0;
  partitioningLogLineCount = 0;
  purgeLogLineCount = 0;
});

afterEach(() => {
  cy.stopContainers();
});

const getPartitions = (table: string): Cypress.Chainable<Array<Partition>> =>
  cy
    .requestOnDatabase({
      database: storageDatabase,
      query: buildPartitionsQuery(table)
    })
    .then(([rows]) => rows as Array<Partition>);

const countLogLines = (file: string): Cypress.Chainable<number> =>
  cy
    .execInContainer({ command: buildLineCountCommand(file), name: 'web' })
    .then(({ output }) => Number.parseInt(output.trim(), 10));

Given('a central monitoring resources since at least one hour', () => {
  applyConfigurationViaClapi();
  checkHostsAreMonitored([{ name: 'Centreon-Server' }]);
});

Given(
  'the Forward provisioning set to {int} days and the Retention duration for logs set to {int} days in Administration > Parameters > Options',
  (forward: number, logsRetention: number) => {
    forwardDays = forward;

    buildPartitioningOptionsQueries({
      forwardDays: forward,
      logsRetentionDays: logsRetention
    }).forEach(({ database, query }) => {
      cy.requestOnDatabase({ database, query });
    });

    cy.requestOnDatabase({
      database: 'centreon',
      query: partitioningOptionsCheckQuery
    }).then(([rows]) => {
      expect(String(rows[0].value), 'Forward provisioning').to.eq(
        String(forward)
      );
    });
    cy.requestOnDatabase({
      database: storageDatabase,
      query: logsRetentionCheckQuery
    }).then(([rows]) => {
      expect(Number(rows[0].archive_retention), 'logs retention').to.eq(
        logsRetention
      );
    });
  }
);

Given(
  'an old partition {word} holding the log line {word} added at the start of the logs table',
  (partition: string, output: string) => {
    getPartitions('logs').then((partitions) => {
      expect(partitions, 'logs partitions').to.not.have.length(0);

      const [first] = partitions;

      expect(
        Number(first.description),
        `bound of the first partition ${first.name}`
      ).to.be.greaterThan(oldPartitionBound);

      cy.requestOnDatabase({
        database: storageDatabase,
        query: buildPrependPartitionQuery({
          firstPartition: first.name,
          firstPartitionBound: first.description,
          newPartition: partition,
          newPartitionBound: oldPartitionBound,
          table: 'logs'
        })
      });
    });

    cy.requestOnDatabase({
      database: storageDatabase,
      query: buildInsertLogLineQuery(output, oldLogLineCtime)
    });

    cy.requestOnDatabase({
      database: storageDatabase,
      query: buildPartitionRowCountQuery('logs', partition)
    }).then(([rows]) => {
      expect(Number(rows[0].count), `rows in ${partition}`).to.eq(1);
    });
  }
);

When(
  'the partitioning command of {word} runs as its cron user',
  (cronFile: string) => {
    countLogLines(partitioningLog).then((count) => {
      partitioningLogLineCount = count;
    });

    // A non-zero exit code of the cron command fails the step
    cy.execInContainer({
      command: buildCronLineRunCommand(cronFile, partitioningScript),
      name: 'web'
    });
  }
);

Then(
  'data_bin, logs, log_archive_host and log_archive_service each have a partition dated today plus {int} days and an empty pmax partition',
  (days: number) => {
    expect(days, 'days asked to the Forward provisioning').to.eq(forwardDays);

    cy.execInContainer({
      command: forwardPartitionNameCommand(days),
      name: 'web'
    }).then(({ output }) => {
      const expectedPartition = output.trim();

      partitionedTables.forEach((table) => {
        getPartitions(table).then((partitions) => {
          const names = partitions.map(({ name }) => name);

          expect(names, `partitions of ${table}`).to.include(expectedPartition);
          expect(names, `partitions of ${table}`).to.include('pmax');
          expect(names[names.length - 1], `last partition of ${table}`).to.eq(
            'pmax'
          );
        });

        cy.requestOnDatabase({
          database: storageDatabase,
          query: buildPartitionRowCountQuery(table, 'pmax')
        }).then(([rows]) => {
          expect(Number(rows[0].count), `rows in ${table} pmax`).to.eq(0);
        });
      });
    });
  }
);

When(
  'the purge command of {word} runs as its cron user',
  (cronFile: string) => {
    countLogLines(purgeLog).then((count) => {
      purgeLogLineCount = count;
    });

    cy.execInContainer({
      command: buildCronLineRunCommand(cronFile, purgeScript),
      name: 'web'
    });
  }
);

Then(
  'the partition {word} and the line {word} are gone from the logs table',
  (partition: string, output: string) => {
    getPartitions('logs').then((partitions) => {
      expect(
        partitions.map(({ name }) => name),
        'partitions of logs'
      ).to.include('pmax');
      expect(
        partitions.map(({ name }) => name),
        'partitions of logs'
      ).to.not.include(partition);
    });

    cy.requestOnDatabase({
      database: storageDatabase,
      query: buildLogLineCountQuery(output)
    }).then(([rows]) => {
      expect(Number(rows[0].count), `log lines ${output}`).to.eq(0);
    });
  }
);

Then(
  'no partition of the logs table is older than the {int} days of retention',
  (days: number) => {
    cy.execInContainer({
      command: retentionLimitCommand(days),
      name: 'web'
    }).then(({ output }) => {
      const retentionLimit = Number.parseInt(output.trim(), 10);

      getPartitions('logs').then((partitions) => {
        const bounded = partitions.filter(
          ({ description }) => description !== 'MAXVALUE'
        );

        expect(bounded, 'dated partitions of logs').to.not.have.length(0);
        bounded.forEach(({ name, description }) => {
          expect(
            Number(description),
            `bound of ${name} against the retention limit`
          ).to.be.at.least(retentionLimit);
        });
      });
    });
  }
);

Then('both cron logs end without error', () => {
  [
    {
      completedLine: 'PARTITIONING COMPLETED',
      file: partitioningLog,
      lineCount: (): number => partitioningLogLineCount
    },
    {
      completedLine: 'PURGE COMPLETED',
      file: purgeLog,
      lineCount: (): number => purgeLogLineCount
    }
  ].forEach(({ completedLine, file, lineCount }) => {
    cy.execInContainer({
      command: buildLinesAfterCommand(file, lineCount()),
      name: 'web'
    }).then(({ output }) => {
      const lines = output
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');

      expect(lines, `lines written to ${file}`).to.not.have.length(0);
      expect(lines[lines.length - 1], `last line of ${file}`).to.contain(
        completedLine
      );
      lines.forEach((line) => {
        expect(line, `line of ${file}`).to.not.match(
          /\b(error|critical|fatal)\b/i
        );
      });
    });
  });
});
