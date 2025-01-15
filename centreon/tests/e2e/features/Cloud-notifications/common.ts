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
    command: `sed -i 's@"notification": [0-3]@"notification": 3@' /usr/share/centreon/config/features.json`,
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

interface ExecInContainerResult {
  exitCode: number;
  output: string;
}

const notificationSentCheck = ({
  contain = true,
  logs
}: {
  contain?: boolean;
  logs: string | Array<string>;
}): Cypress.Chainable => {
  cy.log(`checking logs`);

  const command =
    typeof logs === 'string' || logs instanceof String
      ? `grep "${logs}" ${cloudNotificationLogFile}`
      : logs
          .map((log) => `grep "${log}" ${cloudNotificationLogFile}`)
          .join(' && ');

  return cy.waitUntil(
    () => {
      return cy
        .task<ExecInContainerResult>(
          'execInContainer',
          { command, name: 'web' },
          { timeout: 30000 }
        )
        .then((result) => {
          if (contain) {
            return cy.wrap(result.exitCode === 0);
          }

          return cy.wrap(result.exitCode !== 0);
        });
    },
    { interval: 1000, timeout: 120000 }
  );
};

const notificationSentCount = (count: number): void => {
  cy.log(`checking notification logs count`);

  let errorMessage = 'Notification count not found';

  cy.waitUntil(
    () => {
      return cy
        .task<ExecInContainerResult>(
          'execInContainer',
          {
            command: `grep "Sending notification" ${cloudNotificationLogFile} 2> /dev/null | wc -l || echo 0`,
            name: 'web'
          },
          { timeout: 40000 }
        )
        .then((result) => {
          const match = result.output.trim().match(/(\d+)$/);

          if (match === null) {
            cy.log(`Cannot get line count of ${cloudNotificationLogFile}`);

            return cy.wrap(false);
          }

          const currentLineCount = +match[1];

          if (currentLineCount < count) {
            errorMessage = `Notification count: ${currentLineCount} (expected: ${{ count }})`;
            cy.log(errorMessage);
          }

          return cy.wrap(currentLineCount >= count);
        });
    },
    { errorMsg: () => errorMessage, interval: 5000, timeout: 400000 }
  );
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

const initializeDataFiles = (): void => {
  let values = '';
  let centreonStorageServicesValues = '';
  let centreonServicesValues = '';
  let hostServiceRelationValues = '';
  const resources: Array<{ id: number; parent: { id: number }; type: string }> =
    [];

  // The first service will got an id of 28
  for (let i = 28; i < 1028; i += 1) {
    // Generate values for centreon_storage_services.txt
    values = [
      15, // host_id
      `service_${i - 27}`, // description
      i, // service_id
      0, // acknowledged
      0, // acknowledgement_type
      '', // action_url
      0, // active_checks
      1, // check_attempt
      'check_centreon_ping!3!200,20%!400,50%', // check_command
      0, // check_freshness
      5, // check_interval
      '24x7', // check_period
      0, // check_type
      0, // checked
      0, // default_active_checks
      1, // default_event_handler_enabled
      1, // default_flap_detection
      0, // default_notify
      1, // default_passive_checks
      `service_${i - 27}`, // display_name
      1, // enabled
      '', // event_handler
      1, // event_handler_enabled
      0, // execution_time
      0, // first_notification_delay
      1, // flap_detection
      1, // flap_detection_on_critical
      1, // flap_detection_on_ok
      1, // flap_detection_on_unknown
      1, // flap_detection_on_warning
      0, // flapping
      0, // freshness_threshold
      0, // high_flap_threshold
      '', // icon_image
      '', // icon_image_alt
      0, // last_hard_state
      1710846876, // last_update
      0, // latency
      0, // low_flap_threshold
      1, // max_check_attempts
      0, // next_check
      0, // no_more_notifications
      0, // notification_interval
      0, // notification_number
      '24x7', // notification_period
      0, // notify
      1, // notify_on_critical
      0, // notify_on_downtime
      0, // notify_on_flapping
      1, // notify_on_recovery
      0, // notify_on_unknown
      1, // notify_on_warning
      1, // obsess_over_service
      '', // output
      1, // passive_checks
      0, // percent_state_change
      '', // perfdata
      1, // retain_nonstatus_information
      1, // retain_status_information
      1, // retry_interval
      0, // scheduled_downtime_depth
      0, // should_be_scheduled
      0, // stalk_on_critical
      0, // stalk_on_ok
      0, // stalk_on_unknown
      0, // stalk_on_warning
      0, // state
      1, // state_type
      0 // volatile
    ].join('\t');
    centreonStorageServicesValues += `${values}\n`;

    // Generate values for centreon_services.txt
    values = [
      i, // service_id
      3, // service_template_model_stm_id
      `service_${i - 27}`, // service_description
      2, // service_is_volatile
      1, // service_max_check_attempts
      0, // service_active_checks_enabled
      1, // service_passive_checks_enabled
      2, // service_parallelize_check
      2, // service_obsess_over_service
      2, // service_check_freshness
      2, // service_event_handler_enabled
      2, // service_flap_detection_enabled
      2, // service_process_perf_data
      2, // service_retain_status_information
      2, // service_retain_nonstatus_information
      2, // service_notifications_enabled
      0, // contact_additive_inheritance
      0, // cg_additive_inheritance
      1, // service_inherit_contacts_from_host
      0, // service_use_only_contacts_from_host
      0, // service_locked
      1, // service_register
      1 // service_activate
    ].join('\t');
    centreonServicesValues += `${values}\n`;

    // Generate values for host_service_relation.txt
    values = `15\t${i}\n`;
    hostServiceRelationValues += values;

    // Generate payload-check.json
    resources.push({ id: i, parent: { id: 15 }, type: 'service' });
  }

  cy.writeFile(
    './fixtures/notifications/centreon_storage_services.txt',
    centreonStorageServicesValues
  );
  cy.log('Values generated and stored in centreon_storage_services.txt.');

  cy.writeFile(
    './fixtures/notifications/centreon_services.txt',
    centreonServicesValues
  );
  cy.log('Values generated and stored in centreon_services.txt.');

  cy.writeFile(
    './fixtures/notifications/host_service_relation.txt',
    hostServiceRelationValues
  );
  cy.log('Values generated and stored in host_service_relation.txt.');

  const data = {
    check: { is_forced: true },
    resources
  };
  cy.writeFile(
    './fixtures/notifications/payload-check.json',
    JSON.stringify(data, null, 2)
  );
  cy.log('JSON file generated successfully.');
};

export {
  createNotification,
  editNotification,
  enableNotificationFeature,
  initializeDataFiles,
  notificationSentCheck,
  notificationSentCount,
  setBrokerNotificationsOutput,
  waitUntilLogFileChange
};
