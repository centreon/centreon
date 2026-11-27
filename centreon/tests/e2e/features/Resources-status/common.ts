import {
  apiBase,
  applyConfigurationViaClapi,
  checkServicesAreMonitored,
  checkThatConfigurationIsExported,
  getStatusNumberFromString,
  insertFixture,
  SubmitResult,
  submitResultsViaClapi,
  updateFixturesResult,
  versionApi
} from '../../commons';

interface Criteria {
  name: string;
  objectType: string | null;
  type: string;
  value: Array<{ id: string; name: string }>;
}

interface Filter {
  criterias: Array<Criteria>;
  name: string;
}

const serviceInAcknowledgementName = 'service_test_ack';
const serviceInDtName = 'service_test_dt';
const secondServiceInDtName = 'service_test_dt_2';
const hostInAcknowledgementName = 'test_host';
const hostChildInAcknowledgementName = 'test_host_ack';
const stateFilterContainer = '[aria-label="State filter"]';
const searchInput = 'input[placeholder="Search"]';
const refreshButton = '[aria-label="Refresh"]';
const resourceMonitoringApi = /.+api\/beta\/monitoring\/resources.?page.+/;

const apiFilterResources = `${apiBase}/${versionApi}/users/filters/events-view`;

const initializeResourceData = (): Cypress.Chainable => {
  const files = [
    'resources/clapi/host1/01-add.json',
    'resources/clapi/service1/01-add.json',
    'resources/clapi/service1/02-set-max-check.json',
    'resources/clapi/service1/03-disable-active-check.json',
    'resources/clapi/service1/04-enable-passive-check.json',
    'resources/clapi/service2/01-add.json',
    'resources/clapi/service2/02-set-max-check.json',
    'resources/clapi/service2/03-disable-active-check.json',
    'resources/clapi/service2/04-enable-passive-check.json',
    'resources/clapi/service3/01-add.json',
    'resources/clapi/service3/02-set-max-check.json',
    'resources/clapi/service3/03-disable-active-check.json',
    'resources/clapi/service3/04-enable-passive-check.json',
    'resources/clapi/service4/01-add.json',
    'resources/clapi/service4/02-set-max-check.json',
    'resources/clapi/service4/03-disable-active-check.json',
    'resources/clapi/service4/04-enable-passive-check.json'
  ];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

const initializeAckChildResources = (): Cypress.Chainable => {
  const files = [
    'resources/clapi/host3/01-add.json',
    'resources/clapi/host3/02-enable-passive-check.json',
    'resources/clapi/host3/03-disable-active-check.json',
    'resources/clapi/host3/04-set-max-check.json',
    'resources/clapi/host3/05-add-parent.json',
    'resources/clapi/host3/06-add-check-command.json',
    'resources/clapi/host3/07-enable-notification.json'
  ];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

const initializeAckResources = (): Cypress.Chainable => {
  const files = [
    'resources/clapi/host1/01-add.json',
    'resources/clapi/host1/02-enable-passive-check.json',
    'resources/clapi/host1/03-disable-active-check.json',
    'resources/clapi/host1/04-set-max-check.json',
    'resources/clapi/host1/05-add-check-command.json',
    'resources/clapi/service1/01-add.json',
    'resources/clapi/service1/02-set-max-check.json',
    'resources/clapi/service1/03-disable-active-check.json',
    'resources/clapi/service1/04-enable-passive-check.json'
  ];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

const insertAckResourceFixtures = (): Cypress.Chainable => {
  const dateBeforeLogin = new Date();
  let results: Array<SubmitResult>;
  updateFixturesResult().then((submitResult: Array<SubmitResult>) => {
    results = submitResult;
  });

  return cy
    .setUserTokenApiV1()
    .then(initializeAckResources)
    .then(initializeAckChildResources)
    .then(applyConfigurationViaClapi)
    .then(() => checkThatConfigurationIsExported({ dateBeforeLogin }))
    .then(() =>
      checkServicesAreMonitored([{ name: serviceInAcknowledgementName }])
    )
    .then(() => submitResultsViaClapi(results))
    .then(() =>
      checkServicesAreMonitored([
        { name: serviceInAcknowledgementName, output: 'submit_status_2' }
      ])
    )
    .refreshListing();
};

const setUserFilter = (body: Filter): Cypress.Chainable => {
  return cy
    .request({
      body,
      method: 'POST',
      url: apiFilterResources
    })
    .then((response) => {
      expect(response.status).to.eq(200);
      customFilterId = response.body.id;
    });
};

const deleteUserFilter = (): Cypress.Chainable => {
  if (customFilterId === null) {
    return cy.wrap({});
  }

  return cy
    .request({
      method: 'DELETE',
      url: `${apiFilterResources}/${customFilterId}`
    })
    .then((response) => {
      expect(response.status).to.eq(204);
      customFilterId = null;
    });
};

const tearDownResource = (): Cypress.Chainable => {
  const dateBeforeLogin = new Date();

  return cy
    .setUserTokenApiV1()
    .then(() => cy.removeResourceData())
    .then(applyConfigurationViaClapi)
    .then(() => checkThatConfigurationIsExported({ dateBeforeLogin }));
};

const tearDownAckResource = (): Cypress.Chainable => {
  return cy
    .setUserTokenApiV1()
    .executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'HOST',
        values: 'test_host_ack'
      }
    })
    .then(applyConfigurationViaClapi);
};

