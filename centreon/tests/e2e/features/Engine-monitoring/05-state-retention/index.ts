import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  acknowledgeServiceProblem,
  addPersistentServiceComment,
  buildServiceCommentQuery,
  exitCodes,
  hostCheckCommandName,
  parseRetentionBlocks,
  processServiceCheckResult,
  scheduleFixedServiceDowntime
} from '../common';

const criticalOutput = 'CRITICAL - rbt retention check';
const acknowledgementComment = 'rbt retention acknowledgement';
const downtimeComment = 'rbt retention downtime';
const persistentComment = 'rbt retention persistent comment';
const downtimeDuration = 3600;

interface RetentionBlock {
  fields: Record<string, string>;
  type: string;
}

let hostName = '';
let serviceName = '';
let lastCheck = 0;
let output = '';

const toNumber = (value: string | number | null | undefined): number =>
  Number(value ?? 0);

const firstLine = (value: string | number | null | undefined): string =>
  String(value ?? '')
    .split('\n')[0]
    .trim();

const checkPersistentComment = () =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      params: [hostName, serviceName, persistentComment],
      query: buildServiceCommentQuery()
    })
    .then(([rows]) => {
      expect(rows, 'persistent comment').to.have.length(1);
      expect(toNumber(rows[0].persistent), 'comment persistent').to.eq(1);
    });

beforeEach(() => {
  cy.startContainers();

  hostName = '';
  serviceName = '';
  lastCheck = 0;
  output = '';

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an administrator is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given("state retention is enabled in the poller's Engine configuration", () => {
  cy.setEngineParameter({
    parameter: 'retain_state_information',
    value: '1'
  });
});

Given(
  'a host {string} with a passive service {string} exported to the poller',
  (host: string, service: string) => {
    hostName = host;
    serviceName = service;

    cy.installTestCheckCommands();
    cy.addHost({
      checkCommand: hostCheckCommandName,
      name: host,
      template: 'generic-host'
    })
      .addService({
        activeCheckEnabled: false,
        // Engine needs a check command, even for a passive service
        checkCommand: hostCheckCommandName,
        host,
        maxCheckAttempts: 1,
        name: service,
        passiveCheckEnabled: true,
        template: 'generic-service'
      })
      .applyPollerConfiguration();

    cy.waitForServiceStatus({
      condition: (row) => toNumber(row.enabled) === 1,
      description: 'monitored',
      host,
      service
    });
  }
);

Given(
  '{string} is Critical, acknowledged, in a fixed downtime of one hour and has a persistent comment',
  (service: string) => {
    cy.writeEngineExternalCommand(
      processServiceCheckResult(
        hostName,
        service,
        exitCodes.critical,
        criticalOutput
      )
    );
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.checked) === 1 &&
        toNumber(row.state) === exitCodes.critical,
      description: 'Critical',
      host: hostName,
      service
    });

    cy.writeEngineExternalCommand(
      acknowledgeServiceProblem(hostName, service, acknowledgementComment)
    );
    cy.writeEngineExternalCommand(
      scheduleFixedServiceDowntime(
        hostName,
        service,
        Math.floor(Date.now() / 1000),
        downtimeDuration,
        downtimeComment
      )
    );
    cy.writeEngineExternalCommand(
      addPersistentServiceComment(hostName, service, persistentComment)
    );

    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.acknowledged) === 1 &&
        toNumber(row.scheduled_downtime_depth) > 0,
      description: 'acknowledged and in downtime',
      host: hostName,
      service
    }).then((row) => {
      lastCheck = toNumber(row.last_check);
      output = firstLine(row.output);

      expect(output, 'output').to.eq(criticalOutput);
    });

    cy.waitUntil(
      () =>
        cy
          .requestOnDatabase({
            database: 'centreon_storage',
            params: [hostName, service, persistentComment],
            query: buildServiceCommentQuery()
          })
          .then(([rows]) => rows.length === 1),
      {
        errorMsg: 'The persistent comment is not stored',
        interval: 5000,
        timeout: 60000
      }
    );
  }
);

When('Engine is restarted on the poller', () => {
  cy.restartEngine();
});

Then(
  'the retention file holds the state of {string}, its acknowledgement, downtime and comment',
  (service: string) => {
    cy.getEngineSettings().then(({ retentionFile }) => {
      cy.execInContainer({ command: `cat ${retentionFile}`, name: 'web' }).then(
        ({ output: content }) => {
          const blocks = parseRetentionBlocks(content);
          const isOurs = ({ fields }: RetentionBlock): boolean =>
            fields.host_name === hostName &&
            fields.service_description === service;

          const status = blocks.find(
            (block) => block.type === 'service' && isOurs(block)
          );

          expect(status, `service block of ${service}`).to.not.eq(undefined);
          expect(status?.fields.current_state, 'current_state').to.eq(
            String(exitCodes.critical)
          );
          expect(
            status?.fields.problem_has_been_acknowledged,
            'problem_has_been_acknowledged'
          ).to.eq('1');
          expect(status?.fields.plugin_output, 'plugin_output').to.eq(output);
          expect(status?.fields.last_check, 'last_check').to.eq(
            String(lastCheck)
          );

          const downtime = blocks.find(
            (block) => block.type === 'servicedowntime' && isOurs(block)
          );

          expect(downtime, `downtime of ${service}`).to.not.eq(undefined);
          expect(downtime?.fields.fixed, 'fixed').to.eq('1');
          expect(downtime?.fields.duration, 'duration').to.eq(
            String(downtimeDuration)
          );
          expect(downtime?.fields.comment, 'downtime comment').to.eq(
            downtimeComment
          );

          const comment = blocks.find(
            (block) =>
              block.type === 'servicecomment' &&
              isOurs(block) &&
              block.fields.comment_data === persistentComment
          );

          expect(comment, `comment of ${service}`).to.not.eq(undefined);
          expect(comment?.fields.persistent, 'persistent').to.eq('1');
        }
      );
    });
  }
);

Then(
  'after the restart {string} is still Critical with the same output and last check',
  (service: string) => {
    // Broker disables the resources of a restarted poller until Engine sends
    // them again: an enabled resource holds the state Engine restored
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.enabled) === 1 && toNumber(row.resource_enabled) === 1,
      description: 'monitored again',
      host: hostName,
      service
    }).then((row) => {
      expect(toNumber(row.state), 'state').to.eq(exitCodes.critical);
      expect(toNumber(row.resource_status), 'status').to.eq(exitCodes.critical);
      expect(firstLine(row.output), 'output').to.eq(output);
      expect(toNumber(row.last_check), 'last check').to.eq(lastCheck);
    });
  }
);

Then('it is still acknowledged, in downtime and commented', () => {
  cy.waitForServiceStatus({
    condition: (row) =>
      toNumber(row.resource_enabled) === 1 &&
      toNumber(row.resource_acknowledged) === 1 &&
      toNumber(row.resource_in_downtime) === 1,
    description: 'acknowledged and in downtime',
    host: hostName,
    service: serviceName,
    timeout: 60000
  }).then((row) => {
    expect(toNumber(row.acknowledged), 'acknowledged').to.eq(1);
    expect(
      toNumber(row.scheduled_downtime_depth),
      'downtime depth'
    ).to.be.greaterThan(0);
  });

  checkPersistentComment();
});
