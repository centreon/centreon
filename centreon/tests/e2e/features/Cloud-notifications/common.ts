import { Given } from '@badeball/cypress-cucumber-preprocessor';

import notificationBody from '../../fixtures/notifications/notification-creation.json';
import {
  getStatusNumberFromString,
  getStatusTypeNumberFromString
} from 'e2e/commons';

const cloudNotificationLogFile =
  '/var/log/centreon-broker/centreon-cloud-notifications.log';

Given('the user is on the Notification Rules page', () => {
  cy.visit('/centreon/configuration/notifications');
  cy.wait('@getNotifications');
});

const enableNotificationFeature = (): Cypress.Chainable => {
  return cy.execInContainer({
    command: `sed -i 's@"notification" : 2@"notification" : 3@' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
};

const createNotification = (
  body: typeof notificationBody
): Cypress.Chainable => {
  return cy
    .request({
      body,
      method: 'POST',
      url: 'centreon/api/latest/configuration/notifications'
    })
    .then((response) => {
      expect(response.status).to.eq(201);
    });
};

const editNotification = (body: typeof notificationBody): Cypress.Chainable => {
  return cy
    .request({
      body,
      method: 'PUT',
      url: 'centreon/api/latest/configuration/notifications/1'
    })
    .then((response) => {
      cy.wrap(response);
    });
};

interface Broker {
  configName: string;
  name: string;
}

const setBrokerNotificationsOutput = ({
  name,
  configName
}: Broker): Cypress.Chainable => {
  // modify the content of the lua script
  const modifyLuaFileCommands = [
    `sed -i 's/aws_region = "eu-west-1"/aws_region = "us-east-1"/' /usr/share/centreon-broker/lua/centreon-cloud-notifications.lua`,
    `sed -i 's/log_file = "\\/tmp\\/log"/log_file = "\\/var\\/log\\/centreon-broker\\/centreon-cloud-notifications.log"/' /usr/share/centreon-broker/lua/centreon-cloud-notifications.lua`,
    `sed -i 's/log_level = 0/log_level = 3/' /usr/share/centreon-broker/lua/centreon-cloud-notifications.lua`,
    `sed -i 's/refresh_delay = 500/refresh_delay = 5/' /usr/share/centreon-broker/lua/centreon-cloud-notifications.lua`,
    `sed -i 's/sender = "admin@centreon.com"/sender = "noreply@mycentreon.com"/' /usr/share/centreon-broker/lua/centreon-cloud-notifications.lua`,
    `sed -i "s@mail_command = 'aws ses send-email --region {{AWS_REGION}} --from \\"{{SENDER}}\\" --bcc {{RECIPIENTS}} --subject \\"{{SUBJECT}}\\" --html \\"{{MESSAGE}}\\"'@mail_command = ''@" /usr/share/centreon-broker/lua/centreon-cloud-notifications.lua`
  ];
  modifyLuaFileCommands.forEach((command) => {
    cy.execInContainer({
      command,
      name: 'web'
    });
  });

  const addOutputBody = {
    action: 'ADDOUTPUT',
    object: 'CENTBROKERCFG',
    values: `${configName};${name};lua`
  };
  cy.executeActionViaClapi({
    bodyContent: addOutputBody
  });

  let brokerIOID = '';

  const getBrokerIOIdByNameBody = {
    action: 'LISTOUTPUT',
    object: 'CENTBROKERCFG',
    values: `${configName}`
  };

  return cy
    .executeActionViaClapi({
      bodyContent: getBrokerIOIdByNameBody
    })
    .then((response) => {
      const listBrokersIO = response.body.result;
      const brokerIO = listBrokersIO.find(
        (currentBrokerIO) => currentBrokerIO.name === name
      );
      if (brokerIO) {
        brokerIOID = brokerIO.id;

        const setOutputPathBody = {
          action: 'SETOUTPUT',
          object: 'CENTBROKERCFG',
          values: `${configName};${brokerIOID};path;/usr/share/centreon-broker/lua/centreon-cloud-notifications.lua`
        };
        cy.executeActionViaClapi({
          bodyContent: setOutputPathBody
        });

        const setOutputCategoryBody = {
          action: 'SETOUTPUT',
          object: 'CENTBROKERCFG',
          values: `${configName};${brokerIOID};category;neb`
        };
        cy.executeActionViaClapi({
          bodyContent: setOutputCategoryBody
        });
      } else {
        throw new Error(
          `No input/output named ${name} found among config ${configName}`
        );
      }
    });
};

const notificationSentCheck = ({
  log,
  contain = true
}: {
  contain?: boolean;
  log: string;
}): Cypress.Chainable => {
  return cy
    .execInContainer({
      command: `cat ${cloudNotificationLogFile} | grep "${log}" || true`,
      name: 'web'
    })
    .then((result) => {
      // https://github.com/cypress-io/eslint-plugin-cypress?tab=readme-ov-file#chai-and-no-unused-expressions
      // eslint-disable-next-line @typescript-eslint/no-unused-expressions
      contain
        ? expect(result.output).to.contain(log)
        : expect(result.output).to.not.contain(log);
    });
};

const waitUntilLogFileChange = (): Cypress.Chainable => {
  let initialLineCount = null;

  return cy.waitUntil(
    () => {
      return cy
        .execInContainer({
          command: `cat ${cloudNotificationLogFile} 2> /dev/null | wc -l || echo 0`,
          name: 'web'
        })
        .then((result) => {
          const match = result.output.trim().match(/(\d+)$/);

          if (match === null) {
            cy.log(`Cannot get line count of ${cloudNotificationLogFile}`);

            return false;
          }

          const currentLineCount = match[1];
          cy.log(
            `Current line count of ${cloudNotificationLogFile}: ${currentLineCount}`
          );

          if (initialLineCount === null) {
            initialLineCount = currentLineCount;

            return false;
          }

          return cy.wrap(initialLineCount !== currentLineCount);
        });
    },
    { interval: 5000, timeout: 40000 }
  );
};

let servicesFoundStepCount = 0;

const stepWaitingTime = 250;
const pollingCheckTimeout = 60000;
const maxSteps = pollingCheckTimeout / stepWaitingTime;

interface MonitoredService {
  acknowledged?: boolean | null;
  inDowntime?: boolean | null;
  output?: string;
  status?: string;
  statusType?: string;
}

const checkServices = ({
  acknowledged = null,
  output = '',
  status = '',
  inDowntime = null,
  statusType = ''
}: MonitoredService): void => {
  cy.log('Checking services in database');

  let query = `SELECT COUNT(s.service_id) AS count_services from services as s WHERE s.enabled=1 AND s.description LIKE 'service_%'`;

  if (output !== '') {
    query += ` AND s.output LIKE '%${output}%'`;
  }
  if (status !== '') {
    query += ` AND s.state = ${getStatusNumberFromString(status)}`;
  }
  if (acknowledged !== null) {
    query += ` AND s.acknowledged = ${acknowledged === true ? 1 : 0}`;
  }
  if (inDowntime !== null) {
    query += ` AND s.scheduled_downtime_depth = ${inDowntime === true ? 1 : 0}`;
  }
  if (statusType !== '') {
    query += ` AND s.state_type = ${getStatusTypeNumberFromString(statusType)}`;
  }

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query
  }).then(([rows]) => {
    servicesFoundStepCount += 1;

    const foundServiceCount = rows.length ? rows[0].count_services : 0;

    cy.log('Service count in database', foundServiceCount);
    cy.log('Service database check step count', servicesFoundStepCount);

    if (foundServiceCount == 10) {
      servicesFoundStepCount = 0;

      return null;
    }

    if (servicesFoundStepCount < maxSteps) {
      cy.wait(stepWaitingTime);

      return cy.wrap(null).then(() =>
        checkServices({
          acknowledged,
          output,
          status,
          inDowntime,
          statusType
        })
      );
    }

    throw new Error(
      `The 1000 services are not monitored after ${pollingCheckTimeout}ms`
    );
  });
};

export {
  createNotification,
  editNotification,
  enableNotificationFeature,
  notificationSentCheck,
  setBrokerNotificationsOutput,
  waitUntilLogFileChange,
  checkServices
};