const checkIfUserNotificationsAreEnabled = (): void => {
  cy.log('Checking is user notifications are enabled.');

  const query =
    'SELECT contact_enable_notifications FROM contact WHERE contact_id = 1';

  cy.requestOnDatabase({ database: 'centreon', query }).then(([rows]) => {
    if (rows.length && rows[0].contact_enable_notifications === '1') {
      return null;
    }

    throw new Error('User notifications are disabled.');
  });
};

const submitCustomResultsViaClapi = (
  submitResults: SubmitResult
): Cypress.Chainable => {
  const timestampNow = Math.floor(Date.now() / 1000) - 15;

  return submitResultsViaClapi([
    {
      ...submitResults,
      status: getStatusNumberFromString(submitResults.status).toString(),
      updatetime: timestampNow.toString()
    }
  ]);
};

const clearCentengineLogs = (): Cypress.Chainable => {
  return cy.execInContainer({
    command: 'truncate -s 0 /var/log/centreon-engine/centengine.log',
    name: 'web'
  });
};

const checkIfNotificationsAreNotBeingSent = (): void => {
  cy.log('Checking that if the notifications are being sent.');

  const logToSearch = '[notifications] [info]';

  cy.execInContainer({
    command: `grep -iw '${logToSearch}' /var/log/centreon-engine/centengine.log | tail -1`,
    name: 'web'
  }).then(({ output }): Cypress.Chainable<null> | null => {
    if (!output) {
      return null;
    }

    throw new Error('Notifications are being sent to contacts.');
  });
};

const typeToSearchInput = (searchText: string): void => {
  cy.get(searchInput).type(`{selectall}{backspace}${searchText}{esc}{enter}`);
};

const actionBackgroundColors = {
  acknowledge: 'rgb(223, 210, 185)',
  inDowntime: 'rgb(229, 216, 243)',
  normal: 'rgba(0, 0, 0, 0)'
};
const actions = {
  acknowledge: 'Acknowledge',
  setDowntime: 'Set downtime'
};

let customFilterId = null;

// Last result of a service as stored in the real-time tables read by the page
const buildServiceLastResultQuery = (host: string, service: string): string =>
  `SELECT r.output, r.last_check, r.status, i.name AS poller
    FROM resources r
    INNER JOIN instances i ON i.instance_id = r.poller_id
    WHERE r.type = 0 AND r.enabled = 1
      AND r.name = '${service}' AND r.parent_name = '${host}'`;

const buildHostLastCheckQuery = (host: string): string =>
  `SELECT last_check FROM hosts WHERE name = '${host}' AND enabled = 1`;

