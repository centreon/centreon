import notificationBody from '../../fixtures/notifications/notification-creation.json';

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
      method: 'POST',
      url: 'centreon/api/latest/configuration/notifications',
      body: body
    })
    .then((response) => {
      cy.wrap(response);
    });
};

const editNotification = (body: typeof notificationBody): Cypress.Chainable => {
  return cy
    .request({
      method: 'PUT',
      url: 'centreon/api/latest/configuration/notifications/1',
      body: body
    })
    .then((response) => {
      cy.wrap(response);
    });
};

interface Broker {
  name: string;
  configName: string;
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
      command: command,
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
      const brokerIO = listBrokersIO.find((brokerIO) => brokerIO.name == name);
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
  log: string;
  contain?: boolean;
}): Cypress.Chainable => {
  return cy
    .execInContainer({
      command: `cat /var/log/centreon-broker/centreon-cloud-notifications.log | grep "${log}" || true`,
      name: 'web'
    })
    .then(({ stdout }) => {
      contain
        ? expect(stdout).to.contain(log)
        : expect(stdout).to.not.contain(log);
    });
};

const waitUntilLogFileChange = (): Cypress.Chainable => {
  let initialContent;

  return cy
    .execInContainer({
      command: `cat /var/log/centreon-broker/centreon-cloud-notifications.log`,
      name: 'web'
    })
    .then((result) => {
      cy.log('Initial result:', result);

      if (result.stdout === undefined) {
        throw new Error('No output found in log file');
      }

      initialContent = result.stdout.trim();

      return cy.waitUntil(
        () => {
          return cy
            .execInContainer({
              command: `cat /var/log/centreon-broker/centreon-cloud-notifications.log`,
              name: 'web'
            })
            .then((result) => {
              cy.log('Current result:', result);

              const currentContent = result.stdout.trim();
              return cy.wrap(currentContent !== initialContent);
            });
        },
        { timeout: 40000, interval: 5000 }
      );
    });
};

export {
  createNotification,
  editNotification,
  enableNotificationFeature,
  notificationSentCheck,
  setBrokerNotificationsOutput,
  waitUntilLogFileChange
};
