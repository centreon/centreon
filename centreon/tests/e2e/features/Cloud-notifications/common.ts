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

interface ExecInContainerResult {
  exitCode: number;
  output: string;
}

const notificationSentCheck = ({
  contain = true,
  logs
}: {
  contain?: boolean;
  logs: string | string[];
}): Cypress.Chainable => {
  cy.log(`checking logs`);

  return cy
    .waitUntil(
      () => {
        return cy
          .execInContainer({
            command: `tail -n 4 ${cloudNotificationLogFile} 2> /dev/null`,
            name: 'web'
          })
          .then((result) => {
            cy.log(result.output);

            return cy.wrap(result.output.includes('INFO: Response code: 304'));
          });
      },
      { interval: 20000, timeout: 300000 }
    )
    .then(() => {
      const command =
        typeof logs === 'string' || logs instanceof String
          ? `grep "${logs}" ${cloudNotificationLogFile}`
          : logs
              .map((log) => `grep "${log}" ${cloudNotificationLogFile}`)
              .join(' && ');

      cy.task<ExecInContainerResult>(
        'execInContainer',
        { command, name: 'web' },
        { timeout: 600000 }
      ).then((result) => {
        if (contain) {
          expect(result.exitCode).to.eq(0);
        } else {
          expect(result.exitCode).not.to.eq(0);
        }
      });
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

    if (foundServiceCount == 1000) {
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

const initializeDataFiles = () => {
  // Generate values for centreon_storage_services.txt
  let centreonStorageServicesValues = '';
  for (let i = 28; i < 1028; i++) {
    const host_id = 15;
    const description = `service_${i - 27}`;
    const service_id = i;
    const acknowledged = 0;
    const acknowledgement_type = 0;
    const action_url = '';
    const active_checks = 0;
    const check_attempt = 1;
    const check_command = 'check_centreon_ping!3!200,20%!400,50%';
    const check_freshness = 0;
    const check_interval = 5;
    const check_period = '24x7';
    const check_type = 0;
    const checked = 0;
    const default_active_checks = 0;
    const default_event_handler_enabled = 1;
    const default_flap_detection = 1;
    const default_notify = 0;
    const default_passive_checks = 1;
    const display_name = `service_${i - 27}`;
    const enabled = 1;
    const event_handler = '';
    const event_handler_enabled = 1;
    const execution_time = 0;
    const first_notification_delay = 0;
    const flap_detection = 1;
    const flap_detection_on_critical = 1;
    const flap_detection_on_ok = 1;
    const flap_detection_on_unknown = 1;
    const flap_detection_on_warning = 1;
    const flapping = 0;
    const freshness_threshold = 0;
    const high_flap_threshold = 0;
    const icon_image = '';
    const icon_image_alt = '';
    const last_hard_state = 0;
    const last_update = 1710846876;
    const latency = 0;
    const low_flap_threshold = 0;
    const max_check_attempts = 1;
    const next_check = 0;
    const no_more_notifications = 0;
    const notification_interval = 0;
    const notification_number = 0;
    const notification_period = '24x7';
    const notify = 0;
    const notify_on_critical = 1;
    const notify_on_downtime = 0;
    const notify_on_flapping = 0;
    const notify_on_recovery = 1;
    const notify_on_unknown = 0;
    const notify_on_warning = 1;
    const obsess_over_service = 1;
    const output = '';
    const passive_checks = 1;
    const percent_state_change = 0;
    const perfdata = '';
    const retain_nonstatus_information = 1;
    const retain_status_information = 1;
    const retry_interval = 1;
    const scheduled_downtime_depth = 0;
    const should_be_scheduled = 0;
    const stalk_on_critical = 0;
    const stalk_on_ok = 0;
    const stalk_on_unknown = 0;
    const stalk_on_warning = 0;
    const state = 0;
    const state_type = 1;
    const volatile = 0;

    const values = [
      host_id,
      description,
      service_id,
      acknowledged,
      acknowledgement_type,
      action_url,
      active_checks,
      check_attempt,
      check_command,
      check_freshness,
      check_interval,
      check_period,
      check_type,
      checked,
      default_active_checks,
      default_event_handler_enabled,
      default_flap_detection,
      default_notify,
      default_passive_checks,
      display_name,
      enabled,
      event_handler,
      event_handler_enabled,
      execution_time,
      first_notification_delay,
      flap_detection,
      flap_detection_on_critical,
      flap_detection_on_ok,
      flap_detection_on_unknown,
      flap_detection_on_warning,
      flapping,
      freshness_threshold,
      high_flap_threshold,
      icon_image,
      icon_image_alt,
      last_hard_state,
      last_update,
      latency,
      low_flap_threshold,
      max_check_attempts,
      next_check,
      no_more_notifications,
      notification_interval,
      notification_number,
      notification_period,
      notify,
      notify_on_critical,
      notify_on_downtime,
      notify_on_flapping,
      notify_on_recovery,
      notify_on_unknown,
      notify_on_warning,
      obsess_over_service,
      output,
      passive_checks,
      percent_state_change,
      perfdata,
      retain_nonstatus_information,
      retain_status_information,
      retry_interval,
      scheduled_downtime_depth,
      should_be_scheduled,
      stalk_on_critical,
      stalk_on_ok,
      stalk_on_unknown,
      stalk_on_warning,
      state,
      state_type,
      volatile
    ].join('\t');

    centreonStorageServicesValues += values + '\n';
  }
  cy.writeFile(
    './fixtures/notifications/centreon_storage_services.txt',
    centreonStorageServicesValues
  );
  cy.log('Values generated and stored in centreon_storage_services.txt.');

  // Generate values for centreon_services.txt
  let centreonServicesValues = '';
  for (let i = 28; i < 1028; i++) {
    const service_id = i;
    const service_template_model_stm_id = 3;
    const service_description = `service_${i - 27}`;
    const service_is_volatile = 2;
    const service_max_check_attempts = 1;
    const service_active_checks_enabled = 0;
    const service_passive_checks_enabled = 1;
    const service_parallelize_check = 2;
    const service_obsess_over_service = 2;
    const service_check_freshness = 2;
    const service_event_handler_enabled = 2;
    const service_flap_detection_enabled = 2;
    const service_process_perf_data = 2;
    const service_retain_status_information = 2;
    const service_retain_nonstatus_information = 2;
    const service_notifications_enabled = 2;
    const contact_additive_inheritance = 0;
    const cg_additive_inheritance = 0;
    const service_inherit_contacts_from_host = 1;
    const service_use_only_contacts_from_host = 0;
    const service_locked = 0;
    const service_register = 1;
    const service_activate = 1;

    const values = [
      service_id,
      service_template_model_stm_id,
      service_description,
      service_is_volatile,
      service_max_check_attempts,
      service_active_checks_enabled,
      service_passive_checks_enabled,
      service_parallelize_check,
      service_obsess_over_service,
      service_check_freshness,
      service_event_handler_enabled,
      service_flap_detection_enabled,
      service_process_perf_data,
      service_retain_status_information,
      service_retain_nonstatus_information,
      service_notifications_enabled,
      contact_additive_inheritance,
      cg_additive_inheritance,
      service_inherit_contacts_from_host,
      service_use_only_contacts_from_host,
      service_locked,
      service_register,
      service_activate
    ].join('\t');

    centreonServicesValues += values + '\n';
  }
  cy.writeFile(
    './fixtures/notifications/centreon_services.txt',
    centreonServicesValues
  );
  cy.log('Values generated and stored in centreon_services.txt.');

  // Generate values for host_service_relation.txt
  let hostServiceRelationValues = '';
  for (let i = 28; i < 1028; i++) {
    const host_host_id = 15;
    const service_service_id = i;

    const values = `${host_host_id}\t${service_service_id}\n`;
    hostServiceRelationValues += values;
  }
  cy.writeFile(
    './fixtures/notifications/host_service_relation.txt',
    hostServiceRelationValues
  );
  cy.log('Values generated and stored in host_service_relation.txt.');

  // Generate payload-check.json
  const resources: { id: number; parent: { id: number }; type: string }[] = [];
  for (let i = 28; i < 1028; i++) {
    resources.push({ id: i, parent: { id: 15 }, type: 'service' });
  }
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
  setBrokerNotificationsOutput,
  waitUntilLogFileChange,
  checkServices
};
