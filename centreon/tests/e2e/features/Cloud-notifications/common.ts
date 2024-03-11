import { Given } from '@badeball/cypress-cucumber-preprocessor';

import notificationBody from '../../fixtures/notifications/notification-creation.json';

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

          return cy.wrap(initialLineCount === currentLineCount);
        });
    },
    { interval: 5000, timeout: 40000 }
  );
};

export {
  createNotification,
  editNotification,
  enableNotificationFeature,
  notificationSentCheck,
  setBrokerNotificationsOutput,
  waitUntilLogFileChange
};