const buildServiceAcknowledgementQuery = (
  host: string,
  service: string,
  comment: string
): string =>
  `SELECT COUNT(*) AS count
    FROM acknowledgements a
    INNER JOIN services s ON s.host_id = a.host_id AND s.service_id = a.service_id
    INNER JOIN hosts h ON h.host_id = s.host_id
    WHERE h.name = '${host}' AND s.description = '${service}'
      AND a.comment_data = '${comment}' AND a.deletion_time IS NULL`;

// entry_type 1 is a user comment, source 1 an external command
const buildServiceCommentQuery = (
  host: string,
  service: string,
  comment: string
): string =>
  `SELECT COUNT(*) AS count
    FROM comments c
    INNER JOIN services s ON s.host_id = c.host_id AND s.service_id = c.service_id
    INNER JOIN hosts h ON h.host_id = s.host_id
    WHERE h.name = '${host}' AND s.description = '${service}'
      AND c.data = '${comment}' AND c.entry_type = 1 AND c.source = 1
      AND c.deletion_time IS NULL`;

const buildServiceDowntimeQuery = (host: string, service: string): string =>
  `SELECT d.started, d.fixed, d.start_time, d.end_time, d.actual_end_time
    FROM downtimes d
    INNER JOIN services s ON s.host_id = d.host_id AND s.service_id = d.service_id
    INNER JOIN hosts h ON h.host_id = s.host_id
    WHERE h.name = '${host}' AND s.description = '${service}'
    ORDER BY d.downtime_id DESC
    LIMIT 1`;

// Default duration proposed by the downtime form of Resources Status
const buildSetDefaultDowntimeDurationQuery = (seconds: number): string =>
  `UPDATE options SET \`value\` = CASE \`key\`
      WHEN 'monitoring_dwt_duration' THEN '${seconds}'
      WHEN 'monitoring_dwt_duration_scale' THEN 's'
      WHEN 'monitoring_dwt_fixed' THEN '1'
    END
    WHERE \`key\` IN ('monitoring_dwt_duration', 'monitoring_dwt_duration_scale', 'monitoring_dwt_fixed')`;

// Same rendering as toDateTime of @centreon/ui ("L LT" in English):
// MM/DD/YYYY h:mm AM|PM in the timezone of the user
const formatEnglishDateTime = (timestamp: number, timeZone: string): string => {
  const parts = new Intl.DateTimeFormat('en-US', {
    day: '2-digit',
    hour: 'numeric',
    hour12: true,
    minute: '2-digit',
    month: '2-digit',
    timeZone,
    year: 'numeric'
  }).formatToParts(new Date(timestamp * 1000));
  const part = (type: string): string =>
    parts.find((item) => item.type === type)?.value ?? '';

  return `${part('month')}/${part('day')}/${part('year')} ${part('hour')}:${part('minute')} ${part('dayPeriod').toUpperCase()}`;
};

// Collapses every kind of space, as Intl and the DOM may use narrow ones
const normalizeSpaces = (text: string): string =>
  text.replace(/\s+/g, ' ').trim();

export {
  actionBackgroundColors,
  actions,
  buildHostLastCheckQuery,
  buildServiceAcknowledgementQuery,
  buildServiceCommentQuery,
  buildServiceDowntimeQuery,
  buildServiceLastResultQuery,
  buildSetDefaultDowntimeDurationQuery,
  checkIfNotificationsAreNotBeingSent,
  checkIfUserNotificationsAreEnabled,
  clearCentengineLogs,
  deleteUserFilter,
  formatEnglishDateTime,
  hostChildInAcknowledgementName,
  hostInAcknowledgementName,
  initializeResourceData,
  insertAckResourceFixtures,
  normalizeSpaces,
  refreshButton,
  resourceMonitoringApi,
  searchInput,
  secondServiceInDtName,
  serviceInAcknowledgementName,
  serviceInDtName,
  setUserFilter,
  stateFilterContainer,
  submitCustomResultsViaClapi,
  tearDownAckResource,
  tearDownResource,
  typeToSearchInput
};
